<?php

namespace App\Http\Controllers;

use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\CourseSection;
use App\Models\Rubric;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canAdminAccessAssessments = $this->canAdminAccessAssessments($request);
        abort_unless(
            $user->hasRole('student')
            || $user->hasRole('teacher')
            || $user->hasRole('super_administrator')
            || $canAdminAccessAssessments,
            403
        );
        $canManageAssessments = $user->hasRole('super_administrator') || $user->hasAnyPermission(['lms.create_assignment', 'marks.enter']);
        $canGrade = $user->hasRole('super_administrator') || $user->hasAnyPermission(['lms.grade_assignment', 'marks.enter']);
        $student = $user->hasRole('student') ? Student::where('email', $user->email)->first() : null;
        $teacher = $this->teacherFor($request);
        $isAdminOversight = ! $student
            && ! $this->shouldScopeTeacher($request)
            && $user->hasAnyRole([
                'super_administrator',
                'administrator',
                'university_administrator',
                'college_administrator',
                'department_administrator',
            ]);
        $assessmentPageTitle = $isAdminOversight ? 'Assessment Analytics' : 'Assessments';
        $studentSectionIds = $student
            ? $student->enrollments()
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                ->pluck('course_section_id')
            : collect();

        $sections = CourseSection::with(['course.department.college', 'semester', 'teacher'])
            ->withCount(['activeEnrollments', 'assessmentItems'])
            ->whereIn('status', ['planned', 'active'])
            ->when($student, fn ($query) => $query->whereIn('id', $studentSectionIds))
            ->when($this->shouldScopeTeacher($request), fn ($query) => $query->where('teacher_id', $teacher?->id))
            ->latest()
            ->get();
        $adminFilters = $isAdminOversight ? $this->adminFilterState($request) : [];
        $adminFilterOptions = $isAdminOversight ? $this->buildAdminFilterOptions($sections) : null;
        if ($isAdminOversight) {
            $sections = $this->applyAdminSectionFilters($sections, $adminFilters);
        }
        $selectedSectionId = $request->integer('section_id') ?: null;
        if ($selectedSectionId) {
            abort_unless($sections->contains('id', $selectedSectionId), 403);
        }
        $selectedAssessmentId = $request->integer('assessment_id') ?: null;
        $selectedStatus = $isAdminOversight
            ? $adminFilters['status']
            : (in_array($request->query('status'), ['draft', 'published', 'closed'], true) ? $request->query('status') : null);
        $assessmentSearch = $isAdminOversight ? $adminFilters['q'] : trim((string) $request->query('q', ''));
        $adminHierarchy = $isAdminOversight
            ? $this->buildAdminHierarchy($sections, $request, $selectedSectionId, $adminFilters)
            : null;
        $oversightStats = $isAdminOversight
            ? $this->buildOversightStats($adminHierarchy['scope_section_ids'], $sections, $adminFilters)
            : null;
        $oversightAnalytics = $isAdminOversight
            ? $this->buildOversightAnalytics($adminHierarchy['scope_section_ids'], $adminFilters)
            : null;

        $assessmentRelations = [
            'courseSection.course',
            'courseSection.semester',
            'rubric',
        ];
        if (! $isAdminOversight) {
            $assessmentRelations[] = 'submissions.student';
        }

        $assessmentItems = AssessmentItem::with($assessmentRelations)
            ->withCount([
                'submissions',
                'submissions as graded_submissions_count' => fn ($query) => $query->whereNotNull('score'),
            ])
            ->withAvg('submissions as average_score', 'score')
            ->when($student, fn ($query) => $query
                ->where('status', 'published')
                ->whereIn('course_section_id', $studentSectionIds))
            ->when($this->shouldScopeTeacher($request), fn ($query) => $query->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery
                ->where('teacher_id', $teacher?->id)
                ->whereIn('status', ['planned', 'active'])))
            ->when($selectedSectionId, fn ($query) => $query->where('course_section_id', $selectedSectionId))
            ->when($isAdminOversight && ! $selectedSectionId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($isAdminOversight, fn ($query) => $this->applyAdminAssessmentFilters($query, $adminFilters))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $assessmentGroups = $assessmentItems->getCollection()
            ->groupBy(fn (AssessmentItem $item) => $item->courseSection->course->id ?? 'unassigned')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'course_code' => $first->courseSection->course->code ?? 'Course',
                    'course_name' => $first->courseSection->course->name ?? 'Unassigned course',
                    'items' => $items,
                ];
            })
            ->sortBy('course_code')
            ->values();

        $gradebook = $assessmentItems->getCollection()->map(function (AssessmentItem $item) {
            $average = ! is_null($item->average_score)
                ? ((float) $item->average_score / max((float) $item->max_score, 1)) * (float) $item->weight_percent
                : null;

            return [
                'title' => $item->title,
                'section' => ($item->courseSection->course->code ?? 'Course').' '.$item->courseSection->section_code,
                'weight' => $item->weight_percent,
                'graded' => $item->graded_submissions_count,
                'weighted_average' => $average,
            ];
        });

        return view('assessments.index', compact('sections', 'assessmentItems', 'assessmentGroups', 'gradebook', 'canManageAssessments', 'canGrade', 'assessmentPageTitle', 'isAdminOversight', 'adminHierarchy', 'oversightStats', 'oversightAnalytics', 'student', 'selectedSectionId', 'selectedAssessmentId', 'selectedStatus', 'assessmentSearch', 'adminFilters', 'adminFilterOptions'));
    }

    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole('super_administrator')
            || ($this->canAdminAccessAssessments($request) && $user->hasAnyRole([
                'administrator',
                'university_administrator',
                'college_administrator',
                'department_administrator',
            ])),
            403
        );

        $sections = CourseSection::with(['course.department.college', 'semester', 'teacher'])
            ->withCount(['activeEnrollments', 'assessmentItems'])
            ->whereIn('status', ['planned', 'active'])
            ->latest()
            ->get();
        $adminFilters = $this->adminFilterState($request);
        $sections = $this->applyAdminSectionFilters($sections, $adminFilters);
        $selectedSectionId = $request->integer('section_id') ?: null;
        if ($selectedSectionId) {
            abort_unless($sections->contains('id', $selectedSectionId), 403);
        }
        $adminHierarchy = $this->buildAdminHierarchy($sections, $request, $selectedSectionId, $adminFilters);
        $sectionIds = $adminHierarchy['scope_section_ids'];

        $items = AssessmentItem::with(['courseSection.course.department.college', 'courseSection.semester', 'courseSection.teacher'])
            ->withCount([
                'submissions',
                'submissions as graded_submissions_count' => fn ($query) => $query->whereNotNull('score'),
            ])
            ->withAvg('submissions as average_score', 'score')
            ->whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $adminFilters))
            ->latest()
            ->get();

        $filename = 'assessment-analytics-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'College',
                'Department',
                'Stage',
                'Class',
                'Course',
                'Semester',
                'Teacher',
                'Assessment',
                'Type',
                'Status',
                'Due at',
                'Max score',
                'Weight percent',
                'Submissions',
                'Graded',
                'Pending grading',
                'Average score',
            ]);

            foreach ($items as $item) {
                $section = $item->courseSection;
                fputcsv($handle, [
                    $section->course?->department?->college?->name,
                    $section->course?->department?->name,
                    $section->grade_level ?: 'Stage not specified',
                    $section->section_code,
                    trim(($section->course?->code ? $section->course->code.' - ' : '').($section->course?->name ?? '')),
                    trim(($section->semester?->name ?? '').' '.($section->semester?->academic_year ?? '')),
                    $section->teacher?->full_name,
                    $item->title,
                    $item->type,
                    $item->status,
                    $item->due_at?->format('Y-m-d H:i'),
                    $item->max_score,
                    $item->weight_percent,
                    $item->submissions_count,
                    $item->graded_submissions_count,
                    max($item->submissions_count - $item->graded_submissions_count, 0),
                    is_null($item->average_score) ? null : number_format((float) $item->average_score, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function storeItem(Request $request)
    {
        $this->requireAnyPermission('lms.create_assignment', 'marks.enter');

        $validated = $request->validate([
            'course_section_id' => ['required', 'exists:course_sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['assignment', 'exam', 'quiz', 'project', 'lab'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'instruction_file' => ['nullable', 'file', 'max:51200'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
            'allow_submissions' => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = Auth::id();
        $validated['allow_submissions'] = $request->boolean('allow_submissions', true);
        abort_unless($this->canManageSection($request, (int) $validated['course_section_id']), 403);

        if ($request->hasFile('instruction_file')) {
            $validated['instruction_file_path'] = $request->file('instruction_file')->store('assessment-instructions', 'public');
        }

        unset($validated['instruction_file']);

        $assessmentItem = AssessmentItem::create($validated);

        app(NotificationService::class)->syncAssessmentDeadline($assessmentItem);

        return redirect()->route('assessments.index')->with('success', 'Assessment item created.');
    }

    public function edit(AssessmentItem $assessmentItem)
    {
        $this->requireAnyPermission('lms.create_assignment', 'marks.enter');
        abort_unless($this->canManageAssessment(request(), $assessmentItem), 403);

        $sections = CourseSection::with(['course', 'semester'])
            ->whereIn('status', ['planned', 'active'])
            ->when($this->shouldScopeTeacher(request()), fn ($query) => $query->where('teacher_id', $this->teacherFor(request())?->id))
            ->latest()
            ->get();

        return view('assessments.edit', compact('assessmentItem', 'sections'));
    }

    public function update(Request $request, AssessmentItem $assessmentItem)
    {
        $this->requireAnyPermission('lms.create_assignment', 'marks.enter');

        $validated = $request->validate([
            'course_section_id' => ['required', 'exists:course_sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['assignment', 'exam', 'quiz', 'project', 'lab'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'instruction_file' => ['nullable', 'file', 'max:51200'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'opens_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
            'allow_submissions' => ['nullable', 'boolean'],
        ]);

        $validated['allow_submissions'] = $request->boolean('allow_submissions');
        abort_unless($this->canManageAssessment($request, $assessmentItem), 403);
        abort_unless($this->canManageSection($request, (int) $validated['course_section_id']), 403);

        if ($request->hasFile('instruction_file')) {
            if ($assessmentItem->instruction_file_path) {
                Storage::disk('public')->delete($assessmentItem->instruction_file_path);
            }

            $validated['instruction_file_path'] = $request->file('instruction_file')->store('assessment-instructions', 'public');
        }

        unset($validated['instruction_file']);

        $assessmentItem->update($validated);

        app(NotificationService::class)->syncAssessmentDeadline($assessmentItem);

        return redirect()->route('assessments.index')->with('success', 'Assessment item updated.');
    }

    public function storeRubric(Request $request, AssessmentItem $assessmentItem)
    {
        $this->requireAnyPermission('lms.create_assignment', 'lms.grade_assignment', 'marks.enter');
        abort_unless($this->canManageAssessment($request, $assessmentItem), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'criteria_text' => ['required', 'string', 'max:5000'],
        ]);

        $criteria = collect(preg_split('/\r\n|\r|\n/', $validated['criteria_text']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$label, $points] = array_pad(explode('|', $line, 2), 2, null);

                return [
                    'label' => trim($label),
                    'points' => is_numeric($points) ? (float) $points : null,
                ];
            })
            ->values()
            ->all();

        Rubric::updateOrCreate(
            ['assessment_item_id' => $assessmentItem->id],
            [
                'title' => $validated['title'],
                'criteria' => $criteria,
                'total_points' => collect($criteria)->sum(fn ($criterion) => (float) ($criterion['points'] ?? 0)) ?: $assessmentItem->max_score,
            ]
        );

        return redirect()->route('assessments.index')->with('success', 'Rubric saved.');
    }

    public function submitWork(Request $request, AssessmentItem $assessmentItem)
    {
        $user = $request->user();
        abort_unless($user->hasRole('student'), 403);

        $student = Student::where('email', $user->email)->firstOrFail();
        abort_unless($assessmentItem->status === 'published' && $assessmentItem->allow_submissions, 403);
        abort_if($assessmentItem->due_at && now()->greaterThan($assessmentItem->due_at), 403, 'This assessment is past its due date.');

        $isEnrolled = $assessmentItem->courseSection
            ->activeEnrollments()
            ->where('student_id', $student->id)
            ->exists();

        abort_unless($isEnrolled, 403);

        $validated = $request->validate([
            'content' => ['nullable', 'required_without:submission_file', 'string', 'max:10000'],
            'submission_file' => ['nullable', 'required_without:content', 'file', 'max:51200'],
        ]);

        $attachmentPath = $request->hasFile('submission_file')
            ? $request->file('submission_file')->store('assessment-submissions', 'public')
            : null;

        $submission = AssessmentSubmission::updateOrCreate(
            [
                'assessment_item_id' => $assessmentItem->id,
                'student_id' => $student->id,
            ],
            [
                'content' => $validated['content'] ?? null,
                'attachment_path' => $attachmentPath,
                'submitted_at' => now(),
                'status' => 'submitted',
                'graded_by' => null,
                'score' => null,
                'feedback' => null,
                'graded_at' => null,
            ]
        );

        app(NotificationService::class)->notifyAssessmentSubmitted($submission);

        return redirect()->route('assessments.index')->with('success', 'Submission received.');
    }

    public function downloadItemFile(Request $request, AssessmentItem $assessmentItem)
    {
        abort_unless($assessmentItem->instruction_file_path, 404);
        abort_unless($this->canAccessAssessment($request, $assessmentItem), 403);

        return Storage::disk('public')->download($assessmentItem->instruction_file_path);
    }

    public function downloadSubmissionFile(Request $request, AssessmentSubmission $submission)
    {
        abort_unless($submission->attachment_path, 404);

        $user = $request->user();
        $student = $user->hasRole('student') ? Student::where('email', $user->email)->first() : null;
        $canDownload = ($student && $submission->student_id === $student->id)
            || $user->hasRole('super_administrator')
            || ($user->hasAnyPermission(['lms.grade_assignment', 'marks.enter']) && $this->canManageAssessment($request, $submission->assessmentItem));

        abort_unless($canDownload, 403);

        return Storage::disk('public')->download($submission->attachment_path);
    }

    public function gradeSubmission(Request $request, AssessmentSubmission $submission)
    {
        $this->requireAnyPermission('lms.grade_assignment', 'marks.enter');
        abort_unless($this->canManageAssessment($request, $submission->assessmentItem), 403);

        $maxScore = (float) $submission->assessmentItem->max_score;

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$maxScore],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => 'graded',
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ]);

        return redirect()->route('assessments.index')->with('success', 'Submission graded.');
    }

    private function canAdminAccessAssessments(Request $request): bool
    {
        $user = $request->user();

        return $user->hasAnyRole([
            'administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'lms_administrator',
            'teaching_assistant',
        ]) && $user->hasAnyPermission(['lms.view', 'lms.create_assignment', 'lms.grade_assignment', 'marks.enter', 'marks.view']);
    }

    private function adminFilterState(Request $request): array
    {
        $status = in_array($request->query('status'), ['draft', 'published', 'closed'], true)
            ? $request->query('status')
            : null;
        $type = in_array($request->query('type'), ['assignment', 'exam', 'quiz', 'project', 'lab'], true)
            ? $request->query('type')
            : null;

        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => $status,
            'type' => $type,
            'semester_id' => $request->integer('semester_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'due_from' => $this->normalizedDateFilter($request->query('due_from')),
            'due_until' => $this->normalizedDateFilter($request->query('due_until')),
        ];
    }

    private function normalizedDateFilter($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function buildAdminFilterOptions($sections): array
    {
        $sectionIds = $sections->pluck('id');
        $types = $sectionIds->isEmpty()
            ? collect()
            : AssessmentItem::whereIn('course_section_id', $sectionIds)
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type');

        return [
            'semesters' => $sections->pluck('semester')->filter()->unique('id')->sortBy([['academic_year', 'desc'], ['name', 'asc']])->values(),
            'courses' => $sections->pluck('course')->filter()->unique('id')->sortBy('code')->values(),
            'teachers' => $sections->pluck('teacher')->filter()->unique('id')->sortBy('full_name')->values(),
            'types' => $types->isNotEmpty() ? $types : collect(['assignment', 'exam', 'quiz', 'project', 'lab']),
        ];
    }

    private function applyAdminSectionFilters($sections, array $filters)
    {
        return $sections
            ->when($filters['semester_id'], fn ($collection) => $collection->where('semester_id', $filters['semester_id']))
            ->when($filters['course_id'], fn ($collection) => $collection->where('course_id', $filters['course_id']))
            ->when($filters['teacher_id'], fn ($collection) => $collection->where('teacher_id', $filters['teacher_id']))
            ->values();
    }

    private function applyAdminAssessmentFilters($query, array $filters)
    {
        return $query
            ->when($filters['status'], fn ($assessmentQuery) => $assessmentQuery->where('status', $filters['status']))
            ->when($filters['type'], fn ($assessmentQuery) => $assessmentQuery->where('type', $filters['type']))
            ->when($filters['due_from'], fn ($assessmentQuery) => $assessmentQuery->whereDate('due_at', '>=', $filters['due_from']))
            ->when($filters['due_until'], fn ($assessmentQuery) => $assessmentQuery->whereDate('due_at', '<=', $filters['due_until']))
            ->when($filters['q'] !== '', fn ($assessmentQuery) => $assessmentQuery->where(function ($searchQuery) use ($filters) {
                $searchQuery->where('title', 'like', "%{$filters['q']}%")
                    ->orWhere('type', 'like', "%{$filters['q']}%");
            }));
    }

    private function filteredAssessmentCountsBySection($sectionIds, array $filters)
    {
        if ($sectionIds->isEmpty()) {
            return collect();
        }

        return AssessmentItem::whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->selectRaw('course_section_id, count(*) as assessment_count')
            ->groupBy('course_section_id')
            ->pluck('assessment_count', 'course_section_id');
    }

    private function buildAdminHierarchy($sections, Request $request, ?int $selectedSectionId, array $filters): array
    {
        $selectedSection = $selectedSectionId ? $sections->firstWhere('id', $selectedSectionId) : null;
        $hierarchySections = $sections
            ->filter(fn (CourseSection $section) => $section->course?->department?->college)
            ->values();
        $assessmentCounts = $this->filteredAssessmentCountsBySection($hierarchySections->pluck('id'), $filters);
        $assessmentCountFor = fn (CourseSection $section) => (int) ($assessmentCounts[$section->id] ?? 0);
        $hierarchySections->each(fn (CourseSection $section) => $section->setAttribute('filtered_assessment_items_count', $assessmentCountFor($section)));
        $selectedCollegeId = $selectedSection?->course?->department?->college_id
            ?: ($request->integer('college_id') ?: null);
        $selectedDepartmentId = $selectedSection?->course?->department_id
            ?: ($request->integer('department_id') ?: null);
        $selectedGradeKey = $selectedSection
            ? ($selectedSection->grade_level ?: '__unassigned__')
            : (trim((string) ($request->query('stage') ?? $request->query('grade', ''))) ?: null);
        $selectedGradeAlias = $this->stageAlias($selectedGradeKey);

        $collegeCards = $hierarchySections
            ->groupBy(fn (CourseSection $section) => $section->course->department->college_id)
            ->map(function ($collegeSections) use ($assessmentCountFor) {
                return [
                    'college' => $collegeSections->first()->course->department->college,
                    'department_count' => $collegeSections->pluck('course.department_id')->unique()->count(),
                    'class_count' => $collegeSections->count(),
                    'assessment_count' => $collegeSections->sum(fn (CourseSection $section) => $assessmentCountFor($section)),
                ];
            })
            ->sortBy(fn ($card) => $card['college']->name)
            ->values();
        $selectedCollegeCard = $collegeCards->first(fn ($card) => $card['college']->id === $selectedCollegeId);
        $selectedCollege = $selectedCollegeCard['college'] ?? null;
        abort_if($request->filled('college_id') && ! $selectedCollege, 404);

        $collegeSections = $selectedCollege
            ? $hierarchySections->filter(fn (CourseSection $section) => $section->course->department->college_id === $selectedCollege->id)
            : collect();
        $departmentCards = $collegeSections
            ->groupBy(fn (CourseSection $section) => $section->course->department_id)
            ->map(function ($departmentSections) use ($assessmentCountFor) {
                return [
                    'department' => $departmentSections->first()->course->department,
                    'grade_count' => $departmentSections->map(fn (CourseSection $section) => $section->grade_level ?: '__unassigned__')->unique()->count(),
                    'class_count' => $departmentSections->count(),
                    'assessment_count' => $departmentSections->sum(fn (CourseSection $section) => $assessmentCountFor($section)),
                ];
            })
            ->sortBy(fn ($card) => $card['department']->name)
            ->values();
        $selectedDepartmentCard = $departmentCards->first(fn ($card) => $card['department']->id === $selectedDepartmentId);
        $selectedDepartment = $selectedDepartmentCard['department'] ?? null;
        abort_if($request->filled('department_id') && ! $selectedDepartment, 404);

        $departmentSections = $selectedDepartment
            ? $collegeSections->filter(fn (CourseSection $section) => $section->course->department_id === $selectedDepartment->id)
            : collect();
        $gradeCards = $departmentSections
            ->groupBy(fn (CourseSection $section) => (string) $this->stageAlias($section->grade_level ?: '__unassigned__'))
            ->map(function ($gradeSections, $gradeAlias) use ($assessmentCountFor) {
                $labels = $gradeSections
                    ->map(fn (CourseSection $section) => $section->grade_level ?: '__unassigned__')
                    ->unique()
                    ->values();

                return [
                    'key' => $gradeAlias,
                    'alias' => $gradeAlias,
                    'label' => $this->stageLabel($gradeAlias, $labels->first()),
                    'class_count' => $gradeSections->count(),
                    'student_count' => $gradeSections->sum('active_enrollments_count'),
                    'assessment_count' => $gradeSections->sum(fn (CourseSection $section) => $assessmentCountFor($section)),
                ];
            })
            ->sortBy(fn ($card) => $this->stageSortValue($card['alias'], $card['label']))
            ->values();
        $selectedGrade = $gradeCards->first(fn ($card) => (string) $card['alias'] === (string) $selectedGradeAlias);
        abort_if(($request->filled('grade') || $request->filled('stage')) && ! $selectedGrade, 404);

        $gradeSections = $selectedGrade
            ? $departmentSections
                ->filter(fn (CourseSection $section) => (string) $this->stageAlias($section->grade_level ?: '__unassigned__') === (string) $selectedGrade['alias'])
                ->sortBy(fn (CourseSection $section) => ($section->course->name ?? '').' '.$section->section_code)
                ->values()
            : collect();

        $scopeSections = match (true) {
            (bool) $selectedSection => collect([$selectedSection]),
            (bool) $selectedGrade => $gradeSections,
            (bool) $selectedDepartment => $departmentSections,
            (bool) $selectedCollege => $collegeSections,
            default => $hierarchySections,
        };

        return [
            'colleges' => $collegeCards,
            'selected_college' => $selectedCollege,
            'departments' => $departmentCards,
            'selected_department' => $selectedDepartment,
            'grades' => $gradeCards,
            'selected_grade' => $selectedGrade,
            'classes' => $gradeSections,
            'selected_section' => $selectedSection,
            'scope_section_ids' => $scopeSections->pluck('id'),
        ];
    }

    private function buildOversightStats($sectionIds, $sections, array $filters): array
    {
        if ($sectionIds->isEmpty()) {
            return [
                'total' => 0,
                'published' => 0,
                'draft' => 0,
                'closed' => 0,
                'submission_rate' => null,
                'grading_rate' => null,
                'submitted' => 0,
                'graded' => 0,
                'ungraded' => 0,
                'overdue' => 0,
            ];
        }

        $assessmentSummary = AssessmentItem::whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->selectRaw("count(*) as total, sum(case when status = 'published' then 1 else 0 end) as published, sum(case when status = 'draft' then 1 else 0 end) as draft, sum(case when status = 'closed' then 1 else 0 end) as closed, sum(case when status = 'published' and due_at is not null and due_at < ? then 1 else 0 end) as overdue", [now()])
            ->first();
        $submissionSummary = AssessmentSubmission::whereHas('assessmentItem', fn ($query) => $this->applyAdminAssessmentFilters($query->whereIn('course_section_id', $sectionIds), $filters))
            ->selectRaw('count(*) as submitted, sum(case when score is not null then 1 else 0 end) as graded, sum(case when score is null then 1 else 0 end) as ungraded')
            ->first();
        $eligibleAssessmentCounts = AssessmentItem::whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->whereIn('status', ['published', 'closed'])
            ->where('allow_submissions', true)
            ->selectRaw('course_section_id, count(*) as assessment_count')
            ->groupBy('course_section_id')
            ->pluck('assessment_count', 'course_section_id');
        $enrollmentCounts = $sections->whereIn('id', $sectionIds)->pluck('active_enrollments_count', 'id');
        $expectedSubmissions = $eligibleAssessmentCounts->map(
            fn ($assessmentCount, $sectionId) => $assessmentCount * ($enrollmentCounts[$sectionId] ?? 0)
        )->sum();
        $submitted = (int) ($submissionSummary->submitted ?? 0);
        $graded = (int) ($submissionSummary->graded ?? 0);

        return [
            'total' => (int) ($assessmentSummary->total ?? 0),
            'published' => (int) ($assessmentSummary->published ?? 0),
            'draft' => (int) ($assessmentSummary->draft ?? 0),
            'closed' => (int) ($assessmentSummary->closed ?? 0),
            'submission_rate' => $expectedSubmissions > 0 ? min(round(($submitted / $expectedSubmissions) * 100, 1), 100) : null,
            'grading_rate' => $submitted > 0 ? round(($graded / $submitted) * 100, 1) : null,
            'submitted' => $submitted,
            'graded' => $graded,
            'ungraded' => (int) ($submissionSummary->ungraded ?? 0),
            'overdue' => (int) ($assessmentSummary->overdue ?? 0),
        ];
    }

    private function buildOversightAnalytics($sectionIds, array $filters): array
    {
        if ($sectionIds->isEmpty()) {
            return [
                'types' => collect(),
                'risk_items' => collect(),
                'upcoming' => collect(),
            ];
        }

        $types = AssessmentItem::whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        $riskItems = AssessmentItem::with(['courseSection.course.department.college'])
            ->withCount([
                'submissions',
                'submissions as ungraded_submissions_count' => fn ($query) => $query->whereNull('score'),
            ])
            ->whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->where(function ($query) {
                $query->where(fn ($dueQuery) => $dueQuery
                    ->where('status', 'published')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now()))
                    ->orWhereHas('submissions', fn ($submissionQuery) => $submissionQuery->whereNull('score'));
            })
            ->orderByRaw("case when status = 'published' and due_at is not null and due_at < ? then 0 else 1 end", [now()])
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $upcoming = AssessmentItem::with(['courseSection.course.department.college'])
            ->withCount('submissions')
            ->whereIn('course_section_id', $sectionIds)
            ->tap(fn ($query) => $this->applyAdminAssessmentFilters($query, $filters))
            ->where('status', 'published')
            ->whereBetween('due_at', [now(), now()->addDays(7)])
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        return [
            'types' => $types,
            'risk_items' => $riskItems,
            'upcoming' => $upcoming,
        ];
    }

    private function canAccessAssessment(Request $request, AssessmentItem $assessmentItem): bool
    {
        $user = $request->user();

        if ($user->hasRole('super_administrator')) {
            return true;
        }

        if ($this->shouldScopeTeacher($request)) {
            return $this->canManageAssessment($request, $assessmentItem);
        }

        if ($user->hasAnyRole(['administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'lms_administrator', 'teaching_assistant'])
            && $user->hasAnyPermission(['lms.view', 'lms.create_assignment', 'lms.grade_assignment', 'marks.enter', 'marks.view'])) {
            return true;
        }

        if (! $user->hasRole('student') || $assessmentItem->status !== 'published') {
            return false;
        }

        $student = Student::where('email', $user->email)->first();

        return $student
            ? $assessmentItem->courseSection->activeEnrollments()->where('student_id', $student->id)->exists()
            : false;
    }

    private function canManageAssessment(Request $request, AssessmentItem $assessmentItem): bool
    {
        if (! $this->shouldScopeTeacher($request)) {
            return true;
        }

        $section = $assessmentItem->courseSection;

        return $section
            && in_array($section->status, ['planned', 'active'], true)
            && ! $section->trashed()
            && $section->teacher_id === $this->teacherFor($request)?->id;
    }

    private function canManageSection(Request $request, int $sectionId): bool
    {
        if (! $this->shouldScopeTeacher($request)) {
            return true;
        }

        return CourseSection::whereKey($sectionId)
            ->whereIn('status', ['planned', 'active'])
            ->where('teacher_id', $this->teacherFor($request)?->id)
            ->exists();
    }

    private function shouldScopeTeacher(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('teacher') && ! $user->hasRole('super_administrator');
    }

    private function stageAlias(?string $stage): ?string
    {
        if (! $stage || $stage === '__unassigned__') {
            return $stage;
        }

        $normalized = strtolower(trim($stage));

        if (preg_match('/\d+/', $normalized, $matches)) {
            return $matches[0];
        }

        $ordinals = [
            'first' => '1',
            'one' => '1',
            'second' => '2',
            'two' => '2',
            'third' => '3',
            'three' => '3',
            'fourth' => '4',
            'four' => '4',
            'fifth' => '5',
            'five' => '5',
            'sixth' => '6',
            'six' => '6',
        ];

        foreach ($ordinals as $word => $number) {
            if (str_contains($normalized, $word)) {
                return $number;
            }
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized);
    }

    private function stageLabel(?string $alias, ?string $fallback): string
    {
        if ($alias === '__unassigned__') {
            return 'Stage not specified';
        }

        if ($alias && ctype_digit((string) $alias)) {
            return 'Stage '.$alias;
        }

        return $fallback && $fallback !== '__unassigned__' ? $fallback : 'Stage not specified';
    }

    private function stageSortValue(?string $alias, string $label): string
    {
        if ($alias === '__unassigned__') {
            return '9999';
        }

        if ($alias && ctype_digit((string) $alias)) {
            return str_pad((string) $alias, 4, '0', STR_PAD_LEFT);
        }

        return '5000-'.$label;
    }

    private function teacherFor(Request $request): ?Teacher
    {
        $user = $request->user();

        return $user ? Teacher::where('email', $user->email)->first() : null;
    }
}
