<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Mark;
use App\Models\MarkScoreHistory;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MarkSubmissionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $canPublish = $this->canPublish($user);
        $canEnterFinalExam = $this->canEnterFinalExam($user);
        $canManagePrefinalWindow = $this->canManagePrefinalWindow($user);
        $filters = $this->queueFilterState($request);
        $filterOptions = $this->queueFilterOptions();

        $prefinalWindowSemesters = $canManagePrefinalWindow
            ? Semester::whereHas('academicYear', fn ($query) => $query->whereIn('status', ['upcoming', 'active']))
                ->orderByDesc('academic_year')
                ->orderBy('name')
                ->get()
            : collect();

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
            'canManagePrefinalWindow',
            'prefinalWindowSemesters',
            'filters',
            'filterOptions',
            'queueStats'
        ));
    }

    public function finalExamEntry(Request $request)
    {
        $user = Auth::user();

        if (! $this->canEnterFinalExam($user)) {
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

        if (! $this->canEnterFinalExam($user)) {
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

        $publishedMarks = Mark::with([
                'student.department.college',
                'course.department.college',
                'courseSection.course.department.college',
                'courseSection.semester',
                'courseSection.teacher',
            ])
            ->where('course_id', $course->id)
            ->where('visibility_status', 'published')
            ->where('submission_status', 'approved')
            ->whereNotNull('prefinal_mark')
            ->latest('published_at')
            ->paginate(20, ['*'], 'published_page')
            ->withQueryString();

        return view('marks.final-exam-course', compact(
            'course',
            'finalExamDrafts',
            'publishedMarks',
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

        if (! $this->canEnterFinalExam($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'mark_id' => 'required|integer|exists:marks,id',
            'trial' => 'required|in:first,second',
            'score' => 'required|numeric|min:0|max:'.config('academics.final_exam_mark_max', 100),
            'change_reason' => 'nullable|string|max:1000',
        ]);

        [$firstTrialFailed, $isPublishedCorrection] = DB::transaction(function () use ($validated, $user) {
            $mark = Mark::with(['student', 'course.department', 'courseSection.course.department', 'courseSection.semester.academicYear'])
                ->whereKey($validated['mark_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $this->service->assertMarkIntegrity($mark);

            if (is_null($mark->prefinal_mark)) {
                throw ValidationException::withMessages(['score' => 'Pre-final mark must be entered by the teacher before final exam scores.']);
            }

            $isPublishedCorrection = $mark->visibility_status === 'published';

            if (! $isPublishedCorrection && ! in_array($mark->submission_status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages(['score' => 'Submitted, under-review, or approved marks must be rejected before their final exam score can be changed.']);
            }

            $scoreField = $validated['trial'] === 'first' ? 'first_trial_final_exam' : 'second_trial_final_exam';
            $previousScore = $mark->{$scoreField};
            $previousStatus = $mark->submission_status;
            if (! is_null($previousScore) && ! $isPublishedCorrection && $previousStatus !== 'rejected') {
                throw ValidationException::withMessages(['score' => ucfirst($validated['trial']).' trial has already been entered. Use the review workflow before correcting it.']);
            }
            if (! is_null($previousScore) && blank($validated['change_reason'] ?? null)) {
                throw ValidationException::withMessages(['change_reason' => 'A reason is required when correcting an existing final exam score.']);
            }
            if ($validated['trial'] === 'second') {
                if (is_null($mark->first_trial_final_exam)) {
                    throw ValidationException::withMessages(['score' => 'Enter first trial final exam before second trial.']);
                }
                if (((float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam) >= (float) config('academics.pass_mark', 50)) {
                    throw ValidationException::withMessages(['score' => 'Second trial is only available for students who failed the first trial.']);
                }
            }

            if ($validated['trial'] === 'first') {
                $mark->first_trial_final_exam = $validated['score'];
                $mark->second_trial_final_exam = null;
            } else {
                $mark->second_trial_final_exam = $validated['score'];
            }

            $mark->recalculateFinalMark();
            $firstTrialFailed = $validated['trial'] === 'first' && (float) $mark->final_mark < (float) config('academics.pass_mark', 50);

            if ($firstTrialFailed) {
                // A corrected first trial that now fails still needs a second trial
                // before it can be published again, regardless of prior publish state.
                $mark->submission_status = 'draft';
                $mark->visibility_status = 'draft';
                $mark->submitted_at = null;
                $mark->reviewer_notes = null;
                $mark->reviewed_by = null;
                $mark->reviewed_at = null;
            } elseif ($isPublishedCorrection) {
                // Corrections to an already-published mark go straight back to
                // published by the same examination-committee member — no separate
                // approver, since the mark was already reviewed once to be published.
                $mark->submission_status = 'approved';
                $mark->visibility_status = 'published';
                $mark->submitted_at = now();
                $mark->reviewer_notes = $validated['change_reason'] ?? null;
                $mark->reviewed_by = $user->id;
                $mark->reviewed_at = now();
                $mark->published_at = now();
            } else {
                $mark->submission_status = 'submitted';
                $mark->visibility_status = 'draft';
                $mark->submitted_at = now();
                $mark->reviewer_notes = null;
                $mark->reviewed_by = null;
                $mark->reviewed_at = null;
            }

            $mark->final_exam_entered_by = $user->id;
            $mark->final_exam_entered_at = now();
            $mark->save();

            MarkScoreHistory::create([
                'mark_id' => $mark->id,
                'actor_id' => $user->id,
                'trial' => $validated['trial'],
                'previous_score' => $previousScore,
                'new_score' => $validated['score'],
                'previous_submission_status' => $previousStatus,
                'reason' => $validated['change_reason'] ?? null,
            ]);

            if ($isPublishedCorrection && ! $firstTrialFailed) {
                ActivityLog::create([
                    'log_name' => 'mark_correction',
                    'description' => 'mark_corrected',
                    'subject_type' => Mark::class,
                    'subject_id' => $mark->id,
                    'causer_type' => User::class,
                    'causer_id' => $user->id,
                    'properties' => [
                        'mark_id' => $mark->id,
                        'student_id' => $mark->student_id,
                        'course_id' => $mark->course_id,
                        'trial' => $validated['trial'],
                        'previous_score' => $previousScore,
                        'new_score' => $validated['score'],
                        'reason' => $validated['change_reason'] ?? null,
                    ],
                ]);
                app(NotificationService::class)->notifyMarkCorrected($mark);
            }

            return [$firstTrialFailed, $isPublishedCorrection];
        });

        $redirectTo = (string) $request->input('redirect_to', '');
        $redirectTo = str_starts_with($redirectTo, route('marks.final-exam.index'))
            ? $redirectTo
            : route('marks.submission-queue');

        $message = match (true) {
            $firstTrialFailed => 'First trial score saved. Student is eligible for second trial.',
            $isPublishedCorrection => 'Published mark corrected and republished.',
            default => 'Final exam score saved and submitted for review.',
        };

        return redirect()
            ->to($redirectTo)
            ->with('success', $message);
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

        if (! $this->canPublish($user)) {
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

    public function togglePrefinalWindow(Request $request, Semester $semester)
    {
        $user = Auth::user();

        if (! $this->canManagePrefinalWindow($user)) {
            abort(403);
        }

        $validated = $request->validate([
            'open' => 'required|boolean',
        ]);

        $semester->update([
            'prefinal_marks_open' => $validated['open'],
            'prefinal_marks_opened_by' => $validated['open'] ? $user->id : null,
            'prefinal_marks_opened_at' => $validated['open'] ? now() : null,
        ]);

        return redirect()
            ->route('marks.submission-queue')
            ->with('success', $validated['open']
                ? "Pre-final mark entry opened for {$semester->name} {$semester->academic_year}."
                : "Pre-final mark entry closed for {$semester->name} {$semester->academic_year}.");
    }

    private function canManagePrefinalWindow($user): bool
    {
        return $user->hasRole('super_administrator')
            || ($user->hasRole('examination_administrator') && $user->hasPermission('marks.manage_prefinal_window'));
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

    private function canPublish($user): bool
    {
        return $user->hasRole('super_administrator')
            || ($user->hasRole('examination_administrator') && $user->hasPermission('marks.publish'));
    }

    private function canEnterFinalExam($user): bool
    {
        return $user->hasRole('super_administrator')
            || ($user->hasRole('examination_committee') && $user->hasPermission('marks.enter_final_exam'));
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
        return [
            'colleges' => College::whereHas('departments.courses.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orWhereHas('departments.courses.sections.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orWhereHas('departments.students.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'departments' => Department::whereHas('courses.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orWhereHas('courses.sections.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orWhereHas('students.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'college_id']),
            'stages' => $this->stageOptionsForMarks(fn ($query) => $this->applyQueueOptionStatus($query)),
            'semesters' => Semester::whereHas('courseSections.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orderByDesc('academic_year')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_year']),
            'courses' => Course::whereHas('marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orWhereHas('sections.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'teachers' => Teacher::whereHas('courseSections.marks', fn ($query) => $this->applyQueueOptionStatus($query))
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
        ];
    }

    private function finalExamFilterOptions(): array
    {
        return [
            'academic_years' => Semester::whereHas('courseSections.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year')
                ->filter()
                ->values(),
            'colleges' => College::whereHas('departments.courses.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orWhereHas('departments.courses.sections.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orWhereHas('departments.students.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'departments' => Department::whereHas('courses.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orWhereHas('courses.sections.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orWhereHas('students.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'college_id']),
            'stages' => $this->stageOptionsForMarks(fn ($query) => $this->applyFinalExamOptionStatus($query)),
            'semesters' => Semester::whereHas('courseSections.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orderByDesc('academic_year')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_year']),
            'courses' => Course::whereHas('marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orWhereHas('sections.marks', fn ($query) => $this->applyFinalExamOptionStatus($query))
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ];
    }

    private function applyQueueOptionStatus($query): void
    {
        $query->where(fn ($statusQuery) => $statusQuery
            ->whereIn('submission_status', ['submitted', 'under_review'])
            ->orWhere(fn ($approvedQuery) => $approvedQuery
                ->where('submission_status', 'approved')
                ->where('visibility_status', 'draft')));
    }

    private function applyFinalExamOptionStatus($query): void
    {
        $query
            ->whereIn('submission_status', ['draft', 'rejected'])
            ->where('visibility_status', 'draft')
            ->whereNotNull('prefinal_mark');
    }

    private function stageOptionsForMarks(callable $markConstraint)
    {
        return CourseSection::whereHas('marks', $markConstraint)
            ->select('grade_level')
            ->distinct()
            ->pluck('grade_level')
            ->map(fn ($gradeLevel) => [
                'alias' => (string) $this->stageAlias($gradeLevel ?: '__unassigned__'),
                'grade_level' => $gradeLevel,
            ])
            ->unique('alias')
            ->map(fn ($stage) => [
                'key' => $stage['alias'],
                'label' => $this->stageLabel($stage['alias'], $stage['grade_level']),
            ])
            ->sortBy(fn ($stage) => $this->stageSortValue($stage['key'], $stage['label']))
            ->values();
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

    private function finalExamMarkQuery(array $filters, bool $withRelations = true)
    {
        $filters['submission_status'] = '';

        $query = Mark::query()
            ->when($withRelations, fn ($query) => $query->with([
                'student.department.college',
                'course.department.college',
                'courseSection.course.department.college',
                'courseSection.semester',
                'courseSection.teacher',
            ]))
            ->whereIn('submission_status', ['draft', 'rejected'])
            ->where('visibility_status', 'draft');

        $this->applyQueueFilters($query, $filters);

        return $query->latest();
    }

    private function finalExamCourseCards(array $filters)
    {
        $rows = $this->finalExamMarkQuery($filters, false)
            ->whereNotNull('prefinal_mark')
            ->select('course_id')
            ->selectRaw('COUNT(*) as marks_count')
            ->selectRaw('COUNT(DISTINCT course_section_id) as section_count')
            ->selectRaw('SUM(CASE WHEN first_trial_final_exam IS NULL THEN 1 ELSE 0 END) as waiting_first_trial')
            ->selectRaw('SUM(CASE WHEN first_trial_final_exam IS NOT NULL AND second_trial_final_exam IS NULL AND (COALESCE(prefinal_mark, 0) + COALESCE(first_trial_final_exam, 0)) < ? THEN 1 ELSE 0 END) as waiting_second_trial', [config('academics.pass_mark', 50)])
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        // Courses with every mark already published (nothing left "waiting") still need
        // to be reachable so a mistake found after publishing can be corrected.
        $publishedQuery = Mark::query()
            ->where('visibility_status', 'published')
            ->where('submission_status', 'approved')
            ->whereNotNull('prefinal_mark');
        $this->applyQueueFilters($publishedQuery, array_merge($filters, ['submission_status' => '']));
        $publishedRows = $publishedQuery
            ->select('course_id')
            ->selectRaw('COUNT(*) as published_count')
            ->selectRaw('COUNT(DISTINCT course_section_id) as published_section_count')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $courseIds = $rows->keys()->merge($publishedRows->keys())->unique()->filter()->values();
        $courses = Course::with('department.college')
            ->whereIn('id', $courseIds)
            ->get()
            ->keyBy('id');
        $sectionsByCourse = CourseSection::with('semester')
            ->whereIn('course_id', $courseIds)
            ->get()
            ->groupBy('course_id');

        return $courseIds
            ->map(function ($courseId) use ($rows, $publishedRows, $courses, $sectionsByCourse) {
                $row = $rows->get($courseId);
                $publishedRow = $publishedRows->get($courseId);
                $course = $courses->get($courseId);
                $sections = $sectionsByCourse->get($courseId, collect());

                return [
                    'course' => $course,
                    'marks_count' => (int) ($row->marks_count ?? 0),
                    'section_count' => (int) ($row->section_count ?? $publishedRow->published_section_count ?? 0),
                    'waiting_first_trial' => (int) ($row->waiting_first_trial ?? 0),
                    'waiting_second_trial' => (int) ($row->waiting_second_trial ?? 0),
                    'published_count' => (int) ($publishedRow->published_count ?? 0),
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
                ->whereRaw('(COALESCE(prefinal_mark, 0) + COALESCE(first_trial_final_exam, 0)) < ?', [config('academics.pass_mark', 50)])
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
