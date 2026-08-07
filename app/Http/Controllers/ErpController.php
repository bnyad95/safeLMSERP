<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\SemesterCreditPolicy;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\OrganizationScope;
use App\Support\PermissionRiskPolicy;
use App\Support\RoleAccessPolicy;
use App\Support\UserRolePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ErpController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $portalRoles = ['student', 'parent_user', 'librarian', 'receptionist'];
        $hasNonPortalRole = $user?->roles()->whereNotIn('name', $portalRoles)->exists() ?? false;
        $teacherBlockingRoles = array_values(array_diff(UserRolePolicy::HIGH_RISK_ROLES, ['teaching_assistant']));
        $hasTeacherBlockingRole = $user?->hasAnyRole($teacherBlockingRoles) ?? false;

        if ($user?->hasRole('student') && ! $hasNonPortalRole) {
            return redirect()->route('student-portal');
        }

        if ($user?->hasRole('parent_user') && ! $hasNonPortalRole) {
            return redirect()->route('parent.workspace');
        }

        if ($user?->hasRole('librarian') && ! $hasNonPortalRole) {
            return redirect()->route('library.workspace');
        }

        if ($user?->hasRole('receptionist') && ! $hasNonPortalRole) {
            return redirect()->route('reception.workspace');
        }

        if ($user?->hasRole('university_president') && ! $user->hasRole('super_administrator')) {
            return redirect()->route('analytics.index');
        }

        if ($user?->hasRole('teacher') && ! $hasTeacherBlockingRole) {
            return $this->teacherHomeDashboard($user);
        }

        if ($user?->hasAnyRole(['examination_administrator', 'examination_committee']) && ! $user->hasRole('super_administrator')) {
            return $this->examinationDashboard($user);
        }

        if ($user?->hasAnyRole(['chief_accountant', 'accountant']) && ! $user->hasRole('super_administrator')) {
            return redirect()->route('finance.dashboard');
        }

        $studentsCount = Student::count();
        $activeStudentsCount = Student::where('status', 'Active')->count();
        $teachersCount = Teacher::count();
        $activeTeachersCount = Teacher::where('status', 'Active')->count();
        $coursesCount = Course::count();
        $submittedMarksCount = Mark::where('submission_status', 'submitted')->count();
        $publishedMarksCount = Mark::where('visibility_status', 'published')->count();
        $draftMaterialsCount = CourseMaterial::where('visibility', 'draft')->count();
        $todayAttendanceCount = Attendance::whereDate('date', today())->count();

        $stats = [
            ['label' => 'Students', 'value' => number_format($studentsCount), 'detail' => number_format($activeStudentsCount).' active records', 'tone' => 'blue'],
            ['label' => 'Teachers', 'value' => number_format($teachersCount), 'detail' => number_format($activeTeachersCount).' active staff', 'tone' => 'emerald'],
            ['label' => 'Courses', 'value' => number_format($coursesCount), 'detail' => number_format(Department::count()).' departments mapped', 'tone' => 'indigo'],
            ['label' => 'Marks Queue', 'value' => number_format($submittedMarksCount), 'detail' => number_format($publishedMarksCount).' published results', 'tone' => 'amber'],
        ];

        $reviewItems = [
            ['label' => 'Submitted marks', 'value' => number_format($submittedMarksCount), 'hint' => 'Need review before publishing'],
            ['label' => 'Draft materials', 'value' => number_format($draftMaterialsCount), 'hint' => 'Waiting for teacher publication'],
            ['label' => 'Attendance entries today', 'value' => number_format($todayAttendanceCount), 'hint' => 'Recorded across active courses'],
        ];

        $structure = [
            ['label' => 'Universities', 'value' => number_format(University::count())],
            ['label' => 'Colleges', 'value' => number_format(College::count())],
            ['label' => 'Departments', 'value' => number_format(Department::count())],
            ['label' => 'Semesters', 'value' => number_format(Semester::count())],
        ];

        return view('dashboard', compact('stats', 'reviewItems', 'structure'));
    }

    private function examinationDashboard(User $user)
    {
        $canPublish = $user->hasPermission('marks.publish');
        $submitted = Mark::where('submission_status', 'submitted')->count();
        $underReview = Mark::where('submission_status', 'under_review')->count();
        $approvedDraft = Mark::where('submission_status', 'approved')->where('visibility_status', 'draft')->count();
        $rejected = Mark::where('submission_status', 'rejected')->count();
        $published = Mark::where('visibility_status', 'published')->count();
        $finalExamEntry = Mark::whereIn('submission_status', ['draft', 'rejected'])
            ->where('visibility_status', 'draft')
            ->whereNotNull('prefinal_mark')
            ->count();

        $stats = [
            ['label' => 'Submitted', 'value' => number_format($submitted), 'detail' => 'Waiting for committee review', 'tone' => 'blue'],
            ['label' => 'Under Review', 'value' => number_format($underReview), 'detail' => 'Being checked by committee', 'tone' => 'amber'],
            ['label' => 'Approved', 'value' => number_format($approvedDraft), 'detail' => $canPublish ? 'Ready for publication' : 'Waiting for examination administrator', 'tone' => 'emerald'],
            ['label' => 'Published', 'value' => number_format($published), 'detail' => 'Visible to students', 'tone' => 'indigo'],
        ];

        $reviewItems = [
            ['label' => 'Final exam entry', 'value' => number_format($finalExamEntry), 'hint' => 'Enter first-trial and eligible second-trial scores', 'href' => route('marks.final-exam.index')],
            ['label' => 'Approve or request changes', 'value' => number_format($submitted + $underReview), 'hint' => 'Open the Mark Queue to review submitted marks', 'href' => route('marks.submission-queue')],
            ['label' => $canPublish ? 'Ready to publish' : 'Awaiting publication', 'value' => number_format($approvedDraft), 'hint' => $canPublish ? 'Approved marks can be published to students' : 'Publication is handled by the examination administrator', 'href' => $canPublish ? route('marks.submission-queue', ['submission_status' => 'approved']) : route('exams', ['submission_status' => 'approved', 'visibility_status' => 'draft'])],
            ['label' => 'Rejected marks', 'value' => number_format($rejected), 'hint' => 'Returned to teachers for correction', 'href' => route('exams', ['submission_status' => 'rejected'])],
        ];

        $recentMarks = Mark::with(['student', 'course', 'courseSection.teacher'])
            ->whereIn('submission_status', ['submitted', 'under_review', 'approved', 'rejected'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('erp.examination-dashboard', compact('stats', 'reviewItems', 'recentMarks', 'canPublish'));
    }

    private function teacherHomeDashboard(User $user)
    {
        $teacher = Teacher::with('department')->where('email', $user->email)->first();
        $dashboardDate = now('Asia/Baghdad');

        $assignedSections = CourseSection::with(['course.department', 'semester'])
            ->withCount(['activeEnrollments as students_count', 'assessmentItems as assessments_count'])
            ->when($teacher, fn ($query) => $query->where('teacher_id', $teacher->id))
            ->when(! $teacher, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        $sectionIds = $assignedSections->pluck('id');
        $studentIds = Student::whereHas('enrollments', fn ($query) => $query
            ->where('status', 'enrolled')
            ->whereIn('course_section_id', $sectionIds))
            ->pluck('id');

        $todayTimetable = Timetable::with(['course', 'courseSection', 'classroom', 'timeSlot'])
            ->whereIn('course_section_id', $sectionIds)
            ->where('day_of_week', $dashboardDate->format('l'))
            ->where('status', 'scheduled')
            ->orderBy('start_time')
            ->get();

        $upcomingAssessments = AssessmentItem::with('courseSection.course')
            ->withCount('submissions')
            ->whereIn('course_section_id', $sectionIds)
            ->where('status', 'published')
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        $pendingSubmissions = AssessmentSubmission::with(['assessmentItem.courseSection.course', 'student'])
            ->whereHas('assessmentItem', fn ($query) => $query->whereIn('course_section_id', $sectionIds))
            ->where('status', 'submitted')
            ->whereNull('graded_at')
            ->latest('submitted_at')
            ->limit(6)
            ->get();

        $unreadMessages = ClassMessage::with(['sender', 'courseSection.course'])
            ->whereIn('course_section_id', $sectionIds)
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->latest()
            ->limit(5)
            ->get();

        $attendanceRecords = Attendance::whereIn('course_section_id', $sectionIds)
            ->whereIn('student_id', $studentIds)
            ->whereDate('date', '>=', now()->subDays(30)->toDateString())
            ->get();
        $students = Student::whereIn('id', $studentIds)->orderBy('full_name')->get();
        $attendanceRisks = $students->map(function (Student $student) use ($attendanceRecords) {
            $records = $attendanceRecords->where('student_id', $student->id);
            $total = $records->count();
            $attended = $records->whereIn('status', ['present', 'late', 'excused'])->count();
            $rate = $total > 0 ? round(($attended / $total) * 100, 1) : null;

            return [
                'student' => $student,
                'section_id' => $records->first()?->course_section_id,
                'rate' => $rate,
                'absences' => $records->where('status', 'absent')->count(),
            ];
        })->filter(fn ($risk) => $risk['rate'] !== null && $risk['rate'] < 85)
            ->sortBy('rate')
            ->take(5)
            ->values();

        $stats = [
            'classes' => $assignedSections->count(),
            'students' => $studentIds->count(),
            'pending_submissions' => AssessmentSubmission::whereHas('assessmentItem', fn ($query) => $query->whereIn('course_section_id', $sectionIds))
                ->where('status', 'submitted')
                ->whereNull('graded_at')
                ->count(),
            'unread_messages' => ClassMessage::whereIn('course_section_id', $sectionIds)
                ->where('recipient_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ];

        return view('teacher.home-dashboard', compact(
            'teacher',
            'dashboardDate',
            'todayTimetable',
            'upcomingAssessments',
            'pendingSubmissions',
            'unreadMessages',
            'attendanceRisks',
            'stats'
        ));
    }

    public function students()
    {
        $students = Student::with('department')->latest()->take(8)->get();

        return $this->modulePage('Student Management', 'Track admissions, registrations, and student academic status.', [
            ['label' => 'Active students', 'value' => number_format(Student::where('status', 'Active')->count())],
            ['label' => 'New admissions', 'value' => number_format(Student::count())],
            ['label' => 'Graduated', 'value' => '0'],
        ], $students->map(function ($student) {
            return [
                'title' => $student->full_name,
                'meta' => $student->student_id.' • '.($student->department->name ?? 'No department').' • '.$student->status,
            ];
        })->all());
    }

    public function teachers()
    {
        $teachers = Teacher::with('department')->latest()->take(8)->get();

        return $this->modulePage('Teacher & Staff Management', 'Coordinate teacher profiles, teaching loads, and leave requests.', [
            ['label' => 'Active teachers', 'value' => number_format(Teacher::where('status', 'Active')->count())],
            ['label' => 'Pending leave', 'value' => '0'],
            ['label' => 'Courses assigned', 'value' => number_format(Course::count())],
        ], $teachers->map(function ($teacher) {
            return [
                'title' => $teacher->full_name,
                'meta' => $teacher->title.' • '.($teacher->department->name ?? 'No department'),
            ];
        })->all());
    }

    public function exams(Request $request)
    {
        $this->requireAnyPermission('marks.view', 'marks.review', 'marks.approve', 'marks.publish');

        $examUser = auth()->user();
        $canOpenMarkQueue = $examUser?->hasAnyRole([
            'super_administrator',
            'examination_administrator',
            'examination_committee',
        ]) && ($examUser->hasRole('super_administrator')
            || $examUser->hasAnyPermission(['marks.review', 'marks.approve', 'marks.publish']));
        $canViewStudents = $examUser?->hasRole('super_administrator') || $examUser?->hasPermission('students.view');
        $canViewCourses = $examUser?->hasRole('super_administrator') || $examUser?->hasPermission('courses.view');
        $filters = $this->resultFilterState($request);
        $filterOptions = $this->resultFilterOptions();
        $hierarchy = $this->buildResultsHierarchy($filters);
        $missingResultsCount = $this->missingResultCount($filters);
        $stats = $this->resultStats($filters, $missingResultsCount);
        $recentMarks = $this->resultRows(
            $this->sortResultMarksQuery($this->resultsMarkQuery($filters), $filters['sort'])->limit(12)->get(),
            $canOpenMarkQueue,
            $canViewStudents,
            $canViewCourses
        );
        $statusBreakdown = $this->resultStatusBreakdown($filters);
        $coursePerformance = $this->resultCoursePerformance($filters);
        $departmentRisk = $this->resultDepartmentRisk($filters);

        return view('erp.results-overview', compact(
            'filters',
            'filterOptions',
            'hierarchy',
            'stats',
            'recentMarks',
            'statusBreakdown',
            'coursePerformance',
            'departmentRisk',
            'canOpenMarkQueue'
        ));

    }

    public function exportResults(Request $request)
    {
        $this->requireAnyPermission('marks.view', 'marks.review', 'marks.approve', 'marks.publish');

        $filters = $this->resultFilterState($request);
        $marks = $this->sortResultMarksQuery($this->resultsMarkQuery($filters), $filters['sort']);
        $filename = 'results-overview-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($marks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Student ID',
                'Student',
                'College',
                'Department',
                'Stage',
                'Class',
                'Course',
                'Semester',
                'Teacher',
                'Prefinal',
                'Final exam',
                'Final mark',
                'Result',
                'Submission status',
                'Visibility status',
                'Submitted at',
                'Reviewed at',
                'Published at',
            ]);

            foreach ($marks->cursor() as $mark) {
                $section = $mark->courseSection;
                $course = $mark->course;
                fputcsv($handle, [
                    $mark->student?->student_id,
                    $mark->student?->full_name,
                    $this->resultCollege($mark)?->name,
                    $this->resultDepartment($mark)?->name,
                    $section?->grade_level ?: 'Stage not specified',
                    $section?->section_code,
                    trim(($course?->code ? $course->code.' - ' : '').($course?->name ?? '')),
                    $section?->semester ? trim($section->semester->name.' '.$section->semester->academic_year) : '',
                    $section?->teacher?->full_name,
                    $mark->prefinal_mark,
                    $mark->final_exam,
                    $mark->final_mark,
                    $this->resultOutcome($mark),
                    $mark->submission_status,
                    $mark->visibility_status,
                    $mark->submitted_at?->format('Y-m-d H:i'),
                    $mark->reviewed_at?->format('Y-m-d H:i'),
                    $mark->published_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resultsMarkCollection()
    {
        return Mark::with([
            'student.department.college',
            'course.department.college',
            'courseSection.course.department.college',
            'courseSection.semester',
            'courseSection.teacher',
            'reviewer',
        ])
            ->latest()
            ->get();
    }

    private function resultsMarkQuery(array $filters = [], bool $withRelations = true)
    {
        $query = Mark::query();

        if ($withRelations) {
            $query->with([
                'student.department.college',
                'course.department.college',
                'courseSection.course.department.college',
                'courseSection.semester',
                'courseSection.teacher',
                'reviewer',
            ]);
        }

        if ($filters !== []) {
            $this->applyResultQueryFilters($query, $filters);
        }

        return $query;
    }

    private function sortResultMarksQuery($query, string $sort)
    {
        return match ($sort) {
            'final_desc' => $query->orderByDesc('final_mark')->orderByDesc('updated_at')->orderByDesc('id'),
            'final_asc' => $query->orderBy('final_mark')->orderByDesc('updated_at')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    private function resultFilterState(Request $request): array
    {
        $submissionStatus = in_array($request->query('submission_status'), ['draft', 'submitted', 'under_review', 'approved', 'rejected'], true)
            ? $request->query('submission_status')
            : '';
        $visibilityStatus = in_array($request->query('visibility_status'), ['draft', 'published'], true)
            ? $request->query('visibility_status')
            : '';
        $resultStatus = in_array($request->query('result_status'), ['passed', 'failed'], true)
            ? $request->query('result_status')
            : '';
        $sort = in_array($request->query('sort'), ['recent', 'final_desc', 'final_asc'], true)
            ? $request->query('sort')
            : 'recent';

        return [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage' => trim((string) ($request->query('stage') ?? $request->query('grade', ''))),
            'semester_id' => $request->integer('semester_id') ?: null,
            'section_id' => $request->integer('section_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'submission_status' => $submissionStatus,
            'visibility_status' => $visibilityStatus,
            'result_status' => $resultStatus,
            'sort' => $sort,
        ];
    }

    private function applyResultFilters($marks, array $filters)
    {
        return $marks->filter(function (Mark $mark) use ($filters) {
            if ($filters['college_id'] && (int) ($this->resultCollege($mark)?->id) !== (int) $filters['college_id']) {
                return false;
            }

            if ($filters['department_id'] && (int) ($this->resultDepartment($mark)?->id) !== (int) $filters['department_id']) {
                return false;
            }

            if ($filters['stage'] !== '' && $this->stageAlias($mark->courseSection?->grade_level ?: '__unassigned__') !== $this->stageAlias($filters['stage'])) {
                return false;
            }

            if ($filters['semester_id'] && (int) $mark->courseSection?->semester_id !== (int) $filters['semester_id']) {
                return false;
            }

            if ($filters['section_id'] && (int) $mark->course_section_id !== (int) $filters['section_id']) {
                return false;
            }

            if ($filters['course_id'] && (int) $mark->course_id !== (int) $filters['course_id']) {
                return false;
            }

            if ($filters['teacher_id'] && (int) $mark->courseSection?->teacher_id !== (int) $filters['teacher_id']) {
                return false;
            }

            if ($filters['submission_status'] !== '' && $mark->submission_status !== $filters['submission_status']) {
                return false;
            }

            if ($filters['visibility_status'] !== '' && $mark->visibility_status !== $filters['visibility_status']) {
                return false;
            }

            if ($filters['result_status'] === 'passed' && ! $this->resultPassed($mark)) {
                return false;
            }

            if ($filters['result_status'] === 'failed' && ! $this->resultFailed($mark)) {
                return false;
            }

            if ($filters['q'] !== '') {
                $needle = str($filters['q'])->lower()->toString();
                $haystack = str(collect([
                    $mark->student?->full_name,
                    $mark->student?->student_id,
                    $mark->student?->email,
                    $mark->course?->code,
                    $mark->course?->name,
                    $mark->courseSection?->section_code,
                    $mark->courseSection?->teacher?->full_name,
                ])->filter()->implode(' '))->lower()->toString();

                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function sortResultMarks($marks, string $sort)
    {
        return match ($sort) {
            'final_desc' => $marks->sortByDesc(fn (Mark $mark) => (float) $mark->final_mark)->values(),
            'final_asc' => $marks->sortBy(fn (Mark $mark) => (float) $mark->final_mark)->values(),
            default => $marks->sortByDesc(fn (Mark $mark) => $mark->updated_at?->timestamp ?? 0)->values(),
        };
    }

    private function applyResultQueryFilters($query, array $filters): void
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
                    ->orWhereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('section_code', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection.teacher', fn ($teacherQuery) => $teacherQuery->where('full_name', 'like', "%{$filters['q']}%"));
            }))
            ->when($filters['college_id'], fn ($markQuery) => $markQuery->where(function ($collegeQuery) use ($filters) {
                $collegeQuery->whereHas('courseSection.course.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']))
                    ->orWhereHas('course.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']))
                    ->orWhereHas('student.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']));
            }))
            ->when($filters['department_id'], fn ($markQuery) => $markQuery->where(function ($departmentQuery) use ($filters) {
                $departmentQuery->whereHas('courseSection.course', fn ($courseQuery) => $courseQuery->where('courses.department_id', $filters['department_id']))
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery->where('courses.department_id', $filters['department_id']))
                    ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('students.department_id', $filters['department_id']));
            }))
            ->when($filters['stage'] !== '', fn ($markQuery) => $this->applyResultStageQueryFilter($markQuery, $filters['stage']))
            ->when($filters['semester_id'], fn ($markQuery) => $markQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('semester_id', $filters['semester_id'])))
            ->when($filters['section_id'], fn ($markQuery) => $markQuery->where('marks.course_section_id', $filters['section_id']))
            ->when($filters['course_id'], fn ($markQuery) => $markQuery->where('marks.course_id', $filters['course_id']))
            ->when($filters['teacher_id'], fn ($markQuery) => $markQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('teacher_id', $filters['teacher_id'])))
            ->when($filters['submission_status'] !== '', fn ($markQuery) => $markQuery->where('submission_status', $filters['submission_status']))
            ->when($filters['visibility_status'] !== '', fn ($markQuery) => $markQuery->where('visibility_status', $filters['visibility_status']))
            ->when($filters['result_status'] === 'passed', fn ($markQuery) => $markQuery->whereNotNull('final_mark')->where('final_mark', '>=', 50))
            ->when($filters['result_status'] === 'failed', fn ($markQuery) => $markQuery->whereNotNull('final_mark')->where('final_mark', '<', 50));
    }

    private function applyResultStageQueryFilter($query, string $stage): void
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
            ->whereNotNull('grade_level')
            ->distinct()
            ->pluck('grade_level')
            ->filter(fn ($gradeLevel) => $this->stageAlias($gradeLevel) === $alias)
            ->unique()
            ->values();

        $query->whereHas('courseSection', fn ($sectionQuery) => $gradeLevels->isEmpty()
            ? $sectionQuery->whereRaw('1 = 0')
            : $sectionQuery->whereIn('grade_level', $gradeLevels));
    }

    private function missingResultCount(array $filters): int
    {
        if ($filters['submission_status'] !== '' || $filters['visibility_status'] !== '' || $filters['result_status'] !== '') {
            return 0;
        }

        $query = Enrollment::query()
            ->whereIn('status', ['enrolled', 'completed']);
        $this->applyEnrollmentResultQueryFilters($query, $filters);

        return $query
            ->whereNotExists(function ($subQuery) {
                $subQuery->selectRaw('1')
                    ->from('marks')
                    ->whereColumn('marks.student_id', 'enrollments.student_id')
                    ->whereColumn('marks.course_section_id', 'enrollments.course_section_id');
            })
            ->count();
    }

    private function applyEnrollmentResultQueryFilters($query, array $filters): void
    {
        $query
            ->when($filters['q'] !== '', fn ($enrollmentQuery) => $enrollmentQuery->where(function ($searchQuery) use ($filters) {
                $searchQuery->whereHas('student', fn ($studentQuery) => $studentQuery
                    ->where('full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('student_id', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection.course', fn ($courseQuery) => $courseQuery
                        ->where('name', 'like', "%{$filters['q']}%")
                        ->orWhere('code', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('section_code', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection.teacher', fn ($teacherQuery) => $teacherQuery->where('full_name', 'like', "%{$filters['q']}%"));
            }))
            ->when($filters['college_id'], fn ($enrollmentQuery) => $enrollmentQuery->where(function ($collegeQuery) use ($filters) {
                $collegeQuery->whereHas('courseSection.course.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']))
                    ->orWhereHas('student.department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id']));
            }))
            ->when($filters['department_id'], fn ($enrollmentQuery) => $enrollmentQuery->where(function ($departmentQuery) use ($filters) {
                $departmentQuery->whereHas('courseSection.course', fn ($courseQuery) => $courseQuery->where('department_id', $filters['department_id']))
                    ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('department_id', $filters['department_id']));
            }))
            ->when($filters['stage'] !== '', fn ($enrollmentQuery) => $this->applyEnrollmentStageQueryFilter($enrollmentQuery, $filters['stage']))
            ->when($filters['semester_id'], fn ($enrollmentQuery) => $enrollmentQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('semester_id', $filters['semester_id'])))
            ->when($filters['section_id'], fn ($enrollmentQuery) => $enrollmentQuery->where('course_section_id', $filters['section_id']))
            ->when($filters['course_id'], fn ($enrollmentQuery) => $enrollmentQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('course_id', $filters['course_id'])))
            ->when($filters['teacher_id'], fn ($enrollmentQuery) => $enrollmentQuery->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('teacher_id', $filters['teacher_id'])));
    }

    private function applyEnrollmentStageQueryFilter($query, string $stage): void
    {
        $alias = $this->stageAlias($stage);

        if ($alias === '__unassigned__') {
            $query->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery
                ->whereNull('grade_level')
                ->orWhere('grade_level', ''));

            return;
        }

        $gradeLevels = CourseSection::query()
            ->whereNotNull('grade_level')
            ->distinct()
            ->pluck('grade_level')
            ->filter(fn ($gradeLevel) => $this->stageAlias($gradeLevel) === $alias)
            ->unique()
            ->values();

        $query->whereHas('courseSection', fn ($sectionQuery) => $gradeLevels->isEmpty()
            ? $sectionQuery->whereRaw('1 = 0')
            : $sectionQuery->whereIn('grade_level', $gradeLevels));
    }

    private function enrollmentMatchesResultFilters(Enrollment $enrollment, array $filters): bool
    {
        $section = $enrollment->courseSection;
        $course = $section?->course;

        if (! $section || ! $course) {
            return false;
        }

        if ($filters['college_id'] && (int) ($this->enrollmentCollege($enrollment)?->id) !== (int) $filters['college_id']) {
            return false;
        }

        if ($filters['department_id'] && (int) ($this->enrollmentDepartment($enrollment)?->id) !== (int) $filters['department_id']) {
            return false;
        }

        if ($filters['stage'] !== '' && $this->stageAlias($section->grade_level ?: '__unassigned__') !== $this->stageAlias($filters['stage'])) {
            return false;
        }

        if ($filters['semester_id'] && (int) $section->semester_id !== (int) $filters['semester_id']) {
            return false;
        }

        if ($filters['section_id'] && (int) $section->id !== (int) $filters['section_id']) {
            return false;
        }

        if ($filters['course_id'] && (int) $course->id !== (int) $filters['course_id']) {
            return false;
        }

        if ($filters['teacher_id'] && (int) $section->teacher_id !== (int) $filters['teacher_id']) {
            return false;
        }

        if ($filters['q'] !== '') {
            $needle = str($filters['q'])->lower()->toString();
            $haystack = str(collect([
                $enrollment->student?->full_name,
                $enrollment->student?->student_id,
                $enrollment->student?->email,
                $course->code,
                $course->name,
                $section->section_code,
                $section->teacher?->full_name,
            ])->filter()->implode(' '))->lower()->toString();

            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function resultFilterOptions(): array
    {
        $stages = CourseSection::whereHas('marks')
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
            ->when(
                Mark::whereNull('course_section_id')->exists(),
                fn ($stages) => $stages->push(['key' => '__unassigned__', 'label' => $this->stageLabel('__unassigned__', null)])
            )
            ->sortBy(fn ($stage) => $this->stageSortValue($stage['key'], $stage['label']))
            ->values();

        return [
            'colleges' => College::whereHas('departments.courses.marks')
                ->orWhereHas('departments.courses.sections.marks')
                ->orWhereHas('departments.students.marks')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'departments' => Department::whereHas('courses.marks')
                ->orWhereHas('courses.sections.marks')
                ->orWhereHas('students.marks')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'college_id']),
            'stages' => $stages,
            'semesters' => Semester::whereHas('courseSections.marks')
                ->orderByDesc('academic_year')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_year']),
            'courses' => Course::whereHas('marks')
                ->orWhereHas('sections.marks')
                ->orderBy('code')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'teachers' => Teacher::whereHas('courseSections.marks')
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
        ];
    }

    private function buildResultsHierarchy(array $filters): array
    {
        $baseParams = collect([
            'q' => $filters['q'],
            'course_id' => $filters['course_id'],
            'teacher_id' => $filters['teacher_id'],
            'submission_status' => $filters['submission_status'],
            'visibility_status' => $filters['visibility_status'],
            'result_status' => $filters['result_status'],
            'sort' => $filters['sort'] === 'recent' ? null : $filters['sort'],
        ])->filter(fn ($value) => ! blank($value))->all();
        $url = fn (array $extra = []) => route('exams', array_filter(array_merge($baseParams, $extra), fn ($value) => ! blank($value)));
        $selectedCollege = $filters['college_id'] ? College::find($filters['college_id']) : null;
        $selectedDepartment = $filters['department_id'] ? Department::find($filters['department_id']) : null;
        $selectedStageLabel = $filters['stage'] !== '' ? $this->stageLabel($this->stageAlias($filters['stage']), $filters['stage']) : null;
        $selectedSemester = $filters['semester_id'] ? Semester::find($filters['semester_id']) : null;
        $selectedSection = $filters['section_id'] ? CourseSection::with('course')->find($filters['section_id']) : null;

        $level = match (true) {
            ! $filters['college_id'] => 'colleges',
            ! $filters['department_id'] => 'departments',
            $filters['stage'] === '' => 'stages',
            ! $filters['semester_id'] => 'semesters',
            default => 'classes',
        };

        $cards = $this->resultHierarchyRows($filters, $level)
            ->map(fn ($row) => $this->resultHierarchyCardFromRow($row, $level, $filters, $url))
            ->values();

        $breadcrumbs = collect([
            ['label' => 'Results Overview', 'href' => $url()],
            $selectedCollege ? ['label' => $selectedCollege->name, 'href' => $url(['college_id' => $selectedCollege->id])] : null,
            $selectedDepartment ? ['label' => $selectedDepartment->name, 'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $selectedDepartment->id])] : null,
            $selectedStageLabel ? ['label' => $selectedStageLabel, 'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $filters['stage']])] : null,
            $selectedSemester ? ['label' => trim($selectedSemester->name.' '.$selectedSemester->academic_year), 'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $filters['stage'], 'semester_id' => $selectedSemester->id])] : null,
            $selectedSection ? ['label' => ($selectedSection->course?->code ?? 'Course').' Group '.$selectedSection->section_code, 'href' => null] : null,
        ])->filter()->values();

        return compact('level', 'cards', 'breadcrumbs');
    }

    private function resultHierarchyRows(array $filters, string $level)
    {
        $query = $this->resultsMarkQuery($filters, false);
        $this->joinResultDimensions($query);

        $query
            ->selectRaw('COUNT(*) as marks_count')
            ->selectRaw("SUM(CASE WHEN marks.submission_status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' THEN 1 ELSE 0 END) as published_count")
            ->selectRaw("AVG(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark IS NOT NULL THEN marks.final_mark END) as average_mark");

        match ($level) {
            'colleges' => $query
                ->selectRaw('COALESCE(section_colleges.id, direct_colleges.id, student_colleges.id, 0) as card_id')
                ->selectRaw("COALESCE(section_colleges.name, direct_colleges.name, student_colleges.name, 'College not specified') as title")
                ->selectRaw("COALESCE(section_colleges.code, direct_colleges.code, student_colleges.code, 'No code') as meta")
                ->groupByRaw("COALESCE(section_colleges.id, direct_colleges.id, student_colleges.id, 0), COALESCE(section_colleges.name, direct_colleges.name, student_colleges.name, 'College not specified'), COALESCE(section_colleges.code, direct_colleges.code, student_colleges.code, 'No code')"),
            'departments' => $query
                ->selectRaw('COALESCE(section_departments.id, direct_departments.id, student_departments.id, 0) as card_id')
                ->selectRaw("COALESCE(section_departments.name, direct_departments.name, student_departments.name, 'Department not specified') as title")
                ->selectRaw("COALESCE(section_departments.code, direct_departments.code, student_departments.code, 'No code') as meta")
                ->groupByRaw("COALESCE(section_departments.id, direct_departments.id, student_departments.id, 0), COALESCE(section_departments.name, direct_departments.name, student_departments.name, 'Department not specified'), COALESCE(section_departments.code, direct_departments.code, student_departments.code, 'No code')"),
            'stages' => $query
                ->selectRaw("COALESCE(result_sections.grade_level, '__unassigned__') as card_id")
                ->selectRaw("COALESCE(result_sections.grade_level, 'Stage not specified') as title")
                ->selectRaw('COUNT(DISTINCT result_sections.semester_id) as semester_count')
                ->groupByRaw("COALESCE(result_sections.grade_level, '__unassigned__'), COALESCE(result_sections.grade_level, 'Stage not specified')"),
            'semesters' => $query
                ->selectRaw('COALESCE(result_semesters.id, 0) as card_id')
                ->selectRaw("COALESCE(result_semesters.name, 'Semester not specified') as title")
                ->selectRaw("COALESCE(result_semesters.academic_year, 'No academic year') as meta")
                ->groupByRaw("COALESCE(result_semesters.id, 0), COALESCE(result_semesters.name, 'Semester not specified'), COALESCE(result_semesters.academic_year, 'No academic year')"),
            default => $query
                ->selectRaw('COALESCE(result_sections.id, 0) as card_id')
                ->selectRaw("COALESCE(section_courses.name, direct_courses.name, 'Course not specified') as title")
                ->selectRaw("COALESCE(section_courses.code, direct_courses.code, 'No code') as meta")
                ->selectRaw('result_sections.section_code as section_code')
                ->selectRaw('result_teachers.full_name as teacher_name')
                ->groupByRaw("COALESCE(result_sections.id, 0), COALESCE(section_courses.name, direct_courses.name, 'Course not specified'), COALESCE(section_courses.code, direct_courses.code, 'No code'), result_sections.section_code, result_teachers.full_name"),
        };

        return $query
            ->orderBy('title')
            ->limit(120)
            ->get();
    }

    private function resultHierarchyCardFromRow($row, string $level, array $filters, callable $url): array
    {
        $cardId = $row->card_id;
        $href = match ($level) {
            'colleges' => $cardId ? $url(['college_id' => $cardId]) : null,
            'departments' => $cardId ? $url(['college_id' => $filters['college_id'], 'department_id' => $cardId]) : null,
            'stages' => $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $this->stageAlias((string) $cardId)]),
            'semesters' => $cardId ? $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $filters['stage'], 'semester_id' => $cardId]) : null,
            default => $cardId ? $url([
                'college_id' => $filters['college_id'],
                'department_id' => $filters['department_id'],
                'stage' => $filters['stage'],
                'semester_id' => $filters['semester_id'],
                'section_id' => $cardId,
            ]) : null,
        };

        return [
            'title' => match ($level) {
                'stages' => $this->stageLabel($this->stageAlias((string) $cardId), $row->title),
                'semesters' => trim($row->title.' '.$row->meta),
                'classes' => $row->section_code ? $row->title.' / Group '.$row->section_code : $row->title,
                default => $row->title,
            },
            'meta' => match ($level) {
                'stages' => ((int) $row->semester_count).' semesters',
                'classes' => $row->meta.' / '.($row->teacher_name ?: 'No teacher assigned'),
                default => $row->meta,
            },
            'href' => $href,
            'marks' => (int) $row->marks_count,
            'pending' => (int) $row->pending_count,
            'published' => (int) $row->published_count,
            'average' => is_null($row->average_mark) ? null : round((float) $row->average_mark, 1),
        ];
    }

    private function joinResultDimensions($query): void
    {
        $query
            ->leftJoin('students as result_students', 'marks.student_id', '=', 'result_students.id')
            ->leftJoin('departments as student_departments', 'result_students.department_id', '=', 'student_departments.id')
            ->leftJoin('colleges as student_colleges', 'student_departments.college_id', '=', 'student_colleges.id')
            ->leftJoin('courses as direct_courses', 'marks.course_id', '=', 'direct_courses.id')
            ->leftJoin('departments as direct_departments', 'direct_courses.department_id', '=', 'direct_departments.id')
            ->leftJoin('colleges as direct_colleges', 'direct_departments.college_id', '=', 'direct_colleges.id')
            ->leftJoin('course_sections as result_sections', 'marks.course_section_id', '=', 'result_sections.id')
            ->leftJoin('courses as section_courses', 'result_sections.course_id', '=', 'section_courses.id')
            ->leftJoin('departments as section_departments', 'section_courses.department_id', '=', 'section_departments.id')
            ->leftJoin('colleges as section_colleges', 'section_departments.college_id', '=', 'section_colleges.id')
            ->leftJoin('semesters as result_semesters', 'result_sections.semester_id', '=', 'result_semesters.id')
            ->leftJoin('teachers as result_teachers', 'result_sections.teacher_id', '=', 'result_teachers.id');
    }

    private function resultHierarchyCard(string $title, string $meta, $marks, ?string $href): array
    {
        $pending = $marks->whereIn('submission_status', ['submitted', 'under_review'])->count();
        $published = $marks->where('visibility_status', 'published')->count();
        $average = $marks->whereNotNull('final_mark')->isNotEmpty()
            ? round((float) $marks->avg('final_mark'), 1)
            : null;

        return [
            'title' => $title,
            'meta' => $meta,
            'href' => $href,
            'marks' => $marks->count(),
            'pending' => $pending,
            'published' => $published,
            'average' => $average,
        ];
    }

    private function resultStats(array $filters, int $missingResultsCount): array
    {
        $row = $this->resultsMarkQuery($filters, false)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN submission_status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN submission_status = 'approved' AND visibility_status != 'published' THEN 1 ELSE 0 END) as ready")
            ->selectRaw("SUM(CASE WHEN visibility_status = 'published' THEN 1 ELSE 0 END) as published")
            ->selectRaw("SUM(CASE WHEN visibility_status = 'published' AND final_mark >= 50 THEN 1 ELSE 0 END) as passed")
            ->selectRaw("SUM(CASE WHEN visibility_status = 'published' AND final_mark < 50 THEN 1 ELSE 0 END) as failed")
            ->selectRaw("AVG(CASE WHEN visibility_status = 'published' AND final_mark IS NOT NULL THEN final_mark END) as average")
            ->first();
        $total = (int) ($row->total ?? 0);
        $pending = (int) ($row->pending ?? 0);
        $ready = (int) ($row->ready ?? 0);
        $published = (int) ($row->published ?? 0);
        $passed = (int) ($row->passed ?? 0);
        $failed = (int) ($row->failed ?? 0);
        $average = $published > 0 && ! is_null($row->average) ? number_format((float) $row->average, 1) : 'N/A';
        $passRate = $published > 0 ? number_format(($passed / $published) * 100, 1).'%' : 'N/A';

        return [
            ['label' => 'Total Marks', 'value' => number_format($total), 'detail' => 'Matching current scope'],
            ['label' => 'Pending Review', 'value' => number_format($pending), 'detail' => 'Submitted or under review'],
            ['label' => 'Ready to Publish', 'value' => number_format($ready), 'detail' => 'Approved and not published'],
            ['label' => 'Published', 'value' => number_format($published), 'detail' => 'Visible to students'],
            ['label' => 'Passed', 'value' => number_format($passed), 'detail' => 'Published final mark 50 or more'],
            ['label' => 'Failed', 'value' => number_format($failed), 'detail' => 'Published final mark below 50'],
            ['label' => 'Pass Rate', 'value' => $passRate, 'detail' => 'Published results only'],
            ['label' => 'Avg Published Mark', 'value' => $average, 'detail' => 'Published final mark average'],
            ['label' => 'Missing Results', 'value' => number_format($missingResultsCount), 'detail' => 'Enrolled students without marks'],
        ];
    }

    private function resultRows($marks, bool $canOpenMarkQueue, bool $canViewStudents, bool $canViewCourses)
    {
        return $marks->map(fn (Mark $mark) => [
            'student' => $mark->student?->full_name ?? 'Unknown student',
            'student_id' => $mark->student?->student_id ?? '-',
            'course' => trim(($mark->course?->code ? $mark->course->code.' - ' : '').($mark->course?->name ?? 'No course')),
            'class' => $mark->courseSection ? 'Group '.$mark->courseSection->section_code : 'No class',
            'final_mark' => is_null($mark->final_mark) ? 'N/A' : number_format((float) $mark->final_mark, 1),
            'result_status' => $this->resultOutcome($mark),
            'submission_status' => $this->resultStatusLabel($mark->submission_status),
            'visibility_status' => $this->resultStatusLabel($mark->visibility_status),
            'submitted_at' => $mark->submitted_at?->format('M j, Y H:i') ?? '-',
            'student_url' => $canViewStudents && $mark->student ? route('students.show', $mark->student) : null,
            'course_url' => $canViewCourses && $mark->course ? route('course-records.show', $mark->course) : null,
            'queue_url' => $canOpenMarkQueue && in_array($mark->submission_status, ['submitted', 'under_review', 'approved'], true) ? route('marks.submission-queue') : null,
        ])->values();
    }

    private function resultStatusBreakdown(array $filters)
    {
        $rows = $this->resultsMarkQuery($filters, false)
            ->select('submission_status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('submission_status')
            ->pluck('total', 'submission_status');
        $total = max((int) $rows->sum(), 1);

        return collect(['draft', 'submitted', 'under_review', 'approved', 'rejected'])
            ->map(fn ($status) => [
                'label' => $this->resultStatusLabel($status),
                'count' => (int) ($rows[$status] ?? 0),
                'percent' => round((((int) ($rows[$status] ?? 0)) / $total) * 100, 1),
            ]);
    }

    private function resultCoursePerformance(array $filters)
    {
        $query = $this->resultsMarkQuery($filters, false)
            ->leftJoin('courses', 'marks.course_id', '=', 'courses.id')
            ->select('marks.course_id', 'courses.code', 'courses.name')
            ->selectRaw('COUNT(*) as marks_count')
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' THEN 1 ELSE 0 END) as published_count")
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark >= 50 THEN 1 ELSE 0 END) as passed_count")
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark < 50 THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("AVG(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark IS NOT NULL THEN marks.final_mark END) as average_mark")
            ->groupBy('marks.course_id', 'courses.code', 'courses.name')
            ->orderByDesc('average_mark')
            ->limit(8);

        return $query->get()
            ->map(function ($row) {
                $published = (int) $row->published_count;
                $passed = (int) $row->passed_count;

                return [
                    'course' => trim(($row->code ? $row->code.' - ' : '').($row->name ?? 'No course')),
                    'marks' => (int) $row->marks_count,
                    'average' => is_null($row->average_mark) ? null : round((float) $row->average_mark, 1),
                    'published' => $published,
                    'passed' => $passed,
                    'failed' => (int) $row->failed_count,
                    'pass_rate' => $published > 0 ? round(($passed / $published) * 100, 1) : null,
                ];
            })
            ->values();
    }

    private function resultDepartmentRisk(array $filters)
    {
        $query = $this->resultsMarkQuery($filters, false);
        $this->joinResultDimensions($query);

        return $query
            ->selectRaw('COALESCE(section_departments.id, direct_departments.id, student_departments.id, 0) as department_card_id')
            ->selectRaw("COALESCE(section_departments.name, direct_departments.name, student_departments.name, 'Department not specified') as department_name")
            ->selectRaw("SUM(CASE WHEN marks.submission_status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'draft' THEN 1 ELSE 0 END) as unpublished_count")
            ->groupBy('department_card_id', 'department_name')
            ->orderByRaw("(SUM(CASE WHEN marks.submission_status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) + SUM(CASE WHEN marks.visibility_status = 'draft' THEN 1 ELSE 0 END)) DESC")
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $pending = (int) $row->pending_count;
                $unpublished = (int) $row->unpublished_count;

                return [
                    'department' => $row->department_name,
                    'pending' => $pending,
                    'unpublished' => $unpublished,
                    'attention' => $pending + $unpublished,
                ];
            })
            ->values();
    }

    private function resultCollege(Mark $mark): ?College
    {
        return $mark->courseSection?->course?->department?->college
            ?? $mark->course?->department?->college
            ?? $mark->student?->department?->college;
    }

    private function enrollmentCollege(Enrollment $enrollment): ?College
    {
        return $enrollment->courseSection?->course?->department?->college
            ?? $enrollment->student?->department?->college;
    }

    private function resultDepartment(Mark $mark): ?Department
    {
        return $mark->courseSection?->course?->department
            ?? $mark->course?->department
            ?? $mark->student?->department;
    }

    private function enrollmentDepartment(Enrollment $enrollment): ?Department
    {
        return $enrollment->courseSection?->course?->department
            ?? $enrollment->student?->department;
    }

    private function resultPassed(Mark $mark): bool
    {
        return ! is_null($mark->final_mark) && (float) $mark->final_mark >= 50;
    }

    private function resultFailed(Mark $mark): bool
    {
        return ! is_null($mark->final_mark) && (float) $mark->final_mark < 50;
    }

    private function resultOutcome(Mark $mark): string
    {
        if (is_null($mark->final_mark)) {
            return 'No mark';
        }

        return $this->resultPassed($mark) ? 'Passed' : 'Failed';
    }

    private function resultStatusLabel(?string $status): string
    {
        return str($status ?: 'draft')->replace('_', ' ')->title()->toString();
    }

    public function finance()
    {
        $this->requireAnyPermission(
            'finance.view',
            'finance.create_invoice',
            'finance.record_payment',
            'finance.approve_payment',
            'finance.record_expense',
            'finance.approve_expense',
            'finance.refund'
        );

        $activeStudents = Student::where('status', 'Active')->count();
        $activeTeachers = Teacher::where('status', 'Active')->count();
        $estimatedTuition = $activeStudents * 750000;
        $estimatedPayroll = $activeTeachers * 1200000;

        return view('erp.operations', [
            'title' => 'Accounting & Finance',
            'description' => 'Monitor tuition estimates, fee collection tasks, payroll exposure, and finance approvals.',
            'badge' => 'Finance workspace',
            'stats' => [
                ['label' => 'Active Fee Accounts', 'value' => number_format($activeStudents), 'detail' => 'Active students'],
                ['label' => 'Estimated Tuition', 'value' => number_format($estimatedTuition).' IQD', 'detail' => 'Based on active enrollment'],
                ['label' => 'Payroll Exposure', 'value' => number_format($estimatedPayroll).' IQD', 'detail' => 'Estimated monthly staff payroll'],
                ['label' => 'Approval Queues', 'value' => '0', 'detail' => 'No finance records table yet'],
            ],
            'actions' => collect([
                ['label' => 'Review Students', 'route' => 'students.index', 'style' => 'primary', 'can' => auth()->user()?->hasRole('super_administrator') || auth()->user()?->hasAnyPermission(['students.view', 'students.create', 'students.update', 'students.archive'])],
                ['label' => 'Review Teachers', 'route' => 'teachers.index', 'style' => 'secondary', 'can' => auth()->user()?->hasRole('super_administrator') || auth()->user()?->hasPermission('teachers.view')],
            ])->where('can')->values()->all(),
            'itemsTitle' => 'Finance Controls',
            'items' => collect([
                ['title' => 'Tuition ledger', 'meta' => 'Use active student records as the current fee base.', 'status' => 'Ready'],
                ['title' => 'Payroll planning', 'meta' => 'Use active teacher records for monthly payroll estimates.', 'status' => 'Ready'],
                ['title' => 'Invoices and payments', 'meta' => 'Dedicated finance transaction tables can be added next.', 'status' => 'Planned'],
            ]),
            'emptyText' => 'No finance controls are configured.',
            'workflowTitle' => 'Finance Workflow',
            'workflow' => [
                ['label' => 'Invoice', 'description' => 'Create tuition and service invoices from student records.'],
                ['label' => 'Collect', 'description' => 'Record payments, discounts, scholarships, and refunds.'],
                ['label' => 'Approve', 'description' => 'Review expenses and payment adjustments before reporting.'],
            ],
        ]);

        return $this->modulePage('Accounting & Finance', 'Monitor tuition, invoices, payments, expenses, and financial reporting.', [
            ['label' => 'Collected this month', 'value' => '84,500,000 IQD'],
            ['label' => 'Unpaid tuition', 'value' => '18,700,000 IQD'],
            ['label' => 'Payroll due', 'value' => '9,200,000 IQD'],
        ], [
            ['title' => 'Invoice #INV-1001', 'meta' => 'Student fees • Paid in full'],
            ['title' => 'Scholarship batch', 'meta' => '12 students approved'],
            ['title' => 'Cash flow', 'meta' => 'Daily expense report ready'],
        ]);
    }

    public function attendance(Request $request)
    {
        $this->requireAnyPermission('attendance.view', 'attendance.create', 'attendance.update');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'college_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:50'],
            'semester_id' => ['nullable', 'integer'],
        ]);

        $selectedDate = Carbon::parse($validated['date'] ?? today())->toDateString();
        $filters = [
            'college_id' => $validated['college_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'grade' => $validated['stage'] ?? $validated['grade'] ?? '',
            'semester_id' => $validated['semester_id'] ?? null,
        ];

        $attendanceUser = auth()->user();
        $canMarkAttendance = $attendanceUser?->hasRole('super_administrator')
            || ($attendanceUser?->hasAnyPermission(['attendance.create', 'attendance.update']) ?? false);

        $sections = CourseSection::with(['course.department.college', 'semester', 'teacher'])
            ->withCount([
                'activeEnrollments',
                'attendances as selected_date_attendances_count' => fn ($query) => $query->whereDate('date', $selectedDate),
            ])
            ->whereIn('status', ['planned', 'active'])
            ->get();

        $visibleSections = $sections
            ->when($filters['college_id'], fn ($collection) => $collection->filter(fn (CourseSection $section) => (int) ($section->course?->department?->college_id) === (int) $filters['college_id']))
            ->when($filters['department_id'], fn ($collection) => $collection->filter(fn (CourseSection $section) => (int) ($section->course?->department_id) === (int) $filters['department_id']))
            ->when($filters['grade'] !== '', fn ($collection) => $collection->filter(fn (CourseSection $section) => $this->stageAlias($section->grade_level ?: '__unassigned__') === $this->stageAlias($filters['grade'])))
            ->when($filters['semester_id'], fn ($collection) => $collection->filter(fn (CourseSection $section) => (int) $section->semester_id === (int) $filters['semester_id']))
            ->values();

        $sectionIds = $visibleSections->pluck('id');
        $riskCount = 0;

        if ($sectionIds->isNotEmpty()) {
            $riskCount = Attendance::whereIn('course_section_id', $sectionIds)
                ->get()
                ->groupBy(fn (Attendance $attendance) => $attendance->course_section_id.'-'.$attendance->student_id)
                ->filter(function ($records) {
                    $total = $records->count();
                    $present = $records->where('status', 'present')->count();

                    return $total >= 3 && (($present / $total) * 100) < 75;
                })
                ->count();
        }

        $url = fn (array $extra = []) => route('attendance', array_filter(
            array_merge(['date' => $selectedDate], $extra),
            fn ($value) => $value !== null && $value !== ''
        ));

        $selectedCollege = $filters['college_id']
            ? $sections->first(fn (CourseSection $section) => (int) ($section->course?->department?->college_id) === (int) $filters['college_id'])?->course?->department?->college
            : null;
        $selectedDepartment = $filters['department_id']
            ? $sections->first(fn (CourseSection $section) => (int) ($section->course?->department_id) === (int) $filters['department_id'])?->course?->department
            : null;
        $selectedSemester = $filters['semester_id']
            ? $sections->first(fn (CourseSection $section) => (int) $section->semester_id === (int) $filters['semester_id'])?->semester
            : null;

        $breadcrumbs = collect([
            ['label' => 'Attendance', 'href' => $url()],
            $selectedCollege ? ['label' => $selectedCollege->name, 'href' => $url(['college_id' => $selectedCollege->id])] : null,
            $selectedDepartment ? ['label' => $selectedDepartment->name, 'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $selectedDepartment->id])] : null,
            $filters['grade'] !== '' ? ['label' => $filters['grade'] === '__unassigned__' ? 'Stage not specified' : $filters['grade'], 'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $filters['grade']])] : null,
            $selectedSemester ? ['label' => trim($selectedSemester->name.' '.$selectedSemester->academic_year), 'href' => $url($filters)] : null,
        ])->filter()->values();

        $level = match (true) {
            ! $filters['college_id'] => 'colleges',
            ! $filters['department_id'] => 'departments',
            $filters['grade'] === '' => 'grades',
            ! $filters['semester_id'] => 'semesters',
            default => 'modules',
        };

        $hierarchySections = match ($level) {
            'colleges' => $sections,
            'departments' => $sections->filter(fn (CourseSection $section) => (int) ($section->course?->department?->college_id) === (int) $filters['college_id']),
            'grades' => $sections->filter(fn (CourseSection $section) => (int) ($section->course?->department_id) === (int) $filters['department_id']),
            'semesters' => $sections
                ->filter(fn (CourseSection $section) => (int) ($section->course?->department_id) === (int) $filters['department_id'])
                ->filter(fn (CourseSection $section) => $this->stageAlias($section->grade_level ?: '__unassigned__') === $this->stageAlias($filters['grade'])),
            default => $visibleSections,
        };

        $cards = match ($level) {
            'colleges' => $hierarchySections
                ->groupBy(fn (CourseSection $section) => $section->course?->department?->college_id ?: 0)
                ->map(function ($collegeSections, $collegeId) use ($url) {
                    $college = $collegeSections->first()->course?->department?->college;

                    return [
                        'title' => $college?->name ?? 'College not specified',
                        'meta' => $college?->code ?? 'No code',
                        'count' => $collegeSections->count(),
                        'students' => $collegeSections->sum('active_enrollments_count'),
                        'href' => $collegeId ? $url(['college_id' => $collegeId]) : null,
                    ];
                })
                ->values(),
            'departments' => $hierarchySections
                ->groupBy(fn (CourseSection $section) => $section->course?->department_id ?: 0)
                ->map(function ($departmentSections, $departmentId) use ($url, $filters) {
                    $department = $departmentSections->first()->course?->department;

                    return [
                        'title' => $department?->name ?? 'Department not specified',
                        'meta' => $department?->code ?? 'No code',
                        'count' => $departmentSections->count(),
                        'students' => $departmentSections->sum('active_enrollments_count'),
                        'href' => $departmentId ? $url(['college_id' => $filters['college_id'], 'department_id' => $departmentId]) : null,
                    ];
                })
                ->values(),
            'grades' => $hierarchySections
                ->groupBy(fn (CourseSection $section) => $section->grade_level ?: '__unassigned__')
                ->map(fn ($gradeSections, $grade) => [
                    'title' => $grade === '__unassigned__' ? 'Stage not specified' : $grade,
                    'meta' => $gradeSections->pluck('semester_id')->unique()->count().' semesters',
                    'count' => $gradeSections->count(),
                    'students' => $gradeSections->sum('active_enrollments_count'),
                    'href' => $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'stage' => $grade]),
                ])
                ->values(),
            'semesters' => $hierarchySections
                ->groupBy(fn (CourseSection $section) => $section->semester_id ?: 0)
                ->map(function ($semesterSections, $semesterId) use ($url, $filters) {
                    $semester = $semesterSections->first()->semester;

                    return [
                        'title' => $semester ? trim($semester->name.' '.$semester->academic_year) : 'Semester not specified',
                        'meta' => $semester?->code ?? 'No code',
                        'count' => $semesterSections->count(),
                        'students' => $semesterSections->sum('active_enrollments_count'),
                        'href' => $semesterId ? $url(['college_id' => $filters['college_id'], 'department_id' => $filters['department_id'], 'grade' => $filters['grade'], 'semester_id' => $semesterId]) : null,
                    ];
                })
                ->values(),
            default => collect(),
        };

        $moduleSections = $visibleSections->sortBy(fn (CourseSection $section) => ($section->course?->name ?? '').$section->section_code);

        if ($level !== 'modules') {
            $moduleSections = $moduleSections->take(12);
        }

        $modules = $moduleSections
            ->map(function (CourseSection $section) use ($selectedDate, $canMarkAttendance) {
                $course = $section->course;

                return [
                    'title' => ($course?->name ?? 'Untitled module').' / Group '.$section->section_code,
                    'meta' => ($course?->code ?? 'No code').' / '.($section->teacher->full_name ?? 'No teacher assigned'),
                    'status' => ucfirst($section->status),
                    'students' => $section->active_enrollments_count,
                    'recorded' => $section->selected_date_attendances_count,
                    'markRoute' => $canMarkAttendance ? route('attendance.index', ['course' => $section->course_id, 'section_id' => $section->id, 'date' => $selectedDate]) : null,
                    'reportRoute' => route('attendance.report', ['course' => $section->course_id, 'section_id' => $section->id]),
                    'historyRoute' => route('attendance.history', ['course' => $section->course_id, 'section_id' => $section->id, 'from' => Carbon::parse($selectedDate)->subDays(30)->toDateString(), 'to' => $selectedDate]),
                ];
            })
            ->values();

        $todayEntries = $sectionIds->isEmpty()
            ? 0
            : Attendance::whereDate('date', $selectedDate)
                ->whereIn('course_section_id', $sectionIds)
                ->count();
        $absentToday = $sectionIds->isEmpty()
            ? 0
            : Attendance::whereDate('date', $selectedDate)
                ->whereIn('course_section_id', $sectionIds)
                ->where('status', 'absent')
                ->count();
        $lateToday = $sectionIds->isEmpty()
            ? 0
            : Attendance::whereDate('date', $selectedDate)
                ->whereIn('course_section_id', $sectionIds)
                ->where('status', 'late')
                ->count();
        $expectedEntries = $visibleSections->sum('active_enrollments_count');
        $missingEntries = max($expectedEntries - $todayEntries, 0);
        $attendanceUser = auth()->user();

        return view('attendance.admin', [
            'selectedDate' => $selectedDate,
            'filters' => $filters,
            'breadcrumbs' => $breadcrumbs,
            'level' => $level,
            'cards' => $cards,
            'modules' => $modules,
            'stats' => [
                ['label' => 'Recorded Rows', 'value' => number_format($todayEntries), 'detail' => Carbon::parse($selectedDate)->format('d M Y')],
                ['label' => 'Missing Rows', 'value' => number_format($missingEntries), 'detail' => 'Expected from enrolled students'],
                ['label' => 'Absent', 'value' => number_format($absentToday), 'detail' => 'Marked absent on selected date'],
                ['label' => 'Late', 'value' => number_format($lateToday), 'detail' => 'Marked late on selected date'],
                ['label' => 'At Risk', 'value' => number_format($riskCount), 'detail' => 'Below 75% with 3+ records'],
            ],
            'canMarkAttendance' => $canMarkAttendance,
            'attendanceUser' => $attendanceUser,
        ]);
    }

    public function studentPortal(Request $request)
    {
        $this->requireAnyRole('student');

        $authUser = $request->user();
        $student = Student::query()
            ->where('user_id', $authUser->id)
            ->first();

        if (! $student) {
            $student = Student::query()
                ->where('email', $authUser->email)
                ->first();
        }

        $marks = collect();
        $publishedResultsCount = 0;
        $averageFinalMark = null;
        $enrolledSections = collect();
        $todayClasses = collect();
        $dueTodayAssessments = collect();
        $unreadMessages = collect();
        $attendanceSummary = [
            'value' => 'No records',
            'detail' => 'Attendance has not been recorded yet',
        ];
        $financeSummary = [
            'value' => 'No balance',
            'detail' => 'No unpaid tuition charges',
        ];

        if ($student) {
            $publishedMarksQuery = Mark::query()
                ->where('student_id', $student->id)
                ->where('status', 'Published');

            $publishedResultsCount = (clone $publishedMarksQuery)->count();
            $averageFinalMark = (clone $publishedMarksQuery)->avg('final_mark');
            $marks = (clone $publishedMarksQuery)
                ->with('course')
                ->latest()
                ->limit(12)
                ->get();

            $sectionIds = $student->enrollments()
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                ->pluck('course_section_id');

            $todayClasses = Timetable::with(['course', 'courseSection', 'teacher'])
                ->whereIn('course_section_id', $sectionIds)
                ->where('day_of_week', now('Asia/Baghdad')->format('l'))
                ->where('status', 'scheduled')
                ->orderBy('start_time')
                ->get();

            $enrolledSections = CourseSection::with(['course', 'semester', 'teacher'])
                ->withCount([
                    'streamPosts',
                    'assessmentItems as open_work_count' => fn ($query) => $query
                        ->where('status', 'published')
                        ->where('allow_submissions', true)
                        ->where(fn ($dateQuery) => $dateQuery->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
                        ->where(fn ($dateQuery) => $dateQuery->whereNull('due_at')->orWhere('due_at', '>=', now()))
                        ->whereDoesntHave('submissions', fn ($submissionQuery) => $submissionQuery->where('student_id', $student->id)),
                    'messages as unread_messages_count' => fn ($query) => $query
                        ->where('recipient_id', $authUser->id)
                        ->whereNull('read_at'),
                    'timetables as today_classes_count' => fn ($query) => $query
                        ->where('day_of_week', now('Asia/Baghdad')->format('l'))
                        ->where('status', 'scheduled'),
                ])
                ->whereIn('id', $sectionIds)
                ->orderByDesc('semester_id')
                ->orderBy('section_code')
                ->get();

            $upcomingAssessments = AssessmentItem::whereIn('course_section_id', $sectionIds)
                ->where('status', 'published')
                ->where('allow_submissions', true)
                ->whereNotNull('due_at')
                ->where('due_at', '>=', now())
                ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $student->id))
                ->orderBy('due_at')
                ->get()
                ->groupBy('course_section_id');

            $dueTodayAssessments = AssessmentItem::with(['courseSection.course'])
                ->whereIn('course_section_id', $sectionIds)
                ->where('status', 'published')
                ->where('allow_submissions', true)
                ->whereNotNull('due_at')
                ->where('due_at', '>=', now())
                ->where('due_at', '<=', now('Asia/Baghdad')->endOfDay())
                ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $student->id))
                ->orderBy('due_at')
                ->limit(5)
                ->get();

            $unreadMessages = ClassMessage::with(['courseSection.course', 'sender'])
                ->whereIn('course_section_id', $sectionIds)
                ->where('recipient_id', $authUser->id)
                ->whereNull('read_at')
                ->latest()
                ->limit(5)
                ->get();

            $enrolledSections->each(fn (CourseSection $section) => $section->setAttribute(
                'next_assessment',
                $upcomingAssessments->get($section->id)?->first()
            ));

            $attendanceRecords = Attendance::where('student_id', $student->id)
                ->whereIn('course_section_id', $sectionIds)
                ->get(['status']);
            if ($attendanceRecords->isNotEmpty()) {
                $attended = $attendanceRecords->whereIn('status', ['present', 'late', 'excused'])->count();
                $absences = $attendanceRecords->where('status', 'absent')->count();
                $rate = round(($attended / $attendanceRecords->count()) * 100, 1);

                $attendanceSummary = [
                    'value' => number_format($rate, 1).'%',
                    'detail' => $absences.' absent / '.$attendanceRecords->count().' records',
                ];
            }

            $financeSummary = $this->studentFinanceSummary($student);
        }

        $stats = $student ? [
            ['label' => 'Published Results', 'value' => number_format($publishedResultsCount)],
            ['label' => 'Average Final Mark', 'value' => $publishedResultsCount > 0 ? number_format((float) $averageFinalMark, 2) : 'N/A'],
            ['label' => 'Enrolled Classes', 'value' => number_format($enrolledSections->count())],
            ['label' => 'Attendance Status', 'value' => $attendanceSummary['value'], 'detail' => $attendanceSummary['detail']],
            ['label' => 'Finance Balance', 'value' => $financeSummary['value'], 'detail' => $financeSummary['detail']],
        ] : [];

        return view('erp.student-portal', compact(
            'student',
            'stats',
            'marks',
            'enrolledSections',
            'todayClasses',
            'dueTodayAssessments',
            'unreadMessages'
        ));
    }

    private function studentFinanceSummary(Student $student): array
    {
        $transactions = FinanceTransaction::where('student_id', $student->id)
            ->where('status', '!=', 'cancelled')
            ->get();

        $balances = $transactions
            ->groupBy('currency')
            ->map(function ($currencyTransactions, string $currency) {
                $charges = $currencyTransactions
                    ->whereIn('type', FinanceTransaction::chargeTypes())
                    ->sum(fn (FinanceTransaction $transaction) => (float) $transaction->amount);
                $credits = $currencyTransactions
                    ->whereIn('type', FinanceTransaction::creditTypes())
                    ->sum(fn (FinanceTransaction $transaction) => (float) $transaction->amount);

                return [
                    'currency' => $currency,
                    'balance' => max(0, $charges - $credits),
                ];
            })
            ->filter(fn (array $balance) => $balance['balance'] > 0)
            ->values();

        $nextDue = $transactions
            ->filter(fn (FinanceTransaction $transaction) => $transaction->type === 'invoice'
                && in_array($transaction->payment_status, ['open', 'partial', 'overdue'], true)
                && $transaction->due_date)
            ->sortBy('due_date')
            ->first();

        if ($balances->isEmpty()) {
            return [
                'value' => 'No balance',
                'detail' => $nextDue ? 'Next due '.$nextDue->due_date->format('M j, Y') : 'No unpaid tuition charges',
            ];
        }

        $value = $balances
            ->take(2)
            ->map(fn (array $balance) => number_format($balance['balance'], 2).' '.$balance['currency'])
            ->join(' / ');

        return [
            'value' => $value,
            'detail' => $nextDue ? 'Next due '.$nextDue->due_date->format('M j, Y') : 'No due date set',
        ];
    }

    public function bolognaDefinition()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $universitiesQuery = University::orderBy('name');
        $collegesQuery = College::with('university')->orderBy('name');
        $departmentsQuery = Department::with(['university', 'college'])->orderBy('name');
        $semestersQuery = Semester::with('university')->orderByDesc('academic_year')->orderBy('name');
        $academicYearsQuery = AcademicYear::with('university')->orderByDesc('name');
        $stagesQuery = Stage::with('department')->orderBy('sequence');
        $coursesQuery = Course::with(['department.college'])->withCount('sections')->orderBy('name');
        $sectionsQuery = CourseSection::with(['course.department.college', 'semester', 'teacher', 'stage'])
            ->withCount('activeEnrollments')
            ->orderByDesc('created_at');

        OrganizationScope::apply($universitiesQuery, $user, 'university');
        OrganizationScope::apply($collegesQuery, $user, 'college');
        OrganizationScope::apply($departmentsQuery, $user, 'department');
        OrganizationScope::apply($semestersQuery, $user, 'semester');
        OrganizationScope::apply($academicYearsQuery, $user, 'academic_year');
        OrganizationScope::apply($stagesQuery, $user, 'stage');
        OrganizationScope::apply($coursesQuery, $user, 'course');
        OrganizationScope::apply($sectionsQuery, $user, 'section');

        $universityCount = (clone $universitiesQuery)->count();
        $collegeCount = (clone $collegesQuery)->count();
        $departmentCount = (clone $departmentsQuery)->count();
        $semesterCount = (clone $semestersQuery)->count();
        $academicYearCount = (clone $academicYearsQuery)->count();
        $semesterCreditPolicyCount = SemesterCreditPolicy::query()
            ->whereIn('university_id', (clone $universitiesQuery)->select('id'))
            ->whereNotNull('academic_year_id')
            ->count();
        $stageCount = (clone $stagesQuery)->count();
        $courseCount = (clone $coursesQuery)->count();
        $moduleCount = (clone $sectionsQuery)->reorder()->count();
        $inactiveCourseCount = (clone $coursesQuery)->where('status', 'inactive')->count();
        $missingCreditPolicyCount = max(0, $academicYearCount - $semesterCreditPolicyCount);

        $structureStats = [
            ['label' => 'Universities', 'value' => number_format($universityCount), 'detail' => 'Institution records in scope'],
            ['label' => 'Colleges', 'value' => number_format($collegeCount), 'detail' => 'College definitions'],
            ['label' => 'Departments', 'value' => number_format($departmentCount), 'detail' => 'Academic departments'],
            ['label' => 'Semesters', 'value' => number_format($semesterCount), 'detail' => 'Academic periods'],
            ['label' => 'Course Catalog', 'value' => number_format($courseCount), 'detail' => 'Catalog definitions'],
            ['label' => 'Modules', 'value' => number_format($moduleCount), 'detail' => 'Course sections by stage'],
        ];

        $activeEnrollmentCounts = Enrollment::query()
            ->selectRaw('course_section_id, COUNT(*) as active_enrollments_count')
            ->where('status', 'enrolled')
            ->groupBy('course_section_id');

        $stageSectionsQuery = CourseSection::query();
        OrganizationScope::apply($stageSectionsQuery, $user, 'section');

        $stageSummaries = $stageSectionsQuery
            ->leftJoin('courses', 'course_sections.course_id', '=', 'courses.id')
            ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
            ->leftJoin('colleges', 'departments.college_id', '=', 'colleges.id')
            ->leftJoin('universities', 'departments.university_id', '=', 'universities.id')
            ->leftJoin('stages', 'course_sections.stage_id', '=', 'stages.id')
            ->leftJoinSub($activeEnrollmentCounts, 'active_enrollment_counts', function ($join) {
                $join->on('active_enrollment_counts.course_section_id', '=', 'course_sections.id');
            })
            ->selectRaw('universities.name as university')
            ->selectRaw('colleges.name as college')
            ->selectRaw('departments.id as department_id')
            ->selectRaw('departments.name as department')
            ->selectRaw('COALESCE(stages.id, 0) as stage_id')
            ->selectRaw("COALESCE(stages.name, NULLIF(course_sections.grade_level, ''), 'Stage not specified') as stage")
            ->selectRaw('COUNT(course_sections.id) as modules')
            ->selectRaw('COUNT(DISTINCT course_sections.course_id) as courses')
            ->selectRaw('COUNT(DISTINCT course_sections.semester_id) as semesters')
            ->selectRaw('COALESCE(SUM(active_enrollment_counts.active_enrollments_count), 0) as students')
            ->groupBy(
                'universities.name',
                'colleges.name',
                'departments.id',
                'departments.name',
                'stages.id',
                'stages.name',
                'course_sections.grade_level'
            )
            ->orderBy('universities.name')
            ->orderBy('colleges.name')
            ->orderBy('departments.name')
            ->orderBy('stage')
            ->get();

        $stageCreditRowsQuery = CourseSection::query();
        OrganizationScope::apply($stageCreditRowsQuery, $user, 'section');
        $stageCreditRows = $stageCreditRowsQuery
            ->join('courses', 'course_sections.course_id', '=', 'courses.id')
            ->leftJoin('stages', 'course_sections.stage_id', '=', 'stages.id')
            ->selectRaw('courses.department_id as department_id')
            ->selectRaw('COALESCE(stages.id, 0) as stage_id')
            ->selectRaw("COALESCE(stages.name, NULLIF(course_sections.grade_level, ''), 'Stage not specified') as stage")
            ->selectRaw('course_sections.course_id as course_id')
            ->selectRaw('MAX(courses.credits) as credits')
            ->groupBy('courses.department_id', 'stages.id', 'stages.name', 'course_sections.grade_level', 'course_sections.course_id');
        $stageCredits = DB::query()
            ->fromSub($stageCreditRows, 'stage_courses')
            ->selectRaw('department_id, stage_id, stage, SUM(credits) as credits')
            ->groupBy('department_id', 'stage_id', 'stage')
            ->get()
            ->keyBy(fn ($row) => $row->department_id.'|'.$row->stage_id.'|'.$row->stage);

        $stageSummaries = $stageSummaries
            ->map(fn ($stage) => [
                'university' => $stage->university,
                'college' => $stage->college,
                'department' => $stage->department,
                'stage' => $stage->stage,
                'modules' => (int) $stage->modules,
                'courses' => (int) $stage->courses,
                'semesters' => (int) $stage->semesters,
                'students' => (int) $stage->students,
                'credits' => round((float) ($stageCredits[$stage->department_id.'|'.$stage->stage_id.'|'.$stage->stage]->credits ?? 0), 1),
            ]);

        $moduleSummaries = (clone $sectionsQuery)
            ->limit(12)
            ->get()
            ->map(fn (CourseSection $section) => [
                'module' => trim(($section->course?->code ?? 'Course').' '.$section->section_code),
                'course' => $section->course?->name ?? 'No course',
                'stage' => $section->stage?->name ?? ($section->grade_level ?: 'Stage not specified'),
                'semester' => trim(($section->semester?->name ?? 'No semester').' '.($section->semester?->academic_year ?? '')),
                'teacher' => $section->teacher?->full_name ?? 'Unassigned teacher',
                'students' => $section->active_enrollments_count,
                'credits' => $section->course?->credits ?? 0,
            ]);

        $universityScopeQuery = University::query()->orderBy('name');
        OrganizationScope::apply($universityScopeQuery, $user, 'university');
        $scopedUniversities = $universityScopeQuery->get([
            'id',
            'institution_type',
            'expected_stage_count',
            'expected_semesters_per_year',
        ]);

        $academicYearSemesterCountsQuery = AcademicYear::query()
            ->leftJoin('semesters', 'semesters.academic_year_id', '=', 'academic_years.id')
            ->selectRaw('academic_years.university_id as university_id')
            ->selectRaw('academic_years.id as academic_year_id')
            ->selectRaw('COUNT(semesters.id) as semester_count')
            ->groupBy('academic_years.university_id', 'academic_years.id');
        OrganizationScope::apply($academicYearSemesterCountsQuery, $user, 'academic_year');
        $academicYearSemesterCounts = $academicYearSemesterCountsQuery->get();

        $institutionSemesterRuleViolations = $academicYearSemesterCounts
            ->filter(function ($academicYear) use ($scopedUniversities) {
                $university = $scopedUniversities->firstWhere('id', (int) $academicYear->university_id);
                if (! $university) {
                    return false;
                }

                $expectedSemesters = $university->expectedSemesterCount();
                $actualSemesters = (int) $academicYear->semester_count;

                return $actualSemesters < $expectedSemesters || $actualSemesters > $expectedSemesters + 1;
            })
            ->count();

        $departmentStageCountsQuery = Department::query()
            ->leftJoin('stages', 'stages.department_id', '=', 'departments.id')
            ->selectRaw('departments.id as department_id, departments.university_id as university_id, COUNT(DISTINCT stages.sequence) as stage_count')
            ->groupBy('departments.id', 'departments.university_id');
        OrganizationScope::apply($departmentStageCountsQuery, $user, 'department');
        $departmentStageCounts = $departmentStageCountsQuery->get();
        $universitiesById = $scopedUniversities->keyBy('id');

        $institutionStageRuleViolations = $departmentStageCounts
            ->filter(function ($department) use ($universitiesById) {
                $university = $universitiesById->get((int) $department->university_id);

                return $university && (int) $department->stage_count !== $university->expectedStageCount();
            })
            ->count();

        $curriculumSignals = collect([
            [
                'label' => 'Institution semester rule mismatch',
                'value' => $institutionSemesterRuleViolations,
                'detail' => 'Each academic year must match its institution semester target; one optional summer semester is allowed.',
            ],
            [
                'label' => 'Institution stage rule mismatch',
                'value' => $institutionStageRuleViolations,
                'detail' => 'Departments must define the number of program stages configured for their institution.',
            ],
            [
                'label' => 'Missing stage',
                'value' => (clone $sectionsQuery)
                    ->reorder()
                    ->whereNull('stage_id')
                    ->count(),
                'detail' => 'Modules should be assigned to a stage.',
            ],
            [
                'label' => 'Missing semester',
                'value' => (clone $sectionsQuery)->reorder()->whereNull('semester_id')->count(),
                'detail' => 'Modules should be attached to an academic period.',
            ],
            [
                'label' => 'Unassigned teacher',
                'value' => (clone $sectionsQuery)->reorder()->whereNull('teacher_id')->count(),
                'detail' => 'Every active module should have an instructor.',
            ],
            [
                'label' => 'Inactive courses',
                'value' => $inactiveCourseCount,
                'detail' => 'Review inactive catalog definitions.',
            ],
            [
                'label' => 'Missing credit policy',
                'value' => $missingCreditPolicyCount,
                'detail' => 'Every institution should define semester, progression, and graduation credit requirements.',
            ],
        ]);
        $canManageAcademicSetup = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');
        $canViewCourseCatalog = $user->hasRole('super_administrator')
            || $user->hasPermission('courses.view')
            || $user->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);
        $canViewModuleOfferings = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasPermission('enrollments.view')
            || $user->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);

        $setupCards = [
            ['title' => 'Universities', 'description' => 'Institution records and official codes.', 'route' => route('universities.index'), 'count' => $universityCount, 'enabled' => true],
            ['title' => 'Colleges', 'description' => 'College definitions under each university.', 'route' => route('colleges.index'), 'count' => $collegeCount, 'enabled' => true],
            ['title' => 'Departments', 'description' => 'Departments mapped to colleges and universities.', 'route' => route('departments.index'), 'count' => $departmentCount, 'enabled' => true],
            ['title' => 'Stages', 'description' => 'Reusable study stages defined for each department.', 'route' => route('stages.index'), 'count' => $stageCount, 'enabled' => true],
            ['title' => 'Semester Credit Policy', 'description' => 'Review semester ECTS/credits and passing credits required.', 'route' => route('bologna-definition.semester-credit-policy'), 'count' => $semesterCreditPolicyCount, 'enabled' => true],
            ['title' => 'Course Catalog', 'description' => 'Catalog definitions, credits, and status.', 'route' => route('course-records.index'), 'count' => $courseCount, 'enabled' => $canViewCourseCatalog],
            ['title' => 'Academic Years', 'description' => 'Manage upcoming, active, closed, and archived academic periods.', 'route' => route('academic-years.index'), 'count' => $academicYearCount, 'enabled' => true],
            ['title' => 'Semesters', 'description' => 'Define regular and optional summer periods inside each academic year.', 'route' => route('semesters.index'), 'count' => $semesterCount, 'enabled' => true],
            ['title' => 'Module Offerings', 'description' => 'Open catalog courses by academic year, semester, stage, group, and teacher.', 'route' => route('module-offerings.index'), 'count' => $moduleCount, 'enabled' => $canViewModuleOfferings],
        ];

        return view('erp.bologna-definition', compact(
            'setupCards',
            'structureStats',
            'stageSummaries',
            'moduleSummaries',
            'curriculumSignals',
            'canManageAcademicSetup'
        ));
    }

    public function semesterCreditPolicy()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $universitiesQuery = University::query()->orderBy('name');
        OrganizationScope::apply($universitiesQuery, $user, 'university');
        $universities = $universitiesQuery->get(['id', 'name', 'institution_type']);

        $academicYearsQuery = AcademicYear::with('university')
            ->whereIn('university_id', $universities->pluck('id'))
            ->orderByDesc('starts_on')
            ->orderByDesc('name');
        OrganizationScope::apply($academicYearsQuery, $user, 'academic_year');
        $academicYears = $academicYearsQuery->get();

        $policies = SemesterCreditPolicy::query()
            ->whereIn('academic_year_id', $academicYears->pluck('id'))
            ->get()
            ->keyBy('academic_year_id');
        $canManageAcademicSetup = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('erp.semester-credit-policy', compact('academicYears', 'policies', 'canManageAcademicSetup'));
    }

    public function storeSemesterCreditPolicy(Request $request)
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $academicYearsQuery = AcademicYear::with('university')->orderByDesc('starts_on');
        OrganizationScope::apply($academicYearsQuery, $user, 'academic_year');
        $academicYears = $academicYearsQuery->get()->keyBy('id');

        $validated = $request->validate([
            'policies' => ['required', 'array'],
        ]);

        foreach ($validated['policies'] as $targetId => $policy) {
            if (! is_array($policy)) {
                continue;
            }

            $academicYear = $academicYears->get((int) $targetId);
            if (! $academicYear) {
                $academicYear = $academicYears
                    ->where('university_id', (int) $targetId)
                    ->whereNotIn('status', ['closed', 'archived'])
                    ->sortByDesc('starts_on')
                    ->first();
            }
            abort_unless($academicYear, 403);

            if ($academicYear->isLocked()) {
                return back()->withInput()->withErrors([
                    "policies.{$academicYear->id}" => "{$academicYear->name}: policies for closed or archived years are read-only.",
                ]);
            }

            $university = $academicYear->university;

            $semesterCredits = (int) ($policy['semester_credits'] ?? 0);
            $passingCredits = (int) ($policy['passing_credits'] ?? 0);
            $graduationCredits = (int) ($policy['graduation_credits'] ?? 0);

            if ($semesterCredits < 1 || $semesterCredits > 120) {
                return back()->withInput()->withErrors([
                    "policies.{$academicYear->id}.semester_credits" => "{$university->name} {$academicYear->name}: semester credits must be between 1 and 120.",
                ]);
            }

            if ($passingCredits < 1 || $passingCredits > $semesterCredits) {
                return back()->withInput()->withErrors([
                    "policies.{$academicYear->id}.passing_credits" => "{$university->name} {$academicYear->name}: passing credits must be between 1 and semester credits.",
                ]);
            }

            if ($graduationCredits < $semesterCredits || $graduationCredits > 2000) {
                return back()->withInput()->withErrors([
                    "policies.{$academicYear->id}.graduation_credits" => "{$university->name} {$academicYear->name}: graduation credits must be at least one semester and no more than 2000.",
                ]);
            }

            SemesterCreditPolicy::updateOrCreate(
                ['university_id' => $university->id, 'academic_year_id' => $academicYear->id],
                [
                    'semester_credits' => $semesterCredits,
                    'passing_credits' => $passingCredits,
                    'graduation_credits' => $graduationCredits,
                ]
            );
        }

        return redirect()
            ->route('bologna-definition.semester-credit-policy')
            ->with('success', 'Semester credit policy updated successfully.');
    }

    public function studentRankings(Request $request)
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $sectionsQuery = CourseSection::withTrashed();
        OrganizationScope::apply($sectionsQuery, $user, 'section');

        [$studentRankingHierarchy, $rankings, $rankingFilters, $rankingOptions] = $this->bolognaStudentRankingHierarchy($sectionsQuery, $request);

        return view('erp.student-rankings', compact('studentRankingHierarchy', 'rankings', 'rankingFilters', 'rankingOptions'));
    }

    public function exportStudentRankings(Request $request)
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $sectionsQuery = CourseSection::withTrashed();
        OrganizationScope::apply($sectionsQuery, $user, 'section');
        [, , , , $rankingQuery] = $this->bolognaStudentRankingHierarchy($sectionsQuery, $request);

        return response()->streamDownload(function () use ($rankingQuery) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Rank',
                'Student ID',
                'Student',
                'University',
                'College',
                'Department',
                'Stage',
                'Academic Year',
                'Average',
                'Stage Credits',
                'Lifetime Credits',
                'Progression Eligible',
                'Graduation Eligible',
            ]);

            foreach ($rankingQuery->cursor() as $row) {
                fputcsv($output, [
                    $row->student_rank,
                    $row->student_number,
                    $row->student_name,
                    $row->university,
                    $row->college,
                    $row->department,
                    $row->stage,
                    $row->academic_year,
                    round((float) $row->average_mark, 1),
                    round((float) $row->earned_credits, 1),
                    round((float) $row->lifetime_credits, 1),
                    (int) $row->required_progression_credits > 0
                        ? ((float) $row->earned_credits >= (int) $row->required_progression_credits ? 'Yes' : 'No')
                        : 'Policy missing',
                    (int) $row->required_graduation_credits > 0
                        ? ((float) $row->lifetime_credits >= (int) $row->required_graduation_credits ? 'Yes' : 'No')
                        : 'Policy missing',
                ]);
            }

            fclose($output);
        }, 'student-rankings-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function bolognaStudentRankingHierarchy($sectionsQuery, Request $request): array
    {
        $passMark = (int) config('academics.pass_mark', 50);
        $rankingFilters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage_id' => $request->integer('stage_id') ?: null,
            'academic_year' => trim((string) $request->query('academic_year', '')),
        ];
        $scopedSectionIds = (clone $sectionsQuery)->reorder()->select('course_sections.id');

        $passedCourses = Enrollment::query()
            ->join('course_sections', 'enrollments.course_section_id', '=', 'course_sections.id')
            ->join('courses', 'course_sections.course_id', '=', 'courses.id')
            ->join('marks', function ($join) use ($passMark) {
                $join->on('marks.course_section_id', '=', 'course_sections.id')
                    ->on('marks.student_id', '=', 'enrollments.student_id')
                    ->whereNull('marks.deleted_at')
                    ->where('marks.visibility_status', 'published')
                    ->where('marks.final_mark', '>=', $passMark);
            })
            ->whereIn('enrollments.course_section_id', (clone $scopedSectionIds))
            ->whereIn('enrollments.status', ['enrolled', 'completed'])
            ->whereNull('enrollments.deleted_at')
            ->selectRaw('enrollments.student_id as student_id, courses.university_id as university_id, courses.id as course_id, MAX(courses.credits) as credits')
            ->groupBy('enrollments.student_id', 'courses.university_id', 'courses.id');
        $lifetimeCredits = DB::query()
            ->fromSub($passedCourses, 'passed_courses')
            ->selectRaw('student_id, university_id, SUM(credits) as earned_credits')
            ->groupBy('student_id', 'university_id');

        $studentScores = Enrollment::query()
            ->join('course_sections', 'enrollments.course_section_id', '=', 'course_sections.id')
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('semesters', 'course_sections.semester_id', '=', 'semesters.id')
            ->leftJoin('academic_years as ranking_years', 'semesters.academic_year_id', '=', 'ranking_years.id')
            ->join('courses', 'course_sections.course_id', '=', 'courses.id')
            ->join('departments', 'courses.department_id', '=', 'departments.id')
            ->join('colleges', 'departments.college_id', '=', 'colleges.id')
            ->join('universities', 'departments.university_id', '=', 'universities.id')
            ->leftJoin('stages', 'course_sections.stage_id', '=', 'stages.id')
            ->leftJoin('semester_credit_policies as year_credit_policies', function ($join) {
                $join->on('universities.id', '=', 'year_credit_policies.university_id')
                    ->on('ranking_years.id', '=', 'year_credit_policies.academic_year_id');
            })
            ->leftJoin('semester_credit_policies as default_credit_policies', function ($join) {
                $join->on('universities.id', '=', 'default_credit_policies.university_id')
                    ->whereNull('default_credit_policies.academic_year_id');
            })
            ->leftJoinSub($lifetimeCredits, 'lifetime_credits', function ($join) {
                $join->on('lifetime_credits.student_id', '=', 'students.id')
                    ->on('lifetime_credits.university_id', '=', 'universities.id');
            })
            ->leftJoin('marks', function ($join) {
                $join->on('course_sections.id', '=', 'marks.course_section_id')
                    ->on('students.id', '=', 'marks.student_id')
                    ->whereNull('marks.deleted_at');
            })
            ->whereIn('enrollments.course_section_id', $scopedSectionIds)
            ->whereIn('enrollments.status', ['enrolled', 'completed'])
            ->whereNull('enrollments.deleted_at')
            ->whereNull('students.deleted_at')
            ->selectRaw('universities.id as university_id')
            ->selectRaw('universities.name as university')
            ->selectRaw('colleges.id as college_id')
            ->selectRaw('colleges.name as college')
            ->selectRaw('departments.id as department_id')
            ->selectRaw('departments.name as department')
            ->selectRaw('COALESCE(stages.id, 0) as stage_id')
            ->selectRaw("COALESCE(stages.name, NULLIF(course_sections.grade_level, ''), 'Stage not specified') as stage")
            ->selectRaw('semesters.academic_year as academic_year')
            ->selectRaw('students.id as student_id')
            ->selectRaw('students.full_name as student_name')
            ->selectRaw('students.student_id as student_number')
            ->selectRaw('1.0 * SUM(marks.final_mark * courses.credits) / NULLIF(SUM(courses.credits), 0) as average_mark')
            ->selectRaw('COUNT(enrollments.id) as modules_count')
            ->selectRaw('COUNT(DISTINCT course_sections.semester_id) as semester_count')
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark >= ? THEN courses.credits ELSE 0 END) as earned_credits", [$passMark])
            ->selectRaw('COALESCE(MAX(lifetime_credits.earned_credits), 0) as lifetime_credits')
            ->selectRaw('COALESCE(MAX(year_credit_policies.passing_credits), MAX(default_credit_policies.passing_credits), 0) * COUNT(DISTINCT course_sections.semester_id) as required_progression_credits')
            ->selectRaw('COALESCE(MAX(year_credit_policies.graduation_credits), MAX(default_credit_policies.graduation_credits), 0) as required_graduation_credits')
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark IS NOT NULL THEN 1 ELSE 0 END) as published_count")
            ->selectRaw("SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark >= ? THEN 1 ELSE 0 END) as passed_count", [$passMark])
            ->groupBy(
                'universities.id',
                'universities.name',
                'colleges.id',
                'colleges.name',
                'departments.id',
                'departments.name',
                'stages.id',
                'stages.name',
                'course_sections.grade_level',
                'semesters.academic_year',
                'students.id',
                'students.full_name',
                'students.student_id'
            )
            ->havingRaw("COUNT(enrollments.id) = SUM(CASE WHEN marks.visibility_status = 'published' AND marks.final_mark IS NOT NULL THEN 1 ELSE 0 END)")
            ->havingRaw('MIN(marks.final_mark) >= ?', [$passMark]);

        $rankedScores = DB::query()
            ->fromSub($studentScores, 'student_scores')
            ->select('student_scores.*')
            ->selectRaw('DENSE_RANK() OVER (PARTITION BY university_id, college_id, department_id, stage_id, stage, academic_year ORDER BY average_mark DESC) as student_rank');

        $filteredRankings = DB::query()
            ->fromSub($rankedScores, 'ranked_scores')
            ->when($rankingFilters['q'] !== '', fn ($query) => $query->where(function ($search) use ($rankingFilters) {
                $search->where('student_name', 'like', "%{$rankingFilters['q']}%")
                    ->orWhere('student_number', 'like', "%{$rankingFilters['q']}%");
            }))
            ->when($rankingFilters['college_id'], fn ($query, $id) => $query->where('college_id', $id))
            ->when($rankingFilters['department_id'], fn ($query, $id) => $query->where('department_id', $id))
            ->when($rankingFilters['stage_id'], fn ($query, $id) => $query->where('stage_id', $id))
            ->when($rankingFilters['academic_year'] !== '', fn ($query) => $query->where('academic_year', $rankingFilters['academic_year']));

        $collegeTotals = (clone $filteredRankings)
            ->selectRaw('university_id, college_id, COUNT(DISTINCT student_id) as students')
            ->groupBy('university_id', 'college_id')
            ->get()
            ->keyBy(fn ($row) => $row->university_id.'|'.$row->college_id);
        $departmentTotals = (clone $filteredRankings)
            ->selectRaw('department_id, COUNT(DISTINCT student_id) as students')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');
        $stageTotals = (clone $filteredRankings)
            ->selectRaw('department_id, stage_id, stage, COUNT(DISTINCT student_id) as students')
            ->groupBy('department_id', 'stage_id', 'stage')
            ->get()
            ->keyBy(fn ($row) => $row->department_id.'|'.$row->stage_id.'|'.$row->stage);
        $yearTotals = (clone $filteredRankings)
            ->selectRaw('department_id, stage_id, stage, academic_year, COUNT(DISTINCT student_id) as students')
            ->groupBy('department_id', 'stage_id', 'stage', 'academic_year')
            ->get()
            ->keyBy(fn ($row) => $row->department_id.'|'.$row->stage_id.'|'.$row->stage.'|'.$row->academic_year);

        $rankingQuery = (clone $filteredRankings)
            ->orderBy('university')
            ->orderBy('college')
            ->orderBy('department')
            ->orderBy('stage')
            ->orderByDesc('academic_year')
            ->orderBy('student_rank')
            ->orderBy('student_name');
        $rankings = $rankingQuery->paginate(100)->withQueryString();
        $rows = collect($rankings->items());
        $hierarchy = $rows
            ->groupBy(fn ($row) => $row->university_id.'|'.$row->college_id)
            ->map(fn ($collegeRows) => [
                'university' => $collegeRows->first()->university,
                'college' => $collegeRows->first()->college,
                'students' => (int) ($collegeTotals[$collegeRows->first()->university_id.'|'.$collegeRows->first()->college_id]->students ?? 0),
                'departments' => $collegeRows
                    ->groupBy('department_id')
                    ->map(fn ($departmentRows, $departmentId) => [
                        'department' => $departmentRows->first()->department,
                        'students' => (int) ($departmentTotals[$departmentId]->students ?? 0),
                        'stages' => $departmentRows
                            ->groupBy(fn ($row) => $row->stage_id.'|'.$row->stage)
                            ->map(fn ($stageRows, $stageKey) => [
                                'stage' => $stageRows->first()->stage,
                                'students' => (int) ($stageTotals[$departmentId.'|'.$stageKey]->students ?? 0),
                                'years' => $stageRows
                                    ->groupBy('academic_year')
                                    ->map(fn ($yearRows, string $academicYear) => [
                                        'academic_year' => $academicYear,
                                        'total_students' => (int) ($yearTotals[$departmentId.'|'.$stageKey.'|'.$academicYear]->students ?? 0),
                                        'students' => $yearRows
                                            ->map(fn ($row) => [
                                                'rank' => (int) $row->student_rank,
                                                'name' => $row->student_name,
                                                'student_number' => $row->student_number ?: '-',
                                                'average' => round((float) $row->average_mark, 1),
                                                'marks' => (int) $row->modules_count,
                                                'earned_credits' => round((float) $row->earned_credits, 1),
                                                'required_progression_credits' => (int) $row->required_progression_credits,
                                                'progression_eligible' => (int) $row->required_progression_credits > 0
                                                    ? (float) $row->earned_credits >= (int) $row->required_progression_credits
                                                    : null,
                                                'lifetime_credits' => round((float) $row->lifetime_credits, 1),
                                                'required_graduation_credits' => (int) $row->required_graduation_credits,
                                                'graduation_eligible' => (int) $row->required_graduation_credits > 0
                                                    ? (float) $row->lifetime_credits >= (int) $row->required_graduation_credits
                                                    : null,
                                                'pass_rate' => (int) $row->modules_count > 0
                                                    ? round(((int) $row->passed_count / (int) $row->modules_count) * 100)
                                                    : 0,
                                            ])
                                            ->values(),
                                    ])
                                    ->values(),
                            ])
                            ->values(),
                    ])
                    ->values(),
            ])
            ->values();

        $optionBase = DB::table('course_sections')
            ->join('semesters', 'course_sections.semester_id', '=', 'semesters.id')
            ->join('courses', 'course_sections.course_id', '=', 'courses.id')
            ->join('departments', 'courses.department_id', '=', 'departments.id')
            ->join('colleges', 'departments.college_id', '=', 'colleges.id')
            ->leftJoin('stages', 'course_sections.stage_id', '=', 'stages.id')
            ->whereIn('course_sections.id', (clone $scopedSectionIds));
        $rankingOptions = [
            'colleges' => (clone $optionBase)->selectRaw('colleges.id as college_id, colleges.name as college')->distinct()->orderBy('college')->get(),
            'departments' => (clone $optionBase)->selectRaw('departments.id as department_id, departments.college_id as college_id, departments.name as department')->distinct()->orderBy('department')->get(),
            'stages' => (clone $optionBase)->selectRaw('COALESCE(stages.id, 0) as stage_id, departments.id as department_id')->selectRaw("COALESCE(stages.name, NULLIF(course_sections.grade_level, ''), 'Stage not specified') as stage")->distinct()->orderBy('stage')->get(),
            'academicYears' => (clone $optionBase)->orderByDesc('semesters.academic_year')->distinct()->pluck('semesters.academic_year'),
        ];

        return [$hierarchy, $rankings, $rankingFilters, $rankingOptions, $rankingQuery];
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();
        $limit = 10;

        $canSearchStudents = $user->hasRole('super_administrator')
            || $user->hasAnyPermission(['students.view', 'students.create', 'students.update', 'students.archive']);
        $canSearchTeachers = $user->hasRole('super_administrator')
            || $user->hasPermission('teachers.view');
        $canSearchCourses = $user->hasRole('super_administrator')
            || $user->hasPermission('courses.view');
        $canSearchStructure = $user->hasAnyRole([
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'registrar',
        ]);
        $canSearchUsers = $user->hasRole('super_administrator');

        $students = collect();
        $teachers = collect();
        $courses = collect();
        $universities = collect();
        $colleges = collect();
        $departments = collect();
        $semesters = collect();
        $users = collect();

        if ($query !== '') {
            if ($canSearchStudents) {
                $students = Student::with(['university', 'department'])
                    ->where(function ($q) use ($query) {
                        $q->where('full_name', 'like', "%{$query}%")
                            ->orWhere('student_id', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    })
                    ->latest()
                    ->limit($limit)
                    ->get();
            }

            if ($canSearchTeachers) {
                $teachers = Teacher::with(['university', 'department'])
                    ->where(function ($q) use ($query) {
                        $q->where('full_name', 'like', "%{$query}%")
                            ->orWhere('staff_id', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('title', 'like', "%{$query}%");
                    })
                    ->latest()
                    ->limit($limit)
                    ->get();
            }

            if ($canSearchCourses) {
                $courses = Course::with('department')
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('code', 'like', "%{$query}%");
                    })
                    ->tap(fn ($courseQuery) => $this->applyCourseSearchMembershipScope($courseQuery, $user))
                    ->latest()
                    ->limit($limit)
                    ->get();
            }

            if ($canSearchStructure) {
                $universities = University::where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                })
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();

                $colleges = College::with('university')
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('code', 'like', "%{$query}%");
                    })
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();

                $departments = Department::with(['university', 'college'])
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('code', 'like', "%{$query}%");
                    })
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();

                $semesters = Semester::with('university')
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'like', "%{$query}%")
                            ->orWhere('academic_year', 'like', "%{$query}%");
                    })
                    ->orderByDesc('academic_year')
                    ->limit($limit)
                    ->get();
            }

            if ($canSearchUsers) {
                $users = User::where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                })
                    ->latest()
                    ->limit($limit)
                    ->get();
            }
        }

        $totalResults =
            $students->count() +
            $teachers->count() +
            $courses->count() +
            $universities->count() +
            $colleges->count() +
            $departments->count() +
            $semesters->count() +
            $users->count();

        return view('erp.search', compact(
            'query',
            'students',
            'teachers',
            'courses',
            'universities',
            'colleges',
            'departments',
            'semesters',
            'users',
            'totalResults',
            'canSearchStudents',
            'canSearchTeachers',
            'canSearchCourses',
            'canSearchStructure',
            'canSearchUsers'
        ));
    }

    public function searchSuggestions(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $user = $request->user();
        $limit = 5;

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $items = [];

        $canSearchStudents = $user->hasRole('super_administrator')
            || $user->hasAnyPermission(['students.view', 'students.create', 'students.update', 'students.archive']);
        $canSearchTeachers = $user->hasRole('super_administrator')
            || $user->hasPermission('teachers.view');
        $canSearchCourses = $user->hasRole('super_administrator')
            || $user->hasPermission('courses.view');
        $canSearchStructure = $user->hasAnyRole([
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'registrar',
        ]);
        $canSearchUsers = $user->hasRole('super_administrator');

        if ($canSearchStudents) {
            $students = Student::where('full_name', 'like', "%{$query}%")
                ->orWhere('student_id', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['full_name', 'student_id']);

            foreach ($students as $student) {
                $items[] = [
                    'type' => 'Student',
                    'title' => $student->full_name,
                    'meta' => $student->student_id,
                    'url' => route('search', ['q' => $student->full_name]),
                ];
            }
        }

        if ($canSearchTeachers) {
            $teachers = Teacher::where('full_name', 'like', "%{$query}%")
                ->orWhere('staff_id', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['full_name', 'staff_id']);

            foreach ($teachers as $teacher) {
                $items[] = [
                    'type' => 'Teacher',
                    'title' => $teacher->full_name,
                    'meta' => $teacher->staff_id,
                    'url' => route('search', ['q' => $teacher->full_name]),
                ];
            }
        }

        if ($canSearchCourses) {
            $courses = Course::where(function ($courseQuery) use ($query) {
                $courseQuery->where('name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%");
            })
                ->tap(fn ($courseQuery) => $this->applyCourseSearchMembershipScope($courseQuery, $user))
                ->limit($limit)
                ->get(['name', 'code']);

            foreach ($courses as $course) {
                $items[] = [
                    'type' => 'Course',
                    'title' => $course->name,
                    'meta' => $course->code,
                    'url' => route('search', ['q' => $course->name]),
                ];
            }
        }

        if ($canSearchStructure) {
            $universities = University::where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['name', 'code']);

            foreach ($universities as $university) {
                $items[] = [
                    'type' => 'University',
                    'title' => $university->name,
                    'meta' => $university->code,
                    'url' => route('search', ['q' => $university->name]),
                ];
            }
        }

        if ($canSearchUsers) {
            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit($limit)
                ->get(['name', 'email']);

            foreach ($users as $row) {
                $items[] = [
                    'type' => 'User',
                    'title' => $row->name,
                    'meta' => $row->email,
                    'url' => route('search', ['q' => $row->email]),
                ];
            }
        }

        return response()->json([
            'items' => collect($items)->take(12)->values(),
        ]);
    }

    private function applyCourseSearchMembershipScope($query, User $user): void
    {
        if ($user->hasRole('student')) {
            $studentId = Student::where('email', $user->email)->value('id');
            $query->when(
                $studentId,
                fn ($courseQuery, $id) => $courseQuery->whereHas('sections.activeEnrollments', fn ($enrollment) => $enrollment->where('student_id', $id)),
                fn ($courseQuery) => $courseQuery->whereRaw('1 = 0')
            );

            return;
        }

        if ($user->hasRole('teacher')) {
            $teacherId = Teacher::where('email', $user->email)->value('id');
            $query->when(
                $teacherId,
                fn ($courseQuery, $id) => $courseQuery->whereHas('sections', fn ($section) => $section->where('teacher_id', $id)),
                fn ($courseQuery) => $courseQuery->whereRaw('1 = 0')
            );
        }
    }

    public function accessMatrix(Request $request)
    {
        $this->requireAnyRole('super_administrator');

        $allPermissions = Permission::orderBy('name')->get(['id', 'name', 'display_name']);
        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name', 'description']);

        $modules = $allPermissions
            ->map(fn (Permission $permission) => $this->accessMatrixModuleForPermission($permission->name))
            ->unique('key')
            ->sortBy('label')
            ->values();

        $filters = [
            'role_id' => $request->integer('role_id') ?: null,
            'module' => $request->input('module', 'all'),
            'access' => in_array($request->input('access'), ['all', 'granted', 'missing'], true) ? $request->input('access') : 'all',
            'risk' => in_array($request->input('risk'), ['all', 'critical', 'high', 'standard'], true) ? $request->input('risk') : 'all',
            'mode' => $request->input('mode') === 'edit' ? 'edit' : 'view',
            'q' => trim((string) $request->input('q', '')),
        ];

        $selectedRole = $filters['role_id']
            ? $roles->firstWhere('id', $filters['role_id'])
            : null;

        if (! $selectedRole) {
            $filters['access'] = 'all';
        }

        $selectedPermissionNames = $selectedRole
            ? $selectedRole->permissions->pluck('name')->all()
            : [];

        $permissions = $allPermissions->map(function (Permission $permission) {
            $module = $this->accessMatrixModuleForPermission($permission->name);
            $risk = PermissionRiskPolicy::metadata($permission->name);
            $permission->setAttribute('module_key', $module['key']);
            $permission->setAttribute('module_label', $module['label']);
            $permission->setAttribute('risk_level', $risk['level']);
            $permission->setAttribute('risk_label', $risk['label']);
            $permission->setAttribute('risk_reason', $risk['reason']);

            return $permission;
        })->filter(function (Permission $permission) use ($filters, $selectedRole, $selectedPermissionNames) {
            if ($filters['module'] !== 'all' && $permission->getAttribute('module_key') !== $filters['module']) {
                return false;
            }

            if ($filters['q'] !== '') {
                $haystack = strtolower($permission->name.' '.$permission->display_name);
                if (! str_contains($haystack, strtolower($filters['q']))) {
                    return false;
                }
            }

            if ($filters['risk'] !== 'all' && $permission->getAttribute('risk_level') !== $filters['risk']) {
                return false;
            }

            if ($selectedRole && $filters['access'] !== 'all') {
                $isGranted = in_array($permission->name, $selectedPermissionNames, true);

                if ($filters['access'] === 'granted' && ! $isGranted) {
                    return false;
                }

                if ($filters['access'] === 'missing' && $isGranted) {
                    return false;
                }
            }

            return true;
        })->values();

        $permissionGroups = $permissions->groupBy(fn (Permission $permission) => $permission->getAttribute('module_label'));
        $editableRoles = $roles->where('name', '!=', 'super_administrator')->values();
        $roleScopes = $roles->mapWithKeys(fn (Role $role) => [$role->id => RoleAccessPolicy::scopeFor($role)]);
        $roleAccess = $permissions->mapWithKeys(function (Permission $permission) use ($roles) {
            return [$permission->id => $roles->mapWithKeys(fn (Role $role) => [
                $role->id => RoleAccessPolicy::accessFor(
                    $role,
                    $permission->name,
                    $role->permissions->contains('name', $permission->name)
                ),
            ])];
        });
        $overrideCounts = collect();

        if ($selectedRole) {
            $overrideCounts = DB::table('permission_user')
                ->join('role_user', 'role_user.user_id', '=', 'permission_user.user_id')
                ->join('users', 'users.id', '=', 'permission_user.user_id')
                ->where('role_user.role_id', $selectedRole->id)
                ->whereNull('users.deleted_at')
                ->select('permission_user.permission_id', 'permission_user.effect', DB::raw('COUNT(DISTINCT permission_user.user_id) as aggregate'))
                ->groupBy('permission_user.permission_id', 'permission_user.effect')
                ->get()
                ->groupBy('permission_id')
                ->map(fn ($rows) => [
                    'grant' => (int) ($rows->firstWhere('effect', 'grant')->aggregate ?? 0),
                    'deny' => (int) ($rows->firstWhere('effect', 'deny')->aggregate ?? 0),
                ]);
        }

        $permissionSignature = $selectedRole
            ? RoleAccessPolicy::permissionSignature($selectedRole->permissions->pluck('id'))
            : null;
        $selectedRoleImpact = $selectedRole ? [
            'users' => $selectedRole->users_count,
            'scope' => $roleScopes[$selectedRole->id],
            'direct_grants' => $overrideCounts->sum('grant'),
            'direct_denies' => $overrideCounts->sum('deny'),
        ] : null;

        $stats = [
            ['label' => 'Roles', 'value' => $roles->count(), 'detail' => $editableRoles->count().' editable'],
            ['label' => 'Permissions', 'value' => $allPermissions->count(), 'detail' => $permissions->count().' in view'],
            ['label' => 'Sensitive grants', 'value' => $roles->sum(fn (Role $role) => $role->permissions->filter(fn (Permission $permission) => PermissionRiskPolicy::isSensitive($permission->name))->count()), 'detail' => 'High and critical assignments'],
            ['label' => 'Empty roles', 'value' => $roles->filter(fn (Role $role) => $role->permissions->isEmpty())->count(), 'detail' => 'No permissions assigned'],
        ];

        return view('erp.access-matrix', compact(
            'roles',
            'allPermissions',
            'permissions',
            'permissionGroups',
            'modules',
            'filters',
            'selectedRole',
            'selectedPermissionNames',
            'stats',
            'editableRoles',
            'roleScopes',
            'roleAccess',
            'overrideCounts',
            'permissionSignature',
            'selectedRoleImpact'
        ));
    }

    public function updateRolePermissions(Request $request, Role $role, RolePermissionService $rolePermissionService)
    {
        $this->requireAnyRole('super_administrator');

        abort_if($role->name === 'super_administrator', 403, 'Super Administrator permissions are read-only.');

        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
            'permission_signature' => ['required', 'string', 'size:64'],
            'confirm_permission_change' => ['accepted'],
        ]);

        DB::transaction(function () use ($role, $validated, $rolePermissionService) {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);
            $currentPermissionIds = $lockedRole->permissions()->pluck('permissions.id');
            $currentSignature = RoleAccessPolicy::permissionSignature($currentPermissionIds);

            if (! hash_equals($currentSignature, $validated['permission_signature'])) {
                throw ValidationException::withMessages([
                    'permissions' => 'This role changed after you opened the page. Review the latest permissions before saving again.',
                ]);
            }

            $rolePermissionService->syncRolePermissions($lockedRole->id, $validated['permission_ids'] ?? []);
        });

        return redirect()
            ->route('access-matrix', ['role_id' => $role->id, 'mode' => 'edit'])
            ->with('status', 'Permissions updated for '.$role->display_name.'.');
    }

    private function accessMatrixModuleForPermission(string $permissionName): array
    {
        $prefix = str_contains($permissionName, '.')
            ? str($permissionName)->before('.')->toString()
            : $permissionName;

        return match ($prefix) {
            'students' => ['key' => 'students', 'label' => 'Students'],
            'teachers' => ['key' => 'teachers', 'label' => 'Teachers'],
            'courses' => ['key' => 'courses', 'label' => 'Courses'],
            'enrollments' => ['key' => 'enrollments', 'label' => 'Enrollment'],
            'timetable' => ['key' => 'timetable', 'label' => 'Timetable'],
            'attendance' => ['key' => 'attendance', 'label' => 'Attendance'],
            'classrooms', 'lms', 'assessments' => ['key' => 'learning', 'label' => 'Learning'],
            'marks', 'exams', 'results' => ['key' => 'results', 'label' => 'Results'],
            'finance' => ['key' => 'finance', 'label' => 'Finance'],
            'reports', 'analytics' => ['key' => 'reports', 'label' => 'Reports & Analytics'],
            'academic_setup' => ['key' => 'academic_setup', 'label' => 'Academic Setup'],
            'users', 'roles', 'permissions' => ['key' => 'users', 'label' => 'Users & Access'],
            'data', 'import', 'export' => ['key' => 'data', 'label' => 'Data Exchange'],
            default => ['key' => 'system', 'label' => 'System'],
        };
    }

    private function modulePage(string $title, string $description, array $stats, array $items)
    {
        return view('erp.module', compact('title', 'description', 'stats', 'items'));
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
