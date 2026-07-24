<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\EnrollmentService;
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
            'group' => trim((string) $request->query('group', '')),
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'status' => in_array($request->query('status'), ['planned', 'active', 'closed'], true)
                ? $request->query('status')
                : '',
        ];

        $directoryQuery = $this->sectionDirectoryQuery($filters);
        $matchingSections = (clone $directoryQuery)->get();
        $sections = (clone $directoryQuery)->paginate(12)->withQueryString();
        $stats = [
            'sections' => $matchingSections->count(),
            'active' => $matchingSections->where('status', 'active')->count(),
            'students' => $matchingSections->sum('enrolled_count'),
            'waitlisted' => $matchingSections->sum('waitlisted_count'),
        ];

        return view('enrollments.index', [
            'sections' => $sections,
            'stats' => $stats,
            'classificationGroups' => $this->classifySections($matchingSections),
            'filters' => $filters,
            'colleges' => College::orderBy('name')->get(['id', 'name']),
            'departments' => Department::with('college')->orderBy('name')->get(['id', 'name', 'college_id']),
            'semesters' => Semester::orderByDesc('academic_year')->orderBy('name')->get(),
            'teachers' => Teacher::where('status', 'Active')->orderBy('full_name')->get(['id', 'full_name']),
            'gradeOptions' => CourseSection::whereNotNull('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level'),
            'groupOptions' => CourseSection::distinct()->orderBy('section_code')->pluck('section_code'),
            'abilities' => $this->abilities($request),
            'archivedCount' => CourseSection::onlyTrashed()->count(),
        ]);
    }

    public function createSection(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'teacher_q' => trim((string) $request->query('teacher_q', '')),
        ];
        $canAssignTeachers = $this->canAssignTeachers($request);

        return view('enrollments.create-section', [
            'courses' => Course::with('department.college')
                ->where('status', 'active')
                ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                    $search->where('code', 'like', "%{$filters['q']}%")
                        ->orWhere('name', 'like', "%{$filters['q']}%");
                }))
                ->when($filters['college_id'], fn ($query, $collegeId) => $query->whereHas('department', fn ($department) => $department->where('college_id', $collegeId)))
                ->when($filters['department_id'], fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                ->orderBy('code')
                ->limit(100)
                ->get(),
            'semesters' => Semester::orderByDesc('academic_year')->orderBy('name')->get(),
            'teachers' => $canAssignTeachers
                ? Teacher::where('status', 'Active')
                    ->when($filters['teacher_q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                        $search->where('full_name', 'like', "%{$filters['teacher_q']}%")
                            ->orWhere('staff_id', 'like', "%{$filters['teacher_q']}%");
                    }))
                    ->orderBy('full_name')
                    ->limit(100)
                    ->get()
                : collect(),
            'colleges' => College::orderBy('name')->get(['id', 'name']),
            'departments' => Department::with('college')->orderBy('name')->get(['id', 'name', 'college_id']),
            'filters' => $filters,
            'canAssignTeachers' => $canAssignTeachers,
        ]);
    }

    public function show(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.view');

        $courseSection->load(['course.department.college', 'semester', 'teacher', 'timetables.classroom'])
            ->loadCount([
                'activeEnrollments as enrolled_count',
                'waitlistedEnrollments as waitlisted_count',
                'assessmentItems',
                'materials',
                'timetables',
            ]);

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
            $studentCandidates = Student::with('department')
                ->where('status', 'Active')
                ->where(function ($query) use ($studentSearch) {
                    $query->where('full_name', 'like', "%{$studentSearch}%")
                        ->orWhere('student_id', 'like', "%{$studentSearch}%")
                        ->orWhere('email', 'like', "%{$studentSearch}%");
                })
                ->whereDoesntHave('enrollments', fn ($query) => $query
                    ->where('course_section_id', $courseSection->id)
                    ->whereIn('status', ['enrolled', 'waitlisted']))
                ->orderBy('full_name')
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

        return view('enrollments.show', [
            'section' => $courseSection,
            'activeEnrollments' => $activeEnrollments,
            'waitlist' => $waitlist,
            'history' => $history,
            'studentSearch' => $studentSearch,
            'studentCandidates' => $studentCandidates,
            'transferTargets' => $transferTargets,
            'abilities' => $this->abilities($request),
        ]);
    }

    public function storeSection(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');

        if ($request->filled('teacher_id') && ! $this->canAssignTeachers($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
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

        $course = Course::with('department')->findOrFail($validated['course_id']);
        $semester = Semester::findOrFail($validated['semester_id']);
        $teacher = ! empty($validated['teacher_id']) ? Teacher::findOrFail($validated['teacher_id']) : null;
        $this->validateSectionOrganization($course, $semester, $teacher);

        if ($course->status !== 'active') {
            throw ValidationException::withMessages(['course_id' => 'Only active catalog courses can receive new modules.']);
        }
        if ($teacher && $teacher->status !== 'Active') {
            throw ValidationException::withMessages(['teacher_id' => 'Only active teachers can be assigned to a module.']);
        }

        $section = CourseSection::create($validated);
        $destination = $request->boolean('open_section')
            ? route('course-sections.show', $section)
            : route('enrollments.index');

        return redirect($destination)->with('success', 'Course module created.');
    }

    public function updateSection(Request $request, CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');

        if ($request->has('teacher_id') && ! $this->canAssignTeachers($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $teacher = ! empty($validated['teacher_id']) ? Teacher::findOrFail($validated['teacher_id']) : null;
        $this->validateSectionOrganization($courseSection->course()->with('department')->firstOrFail(), $courseSection->semester, $teacher);

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

        $result = $this->enrollmentService->place(
            Student::findOrFail($validated['student_id']),
            CourseSection::findOrFail($validated['course_section_id']),
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
            $student = Student::findOrFail($studentId);
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
            $student = Student::where(function ($query) use ($row) {
                $query->when(! empty($row['student_id']), fn ($builder) => $builder->orWhere('student_id', $row['student_id']))
                    ->when(! empty($row['email']), fn ($builder) => $builder->orWhere('email', $row['email']));
            })->first();

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
        $validated = $request->validate(['drop_reason' => ['required', 'string', 'max:2000']]);
        $result = $this->enrollmentService->drop($enrollment, $validated['drop_reason'], $request->user());

        return redirect()->route('course-sections.show', $enrollment->course_section_id)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Student removed from the module.' : $result['message']);
    }

    public function promoteWaitlist(Request $request, Enrollment $enrollment)
    {
        $this->requireAnyPermission('enrollments.manage');
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
        $validated = $request->validate([
            'target_section_id' => ['required', 'exists:course_sections,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $result = $this->enrollmentService->transfer($enrollment, CourseSection::findOrFail($validated['target_section_id']), $validated['reason'], $request->user());

        return redirect()->route('course-sections.show', $enrollment->course_section_id)
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Student transferred successfully.' : $result['message']);
    }

    public function destroySection(CourseSection $courseSection)
    {
        $this->requireAnyPermission('enrollments.manage');

        if ($courseSection->status !== 'closed') {
            return back()->with('error', 'Close the module before archiving it.');
        }
        if ($courseSection->enrollments()->whereIn('status', ['enrolled', 'waitlisted'])->exists()) {
            return back()->with('error', 'Remove or transfer all enrolled and waitlisted students before archiving the module.');
        }

        $courseSection->delete();

        return redirect()->route('enrollments.index')->with('success', 'Course module archived.');
    }

    public function archived(Request $request)
    {
        $this->requireAnyPermission('enrollments.manage');
        $search = trim((string) $request->query('q', ''));
        $sections = CourseSection::onlyTrashed()
            ->with(['course.department', 'semester', 'teacher'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('section_code', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($course) => $course->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('enrollments.archived', compact('sections', 'search'));
    }

    public function restoreSection(int $sectionId)
    {
        $this->requireAnyPermission('enrollments.manage');
        $section = CourseSection::withTrashed()->findOrFail($sectionId);
        abort_unless($section->trashed(), 404);
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

    private function sectionDirectoryQuery(array $filters)
    {
        return CourseSection::with(['course.department.college', 'semester', 'teacher'])
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
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at');
    }

    private function classifySections($sections)
    {
        return $sections
            ->groupBy(fn (CourseSection $section) => $section->course->department->college->name ?? 'No college')
            ->map(fn ($collegeSections, string $collegeName) => [
                'college' => $collegeName,
                'count' => $collegeSections->count(),
                'students' => $collegeSections->sum('enrolled_count'),
                'departments' => $collegeSections
                    ->groupBy(fn (CourseSection $section) => $section->course->department->name ?? 'No department')
                    ->map(fn ($departmentSections, string $departmentName) => [
                        'department' => $departmentName,
                        'count' => $departmentSections->count(),
                        'students' => $departmentSections->sum('enrolled_count'),
                        'grades' => $departmentSections
                            ->groupBy(fn (CourseSection $section) => $section->grade_level ?: 'No stage')
                            ->map(fn ($gradeSections, string $grade) => ['grade' => $grade, 'count' => $gradeSections->count()])
                            ->values(),
                    ])
                    ->sortBy('department')
                    ->values(),
            ])
            ->sortBy('college')
            ->values();
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
