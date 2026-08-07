<?php

namespace App\Http\Controllers;

use App\Models\CourseSection;
use App\Models\Mark;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class CourseRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRole('student');

        $student = Student::with(['department', 'currentStage', 'enrollments.courseSection.course', 'enrollments.courseSection.semester'])
            ->where('email', $request->user()->email)
            ->first();

        $registeredSectionIds = collect();
        $registrations = collect();
        $availableSections = collect();
        $semesterOptions = collect();
        $gradeOptions = collect();
        $canRegister = false;
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'grade_level' => trim((string) $request->query('grade_level', '')),
        ];

        if ($student) {
            $canRegister = strtolower((string) $student->status) === 'active' && (bool) $student->current_stage_id;
            $registrations = $student->enrollments()
                ->with(['courseSection.course.department', 'courseSection.semester', 'courseSection.teacher'])
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                ->latest()
                ->get();
            $registeredSectionIds = $registrations->pluck('course_section_id');
            $registeredCourseIds = $registrations->pluck('courseSection.course_id')->filter();
            $priorMarksByCourse = Mark::withTrashed()->with(['courseSection.semester'])
                ->where('student_id', $student->id)
                ->where('visibility_status', 'published')
                ->whereNotNull('final_mark')
                ->whereHas('courseSection.semester')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('course_id');

            $eligibleSections = CourseSection::with(['course.department', 'semester', 'stage', 'teacher'])
                ->withCount(['activeEnrollments as registered_count'])
                ->where('status', 'active')
                ->whereHas('course', fn ($query) => $query->where('department_id', $student->department_id))
                ->whereNotIn('id', $registeredSectionIds);

            $availableSections = $eligibleSections
                ->when($filters['q'] !== '', fn ($query) => $query->whereHas('course', fn ($courseQuery) => $courseQuery
                    ->where(fn ($searchQuery) => $searchQuery
                        ->where('name', 'like', '%'.$filters['q'].'%')
                        ->orWhere('code', 'like', '%'.$filters['q'].'%'))))
                ->when($filters['semester_id'], fn ($query) => $query->where('semester_id', $filters['semester_id']))
                ->when($filters['grade_level'] !== '', fn ($query) => $query->where('grade_level', $filters['grade_level']))
                ->orderBy('course_id')
                ->orderBy('section_code')
                ->get()
                ->filter(fn (CourseSection $section) => $section->registered_count < $section->capacity)
                ->map(function (CourseSection $section) use ($student, $priorMarksByCourse, $registeredCourseIds) {
                    $enrollmentService = app(EnrollmentService::class);
                    $context = $enrollmentService->registrationContext($student, $section, $priorMarksByCourse->get($section->course_id, collect()));
                    $section->setAttribute('is_retake_registration', $context['is_retake']);
                    $reason = $context['retake_reason'];
                    if (! $reason && $registeredCourseIds->contains($section->course_id) && ! $context['is_retake']) {
                        $reason = 'You are already registered in this course.';
                    }
                    if (! $reason) {
                        $reason = $enrollmentService->stageEligibilityMessage($student, $section, $context['is_retake']);
                    }
                    $section->setAttribute('retake_reason', $reason);

                    return $section;
                })
                ->filter(fn (CourseSection $section) => ! $section->retake_reason)
                ->values();
            $semesterOptions = $availableSections->pluck('semester')->filter()->unique('id')->sortByDesc('academic_year')->values();
            $gradeOptions = $availableSections->pluck('grade_level')->filter()->unique()->sort()->values();
        }

        return view('course-registration.index', [
            'student' => $student,
            'registrations' => $registrations,
            'availableSections' => $availableSections,
            'semesterOptions' => $semesterOptions,
            'gradeOptions' => $gradeOptions,
            'filters' => $filters,
            'canRegister' => $canRegister,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAnyRole('student');

        $student = Student::where('email', $request->user()->email)->firstOrFail();
        if (strtolower((string) $student->status) !== 'active') {
            return back()->with('error', 'Only active students can register for courses.');
        }

        $validated = $request->validate([
            'course_section_id' => ['required', 'exists:course_sections,id'],
        ]);

        $section = CourseSection::with(['course', 'semester', 'stage'])->findOrFail($validated['course_section_id']);
        abort_unless((int) $section->course?->department_id === (int) $student->department_id, 403);
        $placement = app(EnrollmentService::class)->place(
            $student,
            $section,
            'enroll',
            now()->toDateString(),
            'Student course registration',
            $request->user()
        );

        if (! $placement['ok']) {
            return back()->with('error', $placement['message']);
        }

        $result = $placement['enrollment']->courseSection()->with('course')->firstOrFail();

        app(NotificationService::class)->notifyStudent($student, 'Course registration confirmed', ($result->course->code ?? 'Course').' / Group '.$result->section_code, [
            'type' => 'course_registration',
            'severity' => 'success',
            'action_url' => route('class-stream.show', $result),
            'data' => ['course_section_id' => $result->id],
        ]);
        app(NotificationService::class)->sendEnrollmentConfirmation($student, $result->course);

        return redirect()->route('course-registration.index')->with('success', 'Course registration completed.');
    }

}
