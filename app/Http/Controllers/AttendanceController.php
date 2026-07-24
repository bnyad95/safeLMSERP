<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request, Course $course)
    {
        $this->requireAttendanceRole($request);
        $this->requireAnyPermission('attendance.view', 'attendance.create', 'attendance.update');

        $section = $this->resolveSection($request, $course);
        $selectedDate = $this->attendanceDate($request);
        $students = $this->sectionStudents($course, $section);
        $attendance = Attendance::where('course_id', $course->id)
            ->when($section, fn ($query) => $query->where('course_section_id', $section->id))
            ->where('date', $selectedDate)
            ->pluck('status', 'student_id')
            ->toArray();
        $canEditAttendance = $request->user()->hasRole('super_administrator')
            || $request->user()->hasAnyPermission(['attendance.create', 'attendance.update']);

        return view('attendance.mark-attendance', compact('course', 'section', 'students', 'attendance', 'canEditAttendance', 'selectedDate'));
    }

    public function store(Request $request, Course $course)
    {
        $this->requireAttendanceRole($request);
        $this->requireAnyPermission('attendance.create', 'attendance.update');

        $section = $this->resolveSection($request, $course);
        $validated = $request->validate([
            'date' => 'nullable|date',
            'attendance' => 'required|array',
            'attendance.*' => 'in:present,absent,late,excused',
        ]);

        $allowedIds = $this->sectionStudents($course, $section)->pluck('id')->map(fn ($id) => (string) $id);
        abort_if(collect(array_keys($validated['attendance']))->diff($allowedIds)->isNotEmpty(), 422, 'Attendance contains students outside this class.');

        $result = $this->service->recordAttendance($course->id, $validated['attendance'], $section?->id, $validated['date'] ?? null);

        return response()->json([
            'success' => true,
            'message' => "Recorded attendance for {$result['created']} new and {$result['updated']} updated records",
            'data' => $result,
        ]);
    }

    public function report(Request $request, Course $course)
    {
        $this->requireAttendanceRole($request);
        $this->requireAnyPermission('attendance.view', 'attendance.create', 'attendance.update');

        $section = $this->resolveSection($request, $course);
        $stats = $this->service->getCourseAttendanceStats($course->id, $section?->id);

        return view('attendance.report', compact('course', 'section', 'stats'));
    }

    public function studentReport(Request $request, Course $course, Student $student)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole([
            'student',
            'teacher',
            'administrator',
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'registrar',
        ]), 403);
        $this->requireAnyPermission('attendance.view', 'attendance.create', 'attendance.update');

        $section = $this->resolveSection($request, $course);
        $currentStudent = $user->hasRole('student') ? Student::where('email', $user->email)->first() : null;
        abort_if($currentStudent && $currentStudent->id !== $student->id, 403);
        abort_unless($this->sectionStudents($course, $section)->contains('id', $student->id), 403);

        $report = $this->service->getAttendanceReport($course->id, $student->id, $section?->id);

        return view('attendance.student-report', compact('course', 'section', 'student', 'report'));
    }

    public function history(Request $request, Course $course)
    {
        $this->requireAttendanceRole($request);
        $this->requireAnyPermission('attendance.view', 'attendance.create', 'attendance.update');

        $section = $this->resolveSection($request, $course);
        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', now()->toDateString());
        $status = in_array($request->get('status'), array_keys(Attendance::getStatusOptions()), true) ? $request->get('status') : '';
        $studentSearch = trim((string) $request->get('student', ''));
        $attendanceQuery = Attendance::where('course_id', $course->id)
            ->when($section, fn ($query) => $query->where('course_section_id', $section->id))
            ->whereBetween('date', [$from, $to])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($studentSearch !== '', fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery
                ->where('full_name', 'like', "%{$studentSearch}%")
                ->orWhere('email', 'like', "%{$studentSearch}%")
                ->orWhere('student_id', 'like', "%{$studentSearch}%")))
            ->with('student')
            ->orderBy('date', 'desc');

        if ($request->get('export') === 'csv') {
            $records = (clone $attendanceQuery)->get();

            return response()->streamDownload(function () use ($records) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Date', 'Student ID', 'Student', 'Email', 'Status', 'Remarks']);
                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record->date?->format('Y-m-d'),
                        $record->student->student_id ?? '',
                        $record->student->full_name ?? '',
                        $record->student->email ?? '',
                        ucfirst($record->status),
                        $record->remarks ?? '',
                    ]);
                }
                fclose($handle);
            }, 'attendance-history.csv');
        }

        $attendances = $attendanceQuery
            ->paginate(50)
            ->withQueryString();

        return view('attendance.history', compact('course', 'section', 'attendances', 'from', 'to', 'status', 'studentSearch'));
    }

    private function attendanceDate(Request $request): string
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
        ]);

        return $validated['date'] ?? now()->toDateString();
    }

    private function resolveSection(Request $request, Course $course): ?CourseSection
    {
        $user = $request->user();
        $sections = CourseSection::where('course_id', $course->id);

        if ($user->hasRole('teacher') && ! $user->hasRole('super_administrator')) {
            $teacher = Teacher::where('email', $user->email)->first();
            abort_unless($teacher, 403);
            $sections->where('teacher_id', $teacher->id);
        } elseif ($user->hasRole('student')) {
            $currentStudent = Student::where('email', $user->email)->first();
            abort_unless($currentStudent, 403);
            $sections->whereHas('activeEnrollments', fn ($query) => $query->where('student_id', $currentStudent->id));
        }

        if ($request->integer('section_id')) {
            return (clone $sections)->whereKey($request->integer('section_id'))->firstOrFail();
        }

        if ($user->hasRole('super_administrator')) {
            return null;
        }

        $matchingSections = $sections->get();
        abort_if($matchingSections->isEmpty() && ! $user->hasRole('super_administrator'), 403);
        abort_if($matchingSections->count() > 1, 422, 'Choose a class section first.');

        return $matchingSections->first();
    }

    private function sectionStudents(Course $course, ?CourseSection $section)
    {
        return Student::whereHas('enrollments', fn ($query) => $query
            ->where('status', 'enrolled')
            ->when($section, fn ($query) => $query->where('course_section_id', $section->id))
            ->when(! $section, fn ($query) => $query->whereHas('courseSection', fn ($query) => $query->where('course_id', $course->id))))
            ->orderBy('full_name')
            ->get();
    }

    private function requireAttendanceRole(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole([
            'teacher',
            'administrator',
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'registrar',
        ]), 403);
    }
}
