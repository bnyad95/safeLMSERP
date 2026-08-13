<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\Mark;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function place(
        Student $student,
        CourseSection $section,
        string $action,
        string $enrolledAt,
        ?string $notes,
        User $actor,
        ?int $ignoredEnrollmentId = null,
        ?int $transferredFromId = null
    ): array {
        return DB::transaction(function () use ($student, $section, $action, $enrolledAt, $notes, $actor, $ignoredEnrollmentId, $transferredFromId) {
            $section = CourseSection::with(['course.department', 'stage', 'timetables', 'semester.academicYear'])
                ->lockForUpdate()
                ->findOrFail($section->id);
            $student = Student::with('currentStage')->lockForUpdate()->findOrFail($student->id);

            if ($student->status !== 'Active') {
                return $this->failure('Only active students can be enrolled or waitlisted.');
            }
            if ($section->status !== 'active') {
                return $this->failure('Students can only be added to active modules.');
            }
            if ($section->course->status !== 'active') {
                return $this->failure('Students cannot be added to an inactive course.');
            }
            if ($section->semester?->academicYear?->isLocked()) {
                return $this->failure('Enrollments in a closed academic year are read-only.');
            }
            if ($student->university_id !== $section->course->university_id) {
                return $this->failure('The student and course module must belong to the same university.');
            }
            if ((int) $student->department_id !== (int) $section->course->department_id) {
                return $this->failure('The student and course module must belong to the same department.');
            }
            if (! $student->current_stage_id) {
                return $this->failure('Assign the student current stage before enrollment or waitlisting.');
            }
            if (! $section->stage_id) {
                return $this->failure('Assign a stage to the module before enrolling students.');
            }
            if (! $student->currentStage
                || (int) $student->currentStage->department_id !== (int) $student->department_id
                || (int) $student->currentStage->university_id !== (int) $student->university_id) {
                return $this->failure('The student current stage is outside the student academic organization.');
            }

            $context = $this->registrationContext($student, $section);
            if ($context['retake_reason']) {
                return $this->failure($context['retake_reason']);
            }
            if ($message = $this->stageEligibilityMessage($student, $section, $context['is_retake'])) {
                return $this->failure($message);
            }

            $existing = Enrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('course_section_id', $section->id)
                ->lockForUpdate()
                ->first();

            if ($existing && ! $existing->trashed() && $existing->status === 'enrolled') {
                return $this->failure('This student is already enrolled in that module.');
            }
            if ($existing && ! $existing->trashed() && $existing->status === 'waitlisted' && $action === 'waitlist') {
                return $this->failure('This student is already on the module waitlist.');
            }

            $duplicateOffering = Enrollment::where('student_id', $student->id)
                ->whereIn('status', ['enrolled', 'waitlisted'])
                ->when($ignoredEnrollmentId, fn ($query) => $query->whereKeyNot($ignoredEnrollmentId))
                ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
                ->whereHas('courseSection', fn ($query) => $query
                    ->where('course_id', $section->course_id)
                    ->where('semester_id', $section->semester_id))
                ->exists();

            if ($duplicateOffering) {
                return $this->failure('The student is already enrolled or waitlisted in another group for this course and semester.');
            }

            if ($action === 'enroll') {
                if ($section->activeEnrollments()->count() >= $section->capacity) {
                    return $this->failure('This module is already at capacity. Add the student to the waitlist instead.');
                }

                if ($this->hasTimetableConflict($student, $section)) {
                    return $this->failure('This module conflicts with another class in your timetable.');
                }
            }

            if ($existing?->trashed()) {
                $existing->restore();
            }

            $previousStatus = $existing?->status;
            $enrollment = $existing ?: new Enrollment([
                'student_id' => $student->id,
                'course_section_id' => $section->id,
            ]);
            $enrollment->fill([
                'status' => $action === 'waitlist' ? 'waitlisted' : 'enrolled',
                'is_retake' => $context['is_retake'],
                'retake_from_enrollment_id' => $context['source_enrollment']?->id,
                'retake_reason' => $context['is_retake'] ? 'Failed course retake' : null,
                'enrolled_at' => $enrolledAt,
                'dropped_at' => null,
                'drop_reason' => null,
                'waitlisted_at' => $action === 'waitlist' ? now() : null,
                'transferred_from_id' => $transferredFromId,
                'notes' => $context['is_retake'] && $notes === 'Student course registration'
                    ? 'Student course registration - retake'
                    : $notes,
            ]);
            $enrollment->save();

            $this->recordEvent(
                $enrollment,
                $action === 'waitlist' ? 'waitlisted' : ($previousStatus === 'waitlisted' ? 'promoted' : 'enrolled'),
                $actor,
                $notes
            );

            if ($enrollment->status === 'enrolled' && $section->semester) {
                app(TuitionChargeService::class)->autoGenerateForSemesterPlanEnrollment($student, $section->semester, $actor);
            }

            return ['ok' => true, 'enrollment' => $enrollment, 'status' => $enrollment->status];
        });
    }

    public function drop(Enrollment $enrollment, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($enrollment, $reason, $actor) {
            $enrollment = Enrollment::with('courseSection.semester.academicYear')->lockForUpdate()->findOrFail($enrollment->id);

            if ($enrollment->courseSection?->semester?->academicYear?->isLocked()) {
                return $this->failure('Enrollments in a closed academic year are read-only.');
            }

            if (! in_array($enrollment->status, ['enrolled', 'waitlisted'], true)) {
                return $this->failure('Only enrolled or waitlisted records can be dropped.');
            }

            $previousStatus = $enrollment->status;
            $enrollment->update([
                'status' => 'dropped',
                'dropped_at' => today(),
                'drop_reason' => $reason,
            ]);
            $this->recordEvent($enrollment, $previousStatus === 'waitlisted' ? 'waitlist_removed' : 'dropped', $actor, $reason);

            return ['ok' => true, 'enrollment' => $enrollment];
        });
    }

    public function transfer(Enrollment $source, CourseSection $target, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($source, $target, $reason, $actor) {
            $source = Enrollment::with('courseSection.semester.academicYear')->lockForUpdate()->findOrFail($source->id);

            if ($source->courseSection?->semester?->academicYear?->isLocked()) {
                return $this->failure('Enrollments in a closed academic year are read-only.');
            }

            if ($source->status !== 'enrolled') {
                return $this->failure('Only active enrollments can be transferred.');
            }
            if ($source->courseSection->course_id !== $target->course_id || $source->courseSection->semester_id !== $target->semester_id) {
                return $this->failure('Transfers must stay within the same course and semester.');
            }

            $result = $this->place(
                $source->student,
                $target,
                'enroll',
                today()->toDateString(),
                $reason,
                $actor,
                $source->id,
                $source->id
            );

            if (! $result['ok']) {
                return $result;
            }

            $source->update([
                'status' => 'dropped',
                'dropped_at' => today(),
                'drop_reason' => 'Transferred: '.$reason,
            ]);
            $this->recordEvent($source, 'transferred_out', $actor, $reason, ['target_section_id' => $target->id]);
            $this->recordEvent($result['enrollment'], 'transferred_in', $actor, $reason, ['source_section_id' => $source->course_section_id]);

            return $result;
        });
    }

    public function hasTimetableConflict(Student $student, CourseSection $target): bool
    {
        $targetEntries = $target->timetables->where('status', 'scheduled');

        foreach ($targetEntries as $entry) {
            $hasConflict = Timetable::overlapping(
                $entry->day_of_week,
                substr((string) $entry->start_time, 0, 5),
                substr((string) $entry->end_time, 0, 5)
            )
                ->where('status', 'scheduled')
                ->where('course_section_id', '!=', $target->id)
                ->whereHas('courseSection.activeEnrollments', fn ($query) => $query->where('student_id', $student->id))
                ->exists();

            if ($hasConflict) {
                return true;
            }
        }

        return false;
    }

    public function registrationContext(Student $student, CourseSection $section, $priorMarks = null): array
    {
        $priorMarks ??= Mark::withTrashed()->with(['courseSection.semester'])
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
            $summerRetake = ($section->semester?->term_type ?? 'regular') === 'summer'
                && ($sameYearMark->courseSection?->semester?->term_type ?? 'regular') !== 'summer'
                && (float) $sameYearMark->final_mark < (float) config('academics.pass_mark', 50);

            return $summerRetake
                ? ['is_retake' => true, 'source_enrollment' => $this->enrollmentForMark($student, $sameYearMark), 'retake_reason' => null]
                : ['is_retake' => false, 'source_enrollment' => null, 'retake_reason' => 'You already have a published result for this course in this academic year.'];
        }

        $latestPriorMark = $priorMarks->first();
        if (! $latestPriorMark) {
            return ['is_retake' => false, 'source_enrollment' => null, 'retake_reason' => null];
        }

        if ((float) $latestPriorMark->final_mark >= (float) config('academics.pass_mark', 50)) {
            return ['is_retake' => false, 'source_enrollment' => null, 'retake_reason' => 'You already passed this course and cannot register for it again.'];
        }

        return [
            'is_retake' => true,
            'source_enrollment' => $this->enrollmentForMark($student, $latestPriorMark)
                ?? $this->latestCompletedEnrollmentForCourse($student, $section->course_id),
            'retake_reason' => null,
        ];
    }

    public function stageEligibilityMessage(Student $student, CourseSection $section, bool $isRetake): ?string
    {
        if (! $student->current_stage_id) {
            return 'Assign the student current stage before enrollment or waitlisting.';
        }
        if (! $section->stage_id) {
            return 'Assign a stage to the module before enrolling students.';
        }

        $student->loadMissing('currentStage');
        $section->loadMissing('stage');
        if (! $student->currentStage || ! $section->stage) {
            return 'The student or module stage is unavailable.';
        }

        if (! $isRetake && (int) $section->stage_id !== (int) $student->current_stage_id) {
            return 'This course is not offered for the student current stage.';
        }
        if ($isRetake && (int) $section->stage->sequence > (int) $student->currentStage->sequence) {
            return 'A retake module cannot be above the student current stage.';
        }

        return null;
    }

    private function enrollmentForMark(Student $student, Mark $mark): ?Enrollment
    {
        if (! $mark->course_section_id) {
            return null;
        }

        return Enrollment::withTrashed()
            ->where('student_id', $student->id)
            ->where('course_section_id', $mark->course_section_id)
            ->first();
    }

    private function latestCompletedEnrollmentForCourse(Student $student, int $courseId): ?Enrollment
    {
        return Enrollment::withTrashed()->with('courseSection')
            ->where('student_id', $student->id)
            ->whereIn('status', ['completed', 'dropped'])
            ->whereHas('courseSection', fn ($query) => $query->where('course_id', $courseId))
            ->latest('updated_at')
            ->first();
    }

    private function recordEvent(Enrollment $enrollment, string $action, User $actor, ?string $notes = null, array $metadata = []): void
    {
        EnrollmentEvent::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_section_id' => $enrollment->course_section_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);

        ActivityLog::create([
            'log_name' => 'enrollment',
            'description' => $action,
            'subject_type' => Enrollment::class,
            'subject_id' => $enrollment->id,
            'causer_type' => User::class,
            'causer_id' => $actor->id,
            'properties' => array_merge([
                'student_id' => $enrollment->student_id,
                'course_section_id' => $enrollment->course_section_id,
                'notes' => $notes,
            ], $metadata),
        ]);
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
