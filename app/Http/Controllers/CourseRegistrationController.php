<?php

namespace App\Http\Controllers;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseRegistrationController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRole('student');

        $student = Student::with(['department', 'enrollments.courseSection.course', 'enrollments.courseSection.semester'])
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
            $canRegister = strtolower((string) $student->status) === 'active';
            $registrations = $student->enrollments()
                ->with(['courseSection.course.department', 'courseSection.semester', 'courseSection.teacher'])
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                ->latest()
                ->get();
            $registeredSectionIds = $registrations->pluck('course_section_id');
            $registeredCourseIds = $registrations->pluck('courseSection.course_id')->filter();

            $eligibleSections = CourseSection::with(['course.department', 'semester', 'teacher'])
                ->withCount(['activeEnrollments as registered_count'])
                ->where('status', 'active')
                ->whereHas('course', fn ($query) => $query->where('department_id', $student->department_id))
                ->whereNotIn('id', $registeredSectionIds)
                ->whereNotIn('course_id', $registeredCourseIds);

            $semesterOptions = (clone $eligibleSections)->get()
                ->pluck('semester')
                ->filter()
                ->unique('id')
                ->sortByDesc('academic_year')
                ->values();
            $gradeOptions = (clone $eligibleSections)->whereNotNull('grade_level')
                ->orderBy('grade_level')
                ->pluck('grade_level')
                ->unique()
                ->values();

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
                ->map(function (CourseSection $section) use ($student) {
                    $context = $this->registrationContext($student, $section);
                    $section->setAttribute('is_retake_registration', $context['is_retake']);
                    $section->setAttribute('retake_reason', $context['retake_reason']);

                    return $section;
                })
                ->filter(fn (CourseSection $section) => ! $section->retake_reason)
                ->values();
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

        $result = DB::transaction(function () use ($student, $validated) {
            $section = CourseSection::with('course')
                ->where('status', 'active')
                ->lockForUpdate()
                ->findOrFail($validated['course_section_id']);

            abort_unless($section->course?->department_id === $student->department_id, 403);

            $alreadyRegisteredCourse = $student->enrollments()
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query
                    ->where('course_id', $section->course_id)
                    ->whereIn('status', ['planned', 'active']))
                ->exists();

            if ($alreadyRegisteredCourse) {
                return 'You are already registered in this course.';
            }

            $context = $this->registrationContext($student, $section);
            if ($context['retake_reason']) {
                return $context['retake_reason'];
            }

            $registration = Enrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('course_section_id', $section->id)
                ->first();

            if ($section->activeEnrollments()->count() >= $section->capacity) {
                return 'This course group is already full.';
            }

            if ($registration?->trashed()) {
                $registration->restore();
            }

            Enrollment::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'course_section_id' => $section->id,
                ],
                [
                    'status' => 'enrolled',
                    'is_retake' => $context['is_retake'],
                    'retake_from_enrollment_id' => $context['source_enrollment']?->id,
                    'retake_reason' => $context['is_retake'] ? 'Failed course retake' : null,
                    'enrolled_at' => now()->toDateString(),
                    'dropped_at' => null,
                    'notes' => $context['is_retake'] ? 'Student course registration - retake' : 'Student course registration',
                ]
            );

            return $section;
        });

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        app(NotificationService::class)->notifyStudent($student, 'Course registration confirmed', ($result->course->code ?? 'Course').' / Group '.$result->section_code, [
            'type' => 'course_registration',
            'severity' => 'success',
            'action_url' => route('class-stream.show', $result),
            'data' => ['course_section_id' => $result->id],
        ]);
        app(NotificationService::class)->sendEnrollmentConfirmation($student, $result->course);

        return redirect()->route('course-registration.index')->with('success', 'Course registration completed.');
    }

    private function registrationContext(Student $student, CourseSection $section): array
    {
        $priorMarks = Mark::with(['courseSection.semester'])
            ->where('student_id', $student->id)
            ->where('course_id', $section->course_id)
            ->where('visibility_status', 'published')
            ->whereNotNull('final_mark')
            ->whereHas('courseSection.semester')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();

        $sameYearMark = $priorMarks->first(fn (Mark $mark) => $mark->courseSection?->semester?->academic_year === $section->semester?->academic_year);
        if ($sameYearMark) {
            return [
                'is_retake' => false,
                'source_enrollment' => null,
                'retake_reason' => 'You already have a published result for this course in this academic year.',
            ];
        }

        $latestPriorMark = $priorMarks->first();
        if (! $latestPriorMark) {
            return [
                'is_retake' => false,
                'source_enrollment' => null,
                'retake_reason' => null,
            ];
        }

        if ((float) $latestPriorMark->final_mark >= 50) {
            return [
                'is_retake' => false,
                'source_enrollment' => null,
                'retake_reason' => 'You already passed this course and cannot register for it again.',
            ];
        }

        return [
            'is_retake' => true,
            'source_enrollment' => $this->latestCompletedEnrollmentForCourse($student, $section->course_id),
            'retake_reason' => null,
        ];
    }

    private function latestCompletedEnrollmentForCourse(Student $student, int $courseId): ?Enrollment
    {
        return Enrollment::with(['courseSection'])
            ->where('student_id', $student->id)
            ->whereIn('status', ['completed', 'dropped'])
            ->whereHas('courseSection', fn ($query) => $query->where('course_id', $courseId))
            ->latest('updated_at')
            ->first();
    }
}
