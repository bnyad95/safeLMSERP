<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Mark;
use App\Services\MarkSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarkSubmissionController extends Controller
{
    public function __construct(private MarkSubmissionService $service) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.review', 'marks.approve', 'marks.publish'])) {
            abort(403);
        }

        $canApprove = $this->can($user, ['marks.approve']);
        $canReject = $this->can($user, ['marks.approve', 'marks.request_change']);
        $canPublish = $this->can($user, ['marks.publish']);
        $canEnterFinalExam = $this->can($user, ['marks.approve']);
        $filters = $this->queueFilterState($request);
        $filterOptions = $this->queueFilterOptions();

        $finalExamDraftCount = $this->finalExamMarkQuery($filters)
            ->whereNotNull('prefinal_mark')
            ->count();

        $pendingSubmissions = $this->queueMarkQuery($filters)
            ->where(fn ($query) => $query
                ->where('submission_status', 'submitted')
                ->orWhere('submission_status', 'under_review'))
            ->paginate(20, ['*'], 'pending_page')
            ->withQueryString();

        $approvedMarks = $this->queueMarkQuery($filters)
            ->where('submission_status', 'approved')
            ->where('visibility_status', 'draft')
            ->with(['student', 'course'])
            ->paginate(20, ['*'], 'approved_page')
            ->withQueryString();
        $queueStats = [
            'pending' => $this->queueMarkQuery($filters)->where('submission_status', 'submitted')->count(),
            'under_review' => $this->queueMarkQuery($filters)->where('submission_status', 'under_review')->count(),
            'approved' => $this->queueMarkQuery($filters)->where('submission_status', 'approved')->where('visibility_status', 'draft')->count(),
        ];

        return view('marks.submission-queue', compact(
            'finalExamDraftCount',
            'pendingSubmissions',
            'approvedMarks',
            'canApprove',
            'canReject',
            'canPublish',
            'canEnterFinalExam',
            'filters',
            'filterOptions',
            'queueStats'
        ));
    }

    public function finalExamEntry(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.approve'])) {
            abort(403);
        }

        $filters = $this->queueFilterState($request);
        $filters['submission_status'] = '';
        $filterOptions = $this->finalExamFilterOptions();
        $courseCardFilters = $filters;
        $courseCardFilters['course_id'] = null;

        $courseCards = $this->finalExamCourseCards($courseCardFilters);
        $finalExamStats = $this->finalExamStats($filters);

        return view('marks.final-exam-entry', compact(
            'finalExamStats',
            'courseCards',
            'filters',
            'filterOptions'
        ));
    }

    public function finalExamCourse(Request $request, Course $course)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.approve'])) {
            abort(403);
        }

        $filters = $this->queueFilterState($request);
        $filters['submission_status'] = '';
        $filters['course_id'] = $course->id;
        $filterOptions = $this->finalExamFilterOptions();

        $finalExamDrafts = $this->finalExamMarkQuery($filters)
            ->whereNotNull('prefinal_mark')
            ->paginate(20, ['*'], 'final_exam_page')
            ->withQueryString();
        $finalExamStats = $this->finalExamStats($filters);

        return view('marks.final-exam-course', compact(
            'course',
            'finalExamDrafts',
            'finalExamStats',
            'filters',
            'filterOptions'
        ));
    }

    public function submitMarks(Request $request)
    {
        return response()->json([
            'error' => 'Final exam marks are submitted by the examination committee.',
        ], 403);
    }

    public function approveMarks(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.approve'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->normalizeMarkIds($request);

        $validated = $request->validate([
            'mark_ids' => 'required|array',
            'mark_ids.*' => 'integer|exists:marks,id',
            'notes' => 'nullable|string',
        ]);

        $result = $this->service->approveMarks($validated['mark_ids'], $validated['notes'] ?? null);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('marks.submission-queue')
                ->with('success', "Approved {$result['updated']} marks");
        }

        return response()->json([
            'success' => true,
            'message' => "Approved {$result['updated']} marks",
            'data' => $result,
        ]);
    }

    public function storeFinalExam(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.approve'])) {
            abort(403);
        }

        $validated = $request->validate([
            'mark_id' => 'required|integer|exists:marks,id',
            'trial' => 'required|in:first,second',
            'score' => 'required|numeric|min:0|max:100',
        ]);

        $mark = Mark::whereKey($validated['mark_id'])->firstOrFail();

        if (is_null($mark->prefinal_mark)) {
            return back()->withErrors(['score' => 'Pre-final mark must be entered by the teacher before final exam scores.']);
        }

        if ($mark->visibility_status === 'published') {
            return back()->withErrors(['score' => 'Published marks cannot be changed.']);
        }

        if ($validated['trial'] === 'second') {
            if (is_null($mark->first_trial_final_exam)) {
                return back()->withErrors(['score' => 'Enter first trial final exam before second trial.']);
            }

            if (((float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam) >= 50) {
                return back()->withErrors(['score' => 'Second trial is only available for students who failed the first trial.']);
            }
        }

        if ($validated['trial'] === 'first') {
            $mark->first_trial_final_exam = $validated['score'];
            $mark->second_trial_final_exam = null;
        } else {
            $mark->second_trial_final_exam = $validated['score'];
        }

        $mark->recalculateFinalMark();
        $firstTrialFailed = $validated['trial'] === 'first' && (float) $mark->final_mark < 50;
        $mark->submission_status = $firstTrialFailed ? 'draft' : 'submitted';
        $mark->visibility_status = 'draft';
        $mark->submitted_at = $firstTrialFailed ? null : now();
        $mark->reviewer_notes = null;
        $mark->reviewed_by = null;
        $mark->reviewed_at = null;
        $mark->save();

        $redirectTo = (string) $request->input('redirect_to', '');
        $redirectTo = str_starts_with($redirectTo, route('marks.final-exam.index'))
            ? $redirectTo
            : route('marks.submission-queue');

        return redirect()
            ->to($redirectTo)
            ->with('success', $firstTrialFailed
                ? 'First trial score saved. Student is eligible for second trial.'
                : 'Final exam score saved and submitted for review.');
    }

    public function rejectMarks(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.approve', 'marks.request_change'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->normalizeMarkIds($request);

        $validated = $request->validate([
            'mark_ids' => 'required|array',
            'mark_ids.*' => 'integer|exists:marks,id',
            'notes' => 'required|string',
        ]);

        $result = $this->service->rejectMarks($validated['mark_ids'], $validated['notes']);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('marks.submission-queue')
                ->with('success', "Rejected {$result['updated']} marks");
        }

        return response()->json([
            'success' => true,
            'message' => "Rejected {$result['updated']} marks",
            'data' => $result,
        ]);
    }

    public function publishMarks(Request $request)
    {
        $user = Auth::user();

        if (! $this->can($user, ['marks.publish'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->normalizeMarkIds($request);

        $validated = $request->validate([
            'mark_ids' => 'required|array',
            'mark_ids.*' => 'integer|exists:marks,id',
        ]);

        $result = $this->service->publishMarks($validated['mark_ids']);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('marks.submission-queue')
                ->with('success', "Published {$result['updated']} marks");
        }

        return response()->json([
            'success' => true,
            'message' => "Published {$result['updated']} marks",
            'data' => $result,
        ]);
    }

    private function normalizeMarkIds(Request $request): void
    {
        $markIds = $request->input('mark_ids');

        if (! is_string($markIds)) {
            return;
        }

        $decoded = json_decode($markIds, true);

        if (is_array($decoded)) {
            $request->merge(['mark_ids' => $decoded]);
        }
    }

    private function can($user, array $permissions): bool
    {
        return $user->hasRole('super_administrator')
            || $user->hasAnyPermission($permissions);
    }

    private function queueMarkQuery(array $filters)
    {
        $query = Mark::with([
            'student.department.college',
            'course.department.college',
            'courseSection.course.department.college',
            'courseSection.semester',
            'courseSection.teacher',
            'reviewer',
        ]);

        $this->applyQueueFilters($query, $filters);

        return $query->latest();
    }

    private function queueFilterState(Request $request): array
    {
        $submissionStatus = in_array($request->query('submission_status'), ['submitted', 'under_review', 'approved'], true)
            ? $request->query('submission_status')
            : '';

        return [
            'q' => trim((string) $request->query('q', '')),
            'academic_year' => trim((string) $request->query('academic_year', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage' => trim((string) ($request->query('stage') ?? $request->query('grade', ''))),
            'semester_id' => $request->integer('semester_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'submission_status' => $submissionStatus,
        ];
    }

    private function queueFilterOptions(): array
    {
        $marks = Mark::with([
            'student.department.college',
            'course.department.college',
            'courseSection.course.department.college',
            'courseSection.semester',
            'courseSection.teacher',
        ])
            ->where(fn ($query) => $query
                ->whereIn('submission_status', ['submitted', 'under_review'])
                ->orWhere(fn ($approvedQuery) => $approvedQuery
                    ->where('submission_status', 'approved')
                    ->where('visibility_status', 'draft')))
            ->get();
        $stages = $marks
            ->groupBy(fn (Mark $mark) => (string) $this->stageAlias($mark->courseSection?->grade_level ?: '__unassigned__'))
            ->map(fn ($stageMarks, $alias) => [
                'key' => $alias,
                'label' => $this->stageLabel($alias, $stageMarks->first()->courseSection?->grade_level),
            ])
            ->sortBy(fn ($stage) => $this->stageSortValue($stage['key'], $stage['label']))
            ->values();

        return [
            'colleges' => $marks->map(fn (Mark $mark) => $this->markCollege($mark))->filter()->unique('id')->sortBy('name')->values(),
            'departments' => $marks->map(fn (Mark $mark) => $this->markDepartment($mark))->filter()->unique('id')->sortBy('name')->values(),
            'stages' => $stages,
            'semesters' => $marks->pluck('courseSection.semester')->filter()->unique('id')->sortByDesc('academic_year')->values(),
            'courses' => $marks->pluck('course')->filter()->unique('id')->sortBy('code')->values(),
            'teachers' => $marks->pluck('courseSection.teacher')->filter()->unique('id')->sortBy('full_name')->values(),
        ];
    }

    private function finalExamFilterOptions(): array
    {
        $marks = $this->finalExamMarkQuery($this->emptyQueueFilters())
            ->whereNotNull('prefinal_mark')
            ->get();

        $stages = $marks
            ->groupBy(fn (Mark $mark) => (string) $this->stageAlias($mark->courseSection?->grade_level ?: '__unassigned__'))
            ->map(fn ($stageMarks, $alias) => [
                'key' => $alias,
                'label' => $this->stageLabel($alias, $stageMarks->first()->courseSection?->grade_level),
            ])
            ->sortBy(fn ($stage) => $this->stageSortValue($stage['key'], $stage['label']))
            ->values();

        return [
            'academic_years' => $marks
                ->map(fn (Mark $mark) => $mark->courseSection?->semester?->academic_year)
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),
            'colleges' => $marks->map(fn (Mark $mark) => $this->markCollege($mark))->filter()->unique('id')->sortBy('name')->values(),
            'departments' => $marks->map(fn (Mark $mark) => $this->markDepartment($mark))->filter()->unique('id')->sortBy('name')->values(),
            'stages' => $stages,
            'semesters' => $marks->pluck('courseSection.semester')->filter()->unique('id')->sortByDesc('academic_year')->values(),
            'courses' => $marks->pluck('course')->filter()->unique('id')->sortBy('code')->values(),
        ];
    }

    private function applyQueueFilters($query, array $filters): void
    {
        $query
            ->when($filters['q'] !== '', fn ($markQuery) => $markQuery->where(function ($searchQuery) use ($filters) {
                $searchQuery->whereHas('student', fn ($studentQuery) => $studentQuery
                    ->where('full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('student_id', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery
                        ->where('name', 'like', "%{$filters['q']}%")
                        ->orWhere('code', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection.teacher', fn ($teacherQuery) => $teacherQuery
                        ->where('full_name', 'like', "%{$filters['q']}%"));
            }))
            ->when($filters['college_id'], fn ($markQuery) => $markQuery->where(function ($collegeQuery) use ($filters) {
                $collegeQuery->whereHas('courseSection.course.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']))
                    ->orWhereHas('course.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']))
                    ->orWhereHas('student.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']));
            }))
            ->when($filters['department_id'], fn ($markQuery) => $markQuery->where(function ($departmentQuery) use ($filters) {
                $departmentQuery->whereHas('courseSection.course', fn ($courseQuery) => $courseQuery->where('department_id', $filters['department_id']))
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery->where('department_id', $filters['department_id']))
                    ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('department_id', $filters['department_id']));
            }))
            ->when($filters['stage'] !== '', fn ($markQuery) => $this->applyStageFilter($markQuery, $filters['stage']))
            ->when($filters['academic_year'] !== '', fn ($markQuery) => $markQuery->whereHas('courseSection.semester', fn ($semesterQuery) => $semesterQuery->where('academic_year', $filters['academic_year'])))
            ->when($filters['semester_id'], fn ($markQuery) => $markQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('semester_id', $filters['semester_id'])))
            ->when($filters['course_id'], fn ($markQuery) => $markQuery->where('course_id', $filters['course_id']))
            ->when($filters['teacher_id'], fn ($markQuery) => $markQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('teacher_id', $filters['teacher_id'])))
            ->when($filters['submission_status'], fn ($markQuery) => $markQuery->where('submission_status', $filters['submission_status']));
    }

    private function applyStageFilter($query, string $stage): void
    {
        $alias = $this->stageAlias($stage);

        if ($alias === '__unassigned__') {
            $query->where(fn ($markQuery) => $markQuery
                ->whereNull('course_section_id')
                ->orWhereHas('courseSection', fn ($sectionQuery) => $sectionQuery
                    ->whereNull('grade_level')
                    ->orWhere('grade_level', '')));

            return;
        }

        $gradeLevels = CourseSection::query()
            ->pluck('grade_level')
            ->filter(fn ($gradeLevel) => $this->stageAlias($gradeLevel) === $alias)
            ->unique()
            ->values();

        $query->whereHas('courseSection', fn ($sectionQuery) => $gradeLevels->isEmpty()
            ? $sectionQuery->whereRaw('1 = 0')
            : $sectionQuery->whereIn('grade_level', $gradeLevels));
    }

    private function finalExamMarkQuery(array $filters)
    {
        $filters['submission_status'] = '';

        $query = Mark::with([
            'student.department.college',
            'course.department.college',
            'courseSection.course.department.college',
            'courseSection.semester',
            'courseSection.teacher',
        ])
            ->whereIn('submission_status', ['draft', 'rejected'])
            ->where('visibility_status', 'draft');

        $this->applyQueueFilters($query, $filters);

        return $query->latest();
    }

    private function finalExamCourseCards(array $filters)
    {
        return $this->finalExamMarkQuery($filters)
            ->whereNotNull('prefinal_mark')
            ->get()
            ->groupBy('course_id')
            ->map(function ($courseMarks) {
                $course = $courseMarks->first()->course;
                $sections = $courseMarks->pluck('courseSection')->filter()->unique('id');
                $waitingSecondTrial = $courseMarks->filter(fn (Mark $mark) => ! is_null($mark->first_trial_final_exam)
                    && is_null($mark->second_trial_final_exam)
                    && ((float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam) < 50);

                return [
                    'course' => $course,
                    'marks_count' => $courseMarks->count(),
                    'section_count' => $sections->count(),
                    'waiting_first_trial' => $courseMarks->whereNull('first_trial_final_exam')->count(),
                    'waiting_second_trial' => $waitingSecondTrial->count(),
                    'college' => $course?->department?->college,
                    'department' => $course?->department,
                    'stages' => $sections->pluck('grade_level')->filter()->unique()->sort()->values(),
                    'semesters' => $sections->pluck('semester')->filter()->unique('id')->sortByDesc('academic_year')->values(),
                ];
            })
            ->filter(fn ($card) => $card['course'])
            ->sortBy(fn ($card) => $card['course']->code ?: $card['course']->name)
            ->values();
    }

    private function finalExamStats(array $filters): array
    {
        return [
            'waiting_first_trial' => $this->finalExamMarkQuery($filters)
                ->whereNotNull('prefinal_mark')
                ->whereNull('first_trial_final_exam')
                ->count(),
            'waiting_second_trial' => $this->finalExamMarkQuery($filters)
                ->whereNotNull('prefinal_mark')
                ->whereNotNull('first_trial_final_exam')
                ->whereNull('second_trial_final_exam')
                ->get()
                ->filter(fn (Mark $mark) => ((float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam) < 50)
                ->count(),
            'ready_for_review' => $this->queueMarkQuery($filters)
                ->where('submission_status', 'submitted')
                ->count(),
        ];
    }

    private function emptyQueueFilters(): array
    {
        return [
            'q' => '',
            'academic_year' => '',
            'college_id' => null,
            'department_id' => null,
            'stage' => '',
            'semester_id' => null,
            'course_id' => null,
            'teacher_id' => null,
            'submission_status' => '',
        ];
    }

    private function markCollege(Mark $mark)
    {
        return $mark->courseSection?->course?->department?->college
            ?? $mark->course?->department?->college
            ?? $mark->student?->department?->college;
    }

    private function markDepartment(Mark $mark)
    {
        return $mark->courseSection?->course?->department
            ?? $mark->course?->department
            ?? $mark->student?->department;
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
}
