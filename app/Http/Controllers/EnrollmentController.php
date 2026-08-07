<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollmentService) {}

    public function index(Request $request)
    {
        $this->requireAnyPermission('enrollments.view');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'grade_level' => trim((string) $request->query('grade_level', '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'status' => in_array($request->query('status'), ['enrolled', 'waitlisted', 'dropped'], true)
                ? $request->query('status')
                : '',
        ];

        $directoryQuery = $this->enrollmentDirectoryQuery($filters, $request->user());
        $enrollments = (clone $directoryQuery)->paginate(25)->withQueryString();
        $totals = (clone $directoryQuery)
            ->reorder()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'enrolled' THEN 1 ELSE 0 END) as enrolled_count")
            ->selectRaw("SUM(CASE WHEN status = 'waitlisted' THEN 1 ELSE 0 END) as waitlisted_count")
            ->selectRaw('SUM(CASE WHEN is_retake = 1 THEN 1 ELSE 0 END) as retake_count')
            ->first();
        $options = $this->directoryOptions($request->user());

        return view('enrollments.registrations', [
            'enrollments' => $enrollments,
            'stats' => [
                'total' => (int) ($totals?->total ?? 0),
                'enrolled' => (int) ($totals?->enrolled_count ?? 0),
                'waitlisted' => (int) ($totals?->waitlisted_count ?? 0),
                'retakes' => (int) ($totals?->retake_count ?? 0),
            ],
            'filters' => $filters,
            'colleges' => $options['colleges'],
            'departments' => $options['departments'],
            'semesters' => $options['semesters'],
            'gradeOptions' => $options['gradeOptions'],
            'abilities' => $this->abilities($request),
        ]);
    }

    public function moduleOfferings(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['super_administrator', 'administrator'])
                || $user->hasPermission('enrollments.view')
                || $user->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']),
            403
        );

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'grade_level' => trim((string) $request->query('grade_level', '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'group' => trim((string) $request->query('group', '')),
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'status' => in_array($request->query('status'), ['planned', 'active', 'closed'], true)
                ? $request->query('status')
                : '',
        ];

        $directoryQuery = $this->sectionDirectoryQuery($filters, $user);
        $sections = (clone $directoryQuery)->paginate(12)->withQueryString();
        $classificationGroups = $this->sectionClassificationGroups($filters, $user);
        $stats = [
            'sections' => $classificationGroups->sum('count'),
            'active' => $classificationGroups->sum('active'),
            'students' => $classificationGroups->sum('students'),
            'waitlisted' => $classificationGroups->sum('waitlisted'),
        ];

        $options = $this->directoryOptions($user);

        return view('enrollments.index', [
            'sections' => $sections,
            'stats' => $stats,
            'classificationGroups' => $classificationGroups,
            'filters' => $filters,
            'colleges' => $options['colleges'],
            'departments' => $options['departments'],
            'semesters' => $options['semesters'],
            'teachers' => $options['teachers'],
            'gradeOptions' => $options['gradeOptions'],
            'groupOptions' => $options['groupOptions'],
            'abilities' => $this->abilities($request),
            'archivedCount' => $options['archivedCount'],
        ]);
    }

    public function createSection(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'university_id' => $request->integer('university_id') ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'teacher_q' => trim((string) $request->query('teacher_q', '')),
        ];
        $canAssignTeachers = $this->canAssignTeachers($request);

        $universityQuery = University::orderBy('name');
        $academicYearQuery = AcademicYear::with('university')
            ->whereIn('status', ['upcoming', 'active'])
            ->orderByDesc('starts_on')
            ->orderByDesc('name');
        $collegeQuery = College::with('university')->orderBy('name');
        $departmentQuery = Department::with('college')->orderBy('name');
        $courseQuery = Course::with('department.college')->where('status', 'active');
        OrganizationScope::apply($courseQuery, $request->user(), 'course');
        $semesterQuery = Semester::with(['university', 'academicYear'])
            ->whereHas('academicYear', fn ($query) => $query->whereIn('status', ['upcoming', 'active']))
            ->orderByDesc('academic_year')
            ->orderBy('sequence');
        $teacherQuery = Teacher::with('department')->where('status', 'Active');
        $stageQuery = Stage::with('department.college')->orderBy('department_id')->orderBy('sequence');

        OrganizationScope::apply($universityQuery, $request->user(), 'university');
        OrganizationScope::apply($academicYearQuery, $request->user(), 'academic_year');
        OrganizationScope::apply($collegeQuery, $request->user(), 'college');
        OrganizationScope::apply($departmentQuery, $request->user(), 'department');
        OrganizationScope::apply($semesterQuery, $request->user(), 'semester');
        OrganizationScope::apply($teacherQuery, $request->user(), 'teacher');
        OrganizationScope::apply($stageQuery, $request->user(), 'stage');

        $universities = $universityQuery->get(['id', 'name', 'code', 'institution_type', 'expected_semesters_per_year']);
        $academicYears = $academicYearQuery
            ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
            ->get();
        $colleges = $collegeQuery
            ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
            ->get(['id', 'university_id', 'name', 'code']);
        $departments = $departmentQuery
            ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
            ->when($filters['college_id'], fn ($query, $collegeId) => $query->where('college_id', $collegeId))
            ->get(['id', 'university_id', 'college_id', 'name', 'code']);
        $semesters = $semesterQuery
            ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
            ->when($filters['academic_year_id'], fn ($query, $academicYearId) => $query->where('academic_year_id', $academicYearId))
            ->get();
        $stages = $stageQuery
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->get();
        $courses = $courseQuery
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('code', 'like', "%{$filters['q']}%")
                    ->orWhere('name', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
            ->when($filters['college_id'], fn ($query, $collegeId) => $query->whereHas('department', fn ($department) => $department->where('college_id', $collegeId)))
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy('code')
            ->limit(100)
            ->get();
        $teachers = $canAssignTeachers
            ? $teacherQuery
                ->when($filters['university_id'], fn ($query, $universityId) => $query->where('university_id', $universityId))
                ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                ->when($filters['teacher_q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                    $search->where('full_name', 'like', "%{$filters['teacher_q']}%")
                        ->orWhere('staff_id', 'like', "%{$filters['teacher_q']}%");
                }))
                ->orderBy('full_name')
                ->limit(100)
                ->get()
            : collect();

        $setupIssues = collect([
            $universities->isEmpty() ? 'Define an accessible university.' : null,
            $academicYears->isEmpty() ? 'Add an active or upcoming academic year.' : null,
            $semesters->isEmpty() ? 'Generate semesters for the academic year.' : null,
            $colleges->isEmpty() ? 'Define a college.' : null,
            $departments->isEmpty() ? 'Define a department.' : null,
            $courses->isEmpty() ? 'Add an active course to the Course Catalog.' : null,
            $stages->isEmpty() ? 'Define stages for the department.' : null,
        ])->filter()->values();

        return view('enrollments.create-section', [
            'universities' => $universities,
            'academicYears' => $academicYears,
            'courses' => $courses,
            'semesters' => $semesters,
            'stages' => $stages,
            'teachers' => $teachers,
            'colleges' => $colleges,
            'departments' => $departments,
            'filters' => $filters,
            'canAssignTeachers' => $canAssignTeachers,
            'setupIssues' => $setupIssues,
        ]);
    }

    public function show(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.view');
        $this->authorizeSection($courseSection, $request->user());

        $courseSection->load(['course.department.college', 'semester.university', 'stage', 'teacher', 'timetables.classroom'])
            ->loadCount([
                'activeEnrollments as enrolled_count',
                'waitlistedEnrollments as waitlisted_count',
                'assessmentItems',
                'materials',
                'timetables',
            ]);
        $courseSection->loadMissing('semester.academicYear');

        $activeEnrollments = $courseSection->activeEnrollments()
            ->with('student.department')
            ->latest('enrolled_at')
            ->paginate(15, ['*'], 'roster_page')
            ->withQueryString();
        $waitlist = $courseSection->waitlistedEnrollments()
            ->with('student.department')
            ->orderBy('waitlisted_at')
            ->paginate(10, ['*'], 'waitlist_page')
            ->withQueryString();
        $history = EnrollmentEvent::with(['student', 'actor'])
            ->where('course_section_id', $courseSection->id)
            ->orderByDesc('occurred_at')
            ->paginate(15, ['*'], 'history_page')
            ->withQueryString();
        $studentSearch = trim((string) $request->query('student_q', ''));
        $studentCandidates = collect();

        if ($studentSearch !== '' && $this->canManage($request)) {
            $studentQuery = Student::with('department')
                ->where('status', 'Active')
                ->where(function ($query) use ($studentSearch) {
                    $query->where('full_name', 'like', "%{$studentSearch}%")
                        ->orWhere('student_id', 'like', "%{$studentSearch}%")
                        ->orWhere('email', 'like', "%{$studentSearch}%");
                })
                ->whereDoesntHave('enrollments', fn ($query) => $query
                    ->where('course_section_id', $courseSection->id)
                    ->whereIn('status', ['enrolled', 'waitlisted']))
                ->orderBy('full_name');
            OrganizationScope::apply($studentQuery, $request->user(), 'student');
            $studentCandidates = $studentQuery
                ->limit(25)
                ->get();
        }

        $transferTargets = CourseSection::with(['course', 'semester'])
            ->withCount(['activeEnrollments as enrolled_count'])
            ->where('course_id', $courseSection->course_id)
            ->where('semester_id', $courseSection->semester_id)
            ->where('status', 'active')
            ->whereKeyNot($courseSection->id)
            ->orderBy('section_code')
            ->get();
        $stageQuery = Stage::where('department_id', $courseSection->course?->department_id)->orderBy('sequence');
        $teacherQuery = Teacher::where('status', 'Active')->orderBy('full_name');
        OrganizationScope::apply($stageQuery, $request->user(), 'stage');
        OrganizationScope::apply($teacherQuery, $request->user(), 'teacher');

        $abilities = $this->abilities($request);
        if ($courseSection->semester?->academicYear?->isLocked()) {
            $abilities['manage'] = false;
            $abilities['assign_teacher'] = false;
        }

        return view('enrollments.show', [
            'section' => $courseSection,
            'activeEnrollments' => $activeEnrollments,
            'waitlist' => $waitlist,
            'history' => $history,
            'studentSearch' => $studentSearch,
            'studentCandidates' => $studentCandidates,
            'transferTargets' => $transferTargets,
            'stages' => $stageQuery->get(),
            'teachers' => $teacherQuery->get(['id', 'full_name', 'staff_id']),
            'abilities' => $abilities,
        ]);
    }

    public function storeSection(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');

        if ($request->filled('teacher_id') && ! $this->canAssignTeachers($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'university_id' => ['nullable', 'exists:universities,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'stage_id' => ['required', 'exists:stages,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'section_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('course_sections')->where(fn ($query) => $query
                    ->where('course_id', $request->integer('course_id'))
                    ->where('semester_id', $request->integer('semester_id'))),
            ],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $course = $this->scopedCourse((int) $validated['course_id'], $request->user());
        $semester = $this->scopedSemester((int) $validated['semester_id'], $request->user());
        $this->ensureSemesterWritable($semester);
        $teacher = ! empty($validated['teacher_id']) ? $this->scopedTeacher((int) $validated['teacher_id'], $request->user()) : null;
        $this->validateOfferingHierarchy($course, $semester, $validated, $request->user());
        $this->validateSectionOrganization($course, $semester, $teacher);
        $stage = $this->resolveStage($course, $validated, $request->user());
        $this->validateProgramSemester($stage, $semester);
        $validated['stage_id'] = $stage?->id;
        $validated['grade_level'] = $stage?->name ?? ($validated['grade_level'] ?? null);

        if ($course->status !== 'active') {
            throw ValidationException::withMessages(['course_id' => 'Only active catalog courses can receive new modules.']);
        }
        if ($teacher && $teacher->status !== 'Active') {
            throw ValidationException::withMessages(['teacher_id' => 'Only active teachers can be assigned to a module.']);
        }

        unset($validated['university_id'], $validated['academic_year_id'], $validated['college_id'], $validated['department_id']);
        $section = CourseSection::create($validated);
        $destination = $request->boolean('open_section')
            ? route('course-sections.show', $section)
            : route('module-offerings.index');

        return redirect($destination)->with('success', 'Course module created.');
    }

    public function updateSection(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeSection($courseSection, $request->user());

        if ($request->has('teacher_id') && ! $this->canAssignTeachers($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'stage_id' => ['required', 'exists:stages,id'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($courseSection->stage_id
            && (int) $validated['stage_id'] !== (int) $courseSection->stage_id
            && (Enrollment::withTrashed()->where('course_section_id', $courseSection->id)->exists()
                || Mark::withTrashed()->where('course_section_id', $courseSection->id)->exists())) {
            return back()->with('error', 'The module stage cannot be changed after enrollment or mark records exist.');
        }
        $teacher = ! empty($validated['teacher_id']) ? $this->scopedTeacher((int) $validated['teacher_id'], $request->user()) : null;
        $course = $courseSection->course()->with('department')->firstOrFail();
        $courseSection->load('semester.academicYear');
        $this->ensureSemesterWritable($courseSection->semester);
        $this->validateSectionOrganization($course, $courseSection->semester, $teacher);
        $stage = $this->resolveStage($course, $validated, $request->user());
        $this->validateProgramSemester($stage, $courseSection->semester);
        $validated['stage_id'] = $stage?->id;
        $validated['grade_level'] = $stage?->name ?? ($validated['grade_level'] ?? null);

        if ($teacher && $teacher->status !== 'Active') {
            throw ValidationException::withMessages(['teacher_id' => 'Only active teachers can be assigned to a module.']);
        }
        if ($validated['capacity'] < $courseSection->activeEnrollments()->count()) {
            return back()->with('error', 'Capacity cannot be lower than the current enrolled student count.');
        }

        $courseSection->update($validated);

        return redirect()->route('course-sections.show', $courseSection)->with('success', 'Course module updated.');
    }

    public function enrollStudent(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_section_id' => ['required', 'exists:course_sections,id'],
            'enrolled_at' => ['required', 'date'],
            'action' => ['nullable', Rule::in(['enroll', 'waitlist'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = $this->scopedStudent((int) $validated['student_id'], $request->user());
        $section = $this->scopedSection((int) $validated['course_section_id'], $request->user());
        $result = $this->enrollmentService->place(
            $student,
            $section,
            $validated['action'] ?? 'enroll',
            $validated['enrolled_at'],
            $validated['notes'] ?? null,
            $request->user()
        );

        return $this->placementResponse($request, $validated['course_section_id'], $result);
    }

    public function bulkEnroll(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeSection($courseSection, $request->user());
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'action' => ['required', Rule::in(['enroll', 'waitlist'])],
            'enrolled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $success = 0;
        $failures = [];

        foreach (array_unique($validated['student_ids']) as $studentId) {
            $student = $this->scopedStudent((int) $studentId, $request->user());
            $result = $this->enrollmentService->place($student, $courseSection, $validated['action'], $validated['enrolled_at'], $validated['notes'] ?? null, $request->user());
            if ($result['ok']) {
                $success++;
            } else {
                $failures[] = $student->full_name.': '.$result['message'];
            }
        }

        $message = "{$success} student(s) processed successfully.";
        if ($failures) {
            $message .= ' '.count($failures).' skipped: '.collect($failures)->take(3)->implode(' ');
        }

        return redirect()->route('course-sections.show', $courseSection)
            ->with($success > 0 ? 'success' : 'error', $message);
    }

    public function importRoster(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeSection($courseSection, $request->user());
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'action' => ['required', Rule::in(['enroll', 'waitlist'])],
            'enrolled_at' => ['required', 'date'],
        ]);
        $handle = fopen($validated['csv_file']->getPathname(), 'r');
        $headers = fgetcsv($handle);

        if ($headers === false) {
            return back()->with('error', 'The roster file is empty.');
        }

        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        $success = 0;
        $failures = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($headers)) {
                $failures++;

                continue;
            }
            $row = array_combine($headers, array_map('trim', $data));
            if (empty($row['student_id']) && empty($row['email'])) {
                $failures++;

                continue;
            }
            $studentQuery = Student::where(function ($query) use ($row) {
                $query->when(! empty($row['student_id']), fn ($builder) => $builder->orWhere('student_id', $row['student_id']))
                    ->when(! empty($row['email']), fn ($builder) => $builder->orWhere('email', $row['email']));
            });
            OrganizationScope::apply($studentQuery, $request->user(), 'student');
            $student = $studentQuery->first();

            if (! $student) {
                $failures++;

                continue;
            }

            $result = $this->enrollmentService->place($student, $courseSection, $validated['action'], $validated['enrolled_at'], 'CSV roster import', $request->user());
            $result['ok'] ? $success++ : $failures++;
        }
        fclose($handle);

        return redirect()->route('course-sections.show', $courseSection)
            ->with('success', "Roster import complete: {$success} processed, {$failures} skipped.");
    }

    public function exportRoster(CourseSection $courseSection): StreamedResponse
    {
        $this->requireAnyPermission('enrollments.view');
        $this->authorizeSection($courseSection, auth()->user());
        $filename = $courseSection->course->code.'-'.$courseSection->section_code.'-roster.csv';

        return response()->streamDownload(function () use ($courseSection) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student ID', 'Name', 'Email', 'Department', 'Status', 'Enrolled At', 'Removed At', 'Removal Reason']);
            $courseSection->enrollments()->with('student.department')->orderBy('status')->chunk(200, function ($enrollments) use ($output) {
                foreach ($enrollments as $enrollment) {
                    fputcsv($output, [
                        $enrollment->student->student_id,
                        $enrollment->student->full_name,
                        $enrollment->student->email,
                        $enrollment->student->department->name ?? '',
                        $enrollment->status,
                        $enrollment->enrolled_at?->format('Y-m-d'),
                        $enrollment->dropped_at?->format('Y-m-d'),
                        $enrollment->drop_reason,
                    ]);
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function dropEnrollment(Request $request, Enrollment $enrollment)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeEnrollment($enrollment, $request->user());
        $validated = $request->validate(['drop_reason' => ['required', 'string', 'max:2000']]);
        $result = $this->enrollmentService->drop($enrollment, $validated['drop_reason'], $request->user());

        return redirect()->route('course-sections.show', $enrollment->course_section_id)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Student removed from the module.' : $result['message']);
    }

    public function promoteWaitlist(Request $request, Enrollment $enrollment)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeEnrollment($enrollment, $request->user());
        $result = $this->enrollmentService->place(
            $enrollment->student,
            $enrollment->courseSection,
            'enroll',
            today()->toDateString(),
            'Promoted from waitlist',
            $request->user()
        );

        return redirect()->route('course-sections.show', $enrollment->course_section_id)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Student promoted from the waitlist.' : $result['message']);
    }

    public function transferEnrollment(Request $request, Enrollment $enrollment)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeEnrollment($enrollment, $request->user());
        $validated = $request->validate([
            'target_section_id' => ['required', 'exists:course_sections,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $targetSection = $this->scopedSection((int) $validated['target_section_id'], $request->user());
        $result = $this->enrollmentService->transfer($enrollment, $targetSection, $validated['reason'], $request->user());

        return redirect()->route('course-sections.show', $enrollment->course_section_id)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Student transferred successfully.' : $result['message']);
    }

    public function destroySection(CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');
        $this->authorizeSection($courseSection, auth()->user());
        $courseSection->load('semester.academicYear');
        $this->ensureSemesterWritable($courseSection->semester);

        if ($courseSection->status !== 'closed') {
            return back()->with('error', 'Close the module before archiving it.');
        }
        if ($courseSection->enrollments()->whereIn('status', ['enrolled', 'waitlisted'])->exists()) {
            return back()->with('error', 'Remove or transfer all enrolled and waitlisted students before archiving the module.');
        }

        $courseSection->delete();

        return redirect()->route('module-offerings.index')->with('success', 'Course module archived.');
    }

    public function archived(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');
        $search = trim((string) $request->query('q', ''));
        $sectionsQuery = CourseSection::onlyTrashed()
            ->with(['course.department', 'semester', 'teacher'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('section_code', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($course) => $course->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('deleted_at');
        OrganizationScope::apply($sectionsQuery, $request->user(), 'section');
        $sections = $sectionsQuery
            ->paginate(15)
            ->withQueryString();

        return view('enrollments.archived', compact('sections', 'search'));
    }

    public function restoreSection(int $sectionId)
    {
        $this->requireAnyPermission('enrollments.manage');
        $query = CourseSection::withTrashed()->whereKey($sectionId);
        OrganizationScope::apply($query, request()->user(), 'section');
        $section = $query->firstOrFail();
        abort_unless($section->trashed(), 404);
        $section->load('semester.academicYear');
        $this->ensureSemesterWritable($section->semester);
        $section->restore();

        return redirect()->route('course-sections.archived')->with('success', 'Course module restored.');
    }

    private function placementResponse(Request $request, int $sectionId, array $result)
    {
        $destination = $request->boolean('return_to_section')
            ? route('course-sections.show', $sectionId)
            : route('enrollments.index');

        if (! $result['ok']) {
            return redirect($destination)->withInput()->with('error', $result['message']);
        }

        $message = $result['status'] === 'waitlisted' ? 'Student added to the waitlist.' : 'Student enrolled in module.';

        return redirect($destination)->with('success', $message);
    }

    private function sectionDirectoryQuery(array $filters, User $user)
    {
        $query = CourseSection::with(['course.department.college', 'semester.university', 'teacher', 'stage'])
            ->withCount([
                'activeEnrollments as enrolled_count',
                'waitlistedEnrollments as waitlisted_count',
            ])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('section_code', 'like', "%{$filters['q']}%")
                    ->orWhereHas('course', fn ($course) => $course
                        ->where('code', 'like', "%{$filters['q']}%")
                        ->orWhere('name', 'like', "%{$filters['q']}%"));
            }))
            ->when($filters['college_id'], fn ($query, $collegeId) => $query->whereHas('course.department', fn ($department) => $department->where('college_id', $collegeId)))
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->whereHas('course', fn ($course) => $course->where('department_id', $departmentId)))
            ->when($filters['grade_level'] !== '', fn ($query) => $query->where('grade_level', $filters['grade_level']))
            ->when($filters['semester_id'], fn ($query, $semesterId) => $query->where('semester_id', $semesterId))
            ->when($filters['group'] !== '', fn ($query) => $query->where('section_code', $filters['group']))
            ->when($filters['teacher_id'], fn ($query, $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']));
        OrganizationScope::apply($query, $user, 'section');

        return $query->orderByDesc('created_at');
    }

    private function enrollmentDirectoryQuery(array $filters, User $user)
    {
        $query = Enrollment::with([
            'student.department.college',
            'courseSection.course.department.college',
            'courseSection.semester',
            'courseSection.stage',
        ])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->whereHas('student', fn ($student) => $student
                    ->where('student_id', 'like', "%{$filters['q']}%")
                    ->orWhere('full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%"))
                    ->orWhereHas('courseSection', fn ($section) => $section
                        ->where('section_code', 'like', "%{$filters['q']}%")
                        ->orWhereHas('course', fn ($course) => $course
                            ->where('code', 'like', "%{$filters['q']}%")
                            ->orWhere('name', 'like', "%{$filters['q']}%")));
            }))
            ->when($filters['college_id'], fn ($query, $collegeId) => $query->whereHas(
                'courseSection.course.department',
                fn ($department) => $department->where('college_id', $collegeId)
            ))
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->whereHas(
                'courseSection.course',
                fn ($course) => $course->where('department_id', $departmentId)
            ))
            ->when($filters['grade_level'] !== '', fn ($query) => $query->whereHas(
                'courseSection',
                fn ($section) => $section->where('grade_level', $filters['grade_level'])
            ))
            ->when($filters['semester_id'], fn ($query, $semesterId) => $query->whereHas(
                'courseSection',
                fn ($section) => $section->where('semester_id', $semesterId)
            ))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']));
        OrganizationScope::apply($query, $user, 'section_record');

        return $query->orderByDesc('enrolled_at')->orderByDesc('id');
    }

    private function sectionClassificationGroups(array $filters, User $user)
    {
        $enrolledCounts = Enrollment::query()
            ->selectRaw('course_section_id, COUNT(*) as enrolled_count')
            ->where('status', 'enrolled')
            ->groupBy('course_section_id');

        $waitlistedCounts = Enrollment::query()
            ->selectRaw('course_section_id, COUNT(*) as waitlisted_count')
            ->where('status', 'waitlisted')
            ->groupBy('course_section_id');

        $rowsQuery = CourseSection::query()
            ->leftJoin('courses', 'course_sections.course_id', '=', 'courses.id')
            ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
            ->leftJoin('colleges', 'departments.college_id', '=', 'colleges.id')
            ->leftJoinSub($enrolledCounts, 'enrolled_counts', function ($join) {
                $join->on('enrolled_counts.course_section_id', '=', 'course_sections.id');
            })
            ->leftJoinSub($waitlistedCounts, 'waitlisted_counts', function ($join) {
                $join->on('waitlisted_counts.course_section_id', '=', 'course_sections.id');
            })
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('course_sections.section_code', 'like', "%{$filters['q']}%")
                    ->orWhere('courses.code', 'like', "%{$filters['q']}%")
                    ->orWhere('courses.name', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['college_id'], fn ($query, $collegeId) => $query->where('departments.college_id', $collegeId))
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('courses.department_id', $departmentId))
            ->when($filters['grade_level'] !== '', fn ($query) => $query->where('course_sections.grade_level', $filters['grade_level']))
            ->when($filters['semester_id'], fn ($query, $semesterId) => $query->where('course_sections.semester_id', $semesterId))
            ->when($filters['group'] !== '', fn ($query) => $query->where('course_sections.section_code', $filters['group']))
            ->when($filters['teacher_id'], fn ($query, $teacherId) => $query->where('course_sections.teacher_id', $teacherId))
            ->when($filters['status'] !== '', fn ($query) => $query->where('course_sections.status', $filters['status']));
        OrganizationScope::apply($rowsQuery, $user, 'section');
        $rows = $rowsQuery
            ->selectRaw("COALESCE(colleges.name, 'No college') as college")
            ->selectRaw("COALESCE(departments.name, 'No department') as department")
            ->selectRaw("COALESCE(NULLIF(course_sections.grade_level, ''), 'No stage') as grade")
            ->selectRaw('COUNT(course_sections.id) as modules_count')
            ->selectRaw("SUM(CASE WHEN course_sections.status = 'active' THEN 1 ELSE 0 END) as active_count")
            ->selectRaw('COALESCE(SUM(enrolled_counts.enrolled_count), 0) as students_count')
            ->selectRaw('COALESCE(SUM(waitlisted_counts.waitlisted_count), 0) as waitlisted_count')
            ->groupBy('college', 'department', 'grade')
            ->orderBy('college')
            ->orderBy('department')
            ->orderBy('grade')
            ->get();

        return $rows
            ->groupBy('college')
            ->map(fn ($collegeRows, string $collegeName) => [
                'college' => $collegeName,
                'count' => $collegeRows->sum(fn ($row) => (int) $row->modules_count),
                'active' => $collegeRows->sum(fn ($row) => (int) $row->active_count),
                'students' => $collegeRows->sum(fn ($row) => (int) $row->students_count),
                'waitlisted' => $collegeRows->sum(fn ($row) => (int) $row->waitlisted_count),
                'departments' => $collegeRows
                    ->groupBy('department')
                    ->map(fn ($departmentRows, string $departmentName) => [
                        'department' => $departmentName,
                        'count' => $departmentRows->sum(fn ($row) => (int) $row->modules_count),
                        'students' => $departmentRows->sum(fn ($row) => (int) $row->students_count),
                        'grades' => $departmentRows
                            ->map(fn ($row) => [
                                'grade' => $row->grade,
                                'count' => (int) $row->modules_count,
                            ])
                            ->values(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function directoryOptions(User $user): array
    {
        [$colleges, $departments] = $this->organizationOptions($user);
        $semesterQuery = Semester::orderByDesc('academic_year')->orderBy('name');
        $teacherQuery = Teacher::where('status', 'Active')->orderBy('full_name');
        $sectionQuery = CourseSection::query();
        $archivedQuery = CourseSection::onlyTrashed();
        OrganizationScope::apply($semesterQuery, $user, 'semester');
        OrganizationScope::apply($teacherQuery, $user, 'teacher');
        OrganizationScope::apply($sectionQuery, $user, 'section');
        OrganizationScope::apply($archivedQuery, $user, 'section');

        return [
            'colleges' => $colleges,
            'departments' => $departments,
            'semesters' => $semesterQuery->get(),
            'teachers' => $teacherQuery->get(['id', 'full_name']),
            'gradeOptions' => (clone $sectionQuery)->whereNotNull('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level'),
            'groupOptions' => (clone $sectionQuery)->distinct()->orderBy('section_code')->pluck('section_code'),
            'archivedCount' => $archivedQuery->count(),
        ];
    }

    private function organizationOptions(User $user): array
    {
        $collegeQuery = College::orderBy('name');
        $departmentQuery = Department::with('college')->orderBy('name');
        OrganizationScope::apply($collegeQuery, $user, 'college');
        OrganizationScope::apply($departmentQuery, $user, 'department');

        return [
            $collegeQuery->get(['id', 'name']),
            $departmentQuery->get(['id', 'name', 'college_id']),
        ];
    }

    private function scopedCourse(int $courseId, User $user): Course
    {
        $query = Course::with('department.college')->whereKey($courseId);
        OrganizationScope::apply($query, $user, 'course');

        return $query->firstOrFail();
    }

    private function scopedUniversity(int $universityId, User $user): University
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, $user, 'university');

        return $query->firstOrFail();
    }

    private function scopedAcademicYear(int $academicYearId, User $user): AcademicYear
    {
        $query = AcademicYear::whereKey($academicYearId);
        OrganizationScope::apply($query, $user, 'academic_year');

        return $query->firstOrFail();
    }

    private function scopedSemester(int $semesterId, User $user): Semester
    {
        $query = Semester::with(['academicYear', 'university'])->whereKey($semesterId);
        OrganizationScope::apply($query, $user, 'semester');

        return $query->firstOrFail();
    }

    private function ensureSemesterWritable(?Semester $semester): void
    {
        if (! $semester || $semester->academicYear?->isLocked()) {
            throw ValidationException::withMessages([
                'semester_id' => 'Closed and archived academic years are read-only.',
            ]);
        }
    }

    private function scopedTeacher(int $teacherId, User $user): Teacher
    {
        $query = Teacher::whereKey($teacherId);
        OrganizationScope::apply($query, $user, 'teacher');

        return $query->firstOrFail();
    }

    private function scopedStudent(int $studentId, User $user): Student
    {
        $query = Student::whereKey($studentId);
        OrganizationScope::apply($query, $user, 'student');

        return $query->firstOrFail();
    }

    private function scopedSection(int $sectionId, User $user): CourseSection
    {
        $query = CourseSection::whereKey($sectionId);
        OrganizationScope::apply($query, $user, 'section');

        return $query->firstOrFail();
    }

    private function authorizeSection(CourseSection $section, User $user): void
    {
        $query = CourseSection::withTrashed()->whereKey($section->id);
        OrganizationScope::apply($query, $user, 'section');
        abort_unless($query->exists(), 403);
    }

    private function authorizeEnrollment(Enrollment $enrollment, User $user): void
    {
        $query = Enrollment::whereKey($enrollment->id);
        OrganizationScope::apply($query, $user, 'section_record');
        abort_unless($query->exists(), 403);
    }

    private function resolveStage(Course $course, array $validated, User $user): ?Stage
    {
        if (! empty($validated['stage_id'])) {
            $query = Stage::whereKey($validated['stage_id']);
            OrganizationScope::apply($query, $user, 'stage');
            $stage = $query->firstOrFail();
            abort_unless((int) $stage->department_id === (int) $course->department_id, 422);

            return $stage;
        }

        $name = trim((string) ($validated['grade_level'] ?? ''));
        if ($name === '') {
            return null;
        }

        return Stage::firstOrCreate(
            ['department_id' => $course->department_id, 'name' => $name],
            [
                'university_id' => $course->department->university_id,
                'sequence' => ((int) Stage::where('department_id', $course->department_id)->max('sequence')) + 1,
            ]
        );
    }

    private function validateSectionOrganization(Course $course, Semester $semester, ?Teacher $teacher): void
    {
        $universityId = $course->department->university_id;
        if ($semester->university_id !== $universityId) {
            throw ValidationException::withMessages(['semester_id' => 'The selected semester and course must belong to the same university.']);
        }
        if ($teacher && $teacher->university_id !== $universityId) {
            throw ValidationException::withMessages(['teacher_id' => 'The selected teacher and course must belong to the same university.']);
        }
    }

    private function validateOfferingHierarchy(Course $course, Semester $semester, array $validated, User $user): void
    {
        $courseUniversityId = (int) $course->department->university_id;

        if (! empty($validated['university_id'])) {
            $university = $this->scopedUniversity((int) $validated['university_id'], $user);
            if ((int) $university->id !== $courseUniversityId || (int) $semester->university_id !== (int) $university->id) {
                throw ValidationException::withMessages([
                    'university_id' => 'The selected university must match the course and semester.',
                ]);
            }
        }

        if (! empty($validated['academic_year_id'])) {
            $academicYear = $this->scopedAcademicYear((int) $validated['academic_year_id'], $user);
            if ((int) $academicYear->university_id !== $courseUniversityId || (int) $semester->academic_year_id !== (int) $academicYear->id) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'The selected academic year must contain the selected semester and course university.',
                ]);
            }
        }

        if (! empty($validated['college_id']) && (int) $course->department->college_id !== (int) $validated['college_id']) {
            throw ValidationException::withMessages([
                'college_id' => 'The selected college must contain the course department.',
            ]);
        }

        if (! empty($validated['department_id']) && (int) $course->department_id !== (int) $validated['department_id']) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department must own the catalog course.',
            ]);
        }
    }

    private function validateProgramSemester(?Stage $stage, Semester $semester): void
    {
        if (! $stage) {
            throw ValidationException::withMessages([
                'stage_id' => 'Select a managed stage so the program semester can be calculated.',
            ]);
        }

        $university = $semester->university;
        if (! $university || (int) $stage->university_id !== (int) $university->id) {
            throw ValidationException::withMessages([
                'stage_id' => 'The selected stage and semester must belong to the same university.',
            ]);
        }

        $stageSequence = (int) $stage->sequence;
        if ($stageSequence < 1 || $stageSequence > $university->expectedStageCount()) {
            throw ValidationException::withMessages([
                'stage_id' => 'The selected stage is outside the university program structure.',
            ]);
        }

        $regularSemesters = $university->expectedSemesterCount();
        $semesterSequence = (int) $semester->sequence;
        $termType = $semester->term_type ?? 'regular';
        $validSequence = $termType === 'summer'
            ? $semesterSequence === $regularSemesters + 1
            : $semesterSequence >= 1 && $semesterSequence <= $regularSemesters;

        if (! $validSequence) {
            throw ValidationException::withMessages([
                'semester_id' => 'The selected semester sequence does not match the university semester structure.',
            ]);
        }
    }

    private function abilities(Request $request): array
    {
        return [
            'manage' => $this->canManage($request),
            'assign_teacher' => $this->canAssignTeachers($request),
        ];
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->hasRole('super_administrator')
            || $request->user()->hasPermission('enrollments.manage');
    }

    private function canAssignTeachers(Request $request): bool
    {
        return $request->user()->hasRole('super_administrator')
            || $request->user()->hasPermission('courses.assign_teacher');
    }
}
