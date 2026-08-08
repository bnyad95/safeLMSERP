<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkSubmissionService
{
    public function submitMarks(array $markIds): array
    {
        $marks = Mark::whereIn('id', $markIds)->get();
        $updated = 0;
        $failed = 0;

        foreach ($marks as $mark) {
            if ($mark->final_mark > 0 && $mark->submission_status === 'draft') {
                $mark->update([
                    'submission_status' => 'submitted',
                    'submitted_at' => now(),
                ]);
                $this->logMarkSubmission($mark, 'submitted');
                $updated++;
            } else {
                $failed++;
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    public function approveMarks(array $markIds, ?string $notes = null): array
    {
        return DB::transaction(function () use ($markIds, $notes) {
            $marks = $this->marksForTransition($markIds, ['submitted', 'under_review']);

            foreach ($marks as $mark) {
                if ($mark->final_exam_entered_by && (int) $mark->final_exam_entered_by === (int) Auth::id()) {
                    throw ValidationException::withMessages([
                        'mark_ids' => 'A final-exam score must be approved by a different examination user than the person who entered it.',
                    ]);
                }

                $mark->update([
                    'submission_status' => 'approved',
                    'reviewer_notes' => $notes,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
                $this->logMarkApproval($mark, 'approved', $notes);
            }

            return ['updated' => $marks->count()];
        });
    }

    public function rejectMarks(array $markIds, string $notes): array
    {
        return DB::transaction(function () use ($markIds, $notes) {
            $marks = $this->marksForTransition($markIds, ['submitted', 'under_review']);

            foreach ($marks as $mark) {
                $mark->update([
                    'submission_status' => 'rejected',
                    'reviewer_notes' => $notes,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
                $this->logMarkApproval($mark, 'rejected', $notes);
            }

            return ['updated' => $marks->count()];
        });
    }

    public function publishMarks(array $markIds): array
    {
        return DB::transaction(function () use ($markIds) {
            $marks = $this->marksForTransition($markIds, ['approved'], true);

            foreach ($marks as $mark) {
                $mark->update([
                    'visibility_status' => 'published',
                    'published_at' => now(),
                ]);
                $this->logMarkPublication($mark);
                app(NotificationService::class)->notifyMarkPublished($mark);
            }

            return ['updated' => $marks->count()];
        });
    }

    public function assertMarkIntegrity(Mark $mark): void
    {
        $mark->loadMissing(['student', 'course.department', 'courseSection.course.department', 'courseSection.semester.academicYear']);
        $section = $mark->courseSection;
        $course = $mark->course;
        $student = $mark->student;

        if (! $section || ! $course || ! $student
            || (int) $section->course_id !== (int) $mark->course_id
            || (int) $section->course_id !== (int) $course->id
            || (int) $student->university_id !== (int) $course->department?->university_id
            || ! Enrollment::withTrashed()
                ->where('student_id', $student->id)
                ->where('course_section_id', $section->id)
                ->where('status', 'enrolled')
                ->exists()) {
            throw ValidationException::withMessages([
                'mark_ids' => 'A selected mark has an invalid student, module, course, or enrollment relationship and cannot enter the official results workflow.',
            ]);
        }

        if ($section->semester?->academicYear?->isLocked()) {
            throw ValidationException::withMessages([
                'mark_ids' => 'Marks in a closed or archived academic year are read-only.',
            ]);
        }
    }

    private function marksForTransition(array $markIds, array $statuses, bool $publishing = false)
    {
        $ids = collect($markIds)->map(fn ($id) => (int) $id)->unique()->values();
        $marks = Mark::with(['student', 'course.department', 'courseSection.course.department', 'courseSection.semester.academicYear'])
            ->whereIn('id', $ids)
            ->whereIn('submission_status', $statuses)
            ->when($publishing, fn ($query) => $query->where('visibility_status', 'draft'))
            ->lockForUpdate()
            ->get();

        if ($marks->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'mark_ids' => 'One or more marks are outside your scope or are no longer in the required workflow state.',
            ]);
        }

        $marks->each(fn (Mark $mark) => $this->assertMarkIntegrity($mark));

        return $marks;
    }

    private function logMarkSubmission(Mark $mark, string $status): void
    {
        ActivityLog::create([
            'log_name' => 'mark_submission',
            'description' => "mark_{$status}",
            'subject_type' => Mark::class,
            'subject_id' => $mark->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'properties' => [
                'mark_id' => $mark->id,
                'student_id' => $mark->student_id,
                'course_id' => $mark->course_id,
                'final_mark' => $mark->final_mark,
                'status' => $status,
            ],
        ]);
    }

    private function logMarkApproval(Mark $mark, string $status, ?string $notes = null): void
    {
        ActivityLog::create([
            'log_name' => 'mark_approval',
            'description' => "mark_{$status}",
            'subject_type' => Mark::class,
            'subject_id' => $mark->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'properties' => [
                'mark_id' => $mark->id,
                'status' => $status,
                'reviewer_notes' => $notes,
                'reviewed_by' => Auth::id(),
            ],
        ]);
    }

    private function logMarkPublication(Mark $mark): void
    {
        ActivityLog::create([
            'log_name' => 'mark_publication',
            'description' => 'mark_published',
            'subject_type' => Mark::class,
            'subject_id' => $mark->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'properties' => [
                'mark_id' => $mark->id,
                'student_id' => $mark->student_id,
                'course_id' => $mark->course_id,
                'published_at' => now(),
            ],
        ]);
    }
}
