<?php

namespace App\Services;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
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
            $section = CourseSection::with(['course.department', 'timetables'])
                ->lockForUpdate()
                ->findOrFail($section->id);
            $student = Student::lockForUpdate()->findOrFail($student->id);

            if ($student->status !== 'Active') {
                return $this->failure('Only active students can be enrolled or waitlisted.');
            }
            if ($section->status !== 'active') {
                return $this->failure('Students can only be added to active modules.');
            }
            if ($section->course->status !== 'active') {
                return $this->failure('Students cannot be added to an inactive course.');
            }
            if ($student->university_id !== $section->course->university_id) {
                return $this->failure('The student and course module must belong to the same university.');
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
                    return $this->failure('The module timetable conflicts with the student\'s current timetable.');
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
                'enrolled_at' => $enrolledAt,
                'dropped_at' => null,
                'drop_reason' => null,
                'waitlisted_at' => $action === 'waitlist' ? now() : null,
                'transferred_from_id' => $transferredFromId,
                'notes' => $notes,
            ]);
            $enrollment->save();

            $this->recordEvent(
                $enrollment,
                $action === 'waitlist' ? 'waitlisted' : ($previousStatus === 'waitlisted' ? 'promoted' : 'enrolled'),
                $actor,
                $notes
            );

            return ['ok' => true, 'enrollment' => $enrollment, 'status' => $enrollment->status];
        });
    }

    public function drop(Enrollment $enrollment, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($enrollment, $reason, $actor) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollment->id);

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
            $source = Enrollment::with('courseSection')->lockForUpdate()->findOrFail($source->id);

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
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
