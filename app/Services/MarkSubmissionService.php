<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Mark;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
        $marks = Mark::whereIn('id', $markIds)
            ->whereIn('submission_status', ['submitted', 'under_review'])
            ->get();

        $updated = 0;
        foreach ($marks as $mark) {
            $mark->update([
                'submission_status' => 'approved',
                'reviewer_notes' => $notes,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
            $this->logMarkApproval($mark, 'approved', $notes);
            $updated++;
        }

        return ['updated' => $updated];
    }

    public function rejectMarks(array $markIds, string $notes): array
    {
        $marks = Mark::whereIn('id', $markIds)
            ->whereIn('submission_status', ['submitted', 'under_review'])
            ->get();

        $updated = 0;
        foreach ($marks as $mark) {
            $mark->update([
                'submission_status' => 'rejected',
                'reviewer_notes' => $notes,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
            $this->logMarkApproval($mark, 'rejected', $notes);
            $updated++;
        }

        return ['updated' => $updated];
    }

    public function publishMarks(array $markIds): array
    {
        $marks = Mark::whereIn('id', $markIds)
            ->where('submission_status', 'approved')
            ->where('visibility_status', 'draft')
            ->get();

        $updated = 0;
        foreach ($marks as $mark) {
            $mark->update([
                'visibility_status' => 'published',
                'published_at' => now(),
            ]);
            $this->logMarkPublication($mark);
            app(NotificationService::class)->notifyMarkPublished($mark);
            $updated++;
        }

        return ['updated' => $updated];
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
            'properties' => json_encode([
                'mark_id' => $mark->id,
                'student_id' => $mark->student_id,
                'course_id' => $mark->course_id,
                'final_mark' => $mark->final_mark,
                'status' => $status,
            ]),
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
            'properties' => json_encode([
                'mark_id' => $mark->id,
                'status' => $status,
                'reviewer_notes' => $notes,
                'reviewed_by' => Auth::id(),
            ]),
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
            'properties' => json_encode([
                'mark_id' => $mark->id,
                'student_id' => $mark->student_id,
                'course_id' => $mark->course_id,
                'published_at' => now(),
            ]),
        ]);
    }
}
