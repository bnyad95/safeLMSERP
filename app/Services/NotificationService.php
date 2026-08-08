<?php

namespace App\Services;

use App\Mail\EnrollmentConfirmation;
use App\Mail\PerformanceWarning;
use App\Models\AppNotification;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\CalendarEvent;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyUser(User $user, string $title, ?string $body = null, array $options = []): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'student_id' => $options['student_id'] ?? null,
            'type' => $options['type'] ?? 'general',
            'severity' => $options['severity'] ?? 'info',
            'title' => $title,
            'body' => $body,
            'action_url' => $options['action_url'] ?? null,
            'data' => $options['data'] ?? null,
        ]);
    }

    public function notifyStudent(Student $student, string $title, ?string $body = null, array $options = []): AppNotification
    {
        $user = $this->userForStudent($student);

        return AppNotification::create([
            'user_id' => $user?->id,
            'student_id' => $student->id,
            'type' => $options['type'] ?? 'general',
            'severity' => $options['severity'] ?? 'info',
            'title' => $title,
            'body' => $body,
            'action_url' => $options['action_url'] ?? null,
            'data' => $options['data'] ?? null,
        ]);
    }

    public function syncAssessmentDeadline(AssessmentItem $assessmentItem): void
    {
        if (! $assessmentItem->due_at || $assessmentItem->status !== 'published') {
            CalendarEvent::where('source_type', AssessmentItem::class)
                ->where('source_id', $assessmentItem->id)
                ->delete();

            return;
        }

        $assessmentItem->loadMissing(['courseSection.course', 'courseSection.activeEnrollments.student']);

        $teacherUser = User::where('email', $assessmentItem->courseSection->teacher?->email)->first();
        if ($teacherUser) {
            CalendarEvent::updateOrCreate(
                [
                    'user_id' => $teacherUser->id,
                    'student_id' => null,
                    'source_type' => AssessmentItem::class,
                    'source_id' => $assessmentItem->id,
                ],
                [
                    'category' => 'assignment_deadline',
                    'title' => "Assessment due: {$assessmentItem->title}",
                    'description' => ($assessmentItem->courseSection->course->code ?? 'Course').' - Group '.$assessmentItem->courseSection->section_code,
                    'starts_at' => $assessmentItem->due_at,
                    'ends_at' => null,
                    'action_url' => $this->assessmentUrl($assessmentItem),
                    'data' => [
                        'assessment_id' => $assessmentItem->id,
                        'course_section_id' => $assessmentItem->course_section_id,
                    ],
                ]
            );
        }

        foreach ($assessmentItem->courseSection->activeEnrollments as $enrollment) {
            $student = $enrollment->student;

            if (! $student) {
                continue;
            }

            $user = $this->userForStudent($student);
            $title = "Assessment due: {$assessmentItem->title}";
            $description = ($assessmentItem->courseSection->course->code ?? 'Course').' - '.ucfirst($assessmentItem->type);

            CalendarEvent::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'source_type' => AssessmentItem::class,
                    'source_id' => $assessmentItem->id,
                ],
                [
                    'user_id' => $user?->id,
                    'category' => 'assignment_deadline',
                    'title' => $title,
                    'description' => $description,
                    'starts_at' => $assessmentItem->due_at,
                    'ends_at' => null,
                    'action_url' => route('assessments.index'),
                    'data' => [
                        'assessment_id' => $assessmentItem->id,
                        'course_section_id' => $assessmentItem->course_section_id,
                    ],
                ]
            );

            $this->notifyStudent($student, $title, $description, [
                'type' => 'assignment_deadline',
                'severity' => 'info',
                'action_url' => route('assessments.index'),
                'data' => ['assessment_id' => $assessmentItem->id],
            ]);
        }
    }

    public function notifyAssessmentSubmitted(AssessmentSubmission $submission): ?AppNotification
    {
        $submission->loadMissing(['student', 'assessmentItem.courseSection.course', 'assessmentItem.courseSection.teacher']);
        $assessment = $submission->assessmentItem;
        $teacherUser = User::where('email', $assessment?->courseSection?->teacher?->email)->first();

        if (! $teacherUser || ! $assessment) {
            return null;
        }

        return $this->notifyUser(
            $teacherUser,
            ($submission->wasRecentlyCreated ? 'New submission: ' : 'Submission updated: ').$assessment->title,
            ($submission->student->full_name ?? 'A student').' - '.($assessment->courseSection->course->code ?? 'Course').' / Group '.$assessment->courseSection->section_code,
            [
                'type' => 'assessment_submission',
                'severity' => 'info',
                'action_url' => $this->assessmentUrl($assessment),
                'data' => [
                    'assessment_id' => $assessment->id,
                    'submission_id' => $submission->id,
                    'course_section_id' => $assessment->course_section_id,
                ],
            ]
        );
    }

    public function notifyClassMessage(ClassMessage $message): AppNotification
    {
        $message->loadMissing(['sender', 'recipient', 'courseSection.course']);
        $classLabel = ($message->courseSection->course->code ?? 'Class').' / Group '.$message->courseSection->section_code;

        return $this->notifyUser(
            $message->recipient,
            'New class message from '.($message->sender->name ?? 'User'),
            $classLabel.($message->body ? ' - '.str($message->body)->limit(120) : ' - File attachment'),
            [
                'type' => 'class_message',
                'severity' => 'info',
                'action_url' => route('class-messages.index', [
                    'courseSection' => $message->course_section_id,
                    'recipient_id' => $message->sender_id,
                ]),
                'data' => [
                    'message_id' => $message->id,
                    'course_section_id' => $message->course_section_id,
                    'sender_id' => $message->sender_id,
                ],
            ]
        );
    }

    public function notifyPaymentDue(FinanceTransaction $transaction): void
    {
        if (! $transaction->due_date || $transaction->type !== 'invoice') {
            return;
        }

        $transaction->loadMissing('student');

        if (! $transaction->student) {
            return;
        }

        $student = $transaction->student;
        $user = $this->userForStudent($student);
        $title = 'Payment due: '.($transaction->reference ?: 'Invoice');
        $body = number_format((float) $transaction->amount, 2).' '.$transaction->currency.' due on '.$transaction->due_date->format('Y-m-d');

        CalendarEvent::updateOrCreate(
            [
                'student_id' => $student->id,
                'source_type' => FinanceTransaction::class,
                'source_id' => $transaction->id,
            ],
            [
                'user_id' => $user?->id,
                'category' => 'payment_due',
                'title' => $title,
                'description' => $body,
                'starts_at' => $transaction->due_date->startOfDay(),
                'ends_at' => null,
                'action_url' => route('student.finance'),
                'data' => ['finance_transaction_id' => $transaction->id],
            ]
        );

        $this->notifyStudent($student, $title, $body, [
            'type' => 'payment_due',
            'severity' => 'warning',
            'action_url' => route('student.finance'),
            'data' => ['finance_transaction_id' => $transaction->id],
        ]);
    }

    public function notifyTuitionChargeReminder(Student $student, $balances, ?string $message = null, ?User $sender = null): AppNotification
    {
        $balanceRows = collect($balances)
            ->filter(fn ($balance) => (float) ($balance['balance'] ?? 0) > 0)
            ->values();

        $balanceText = $balanceRows
            ->map(fn ($balance) => number_format((float) $balance['balance'], 2).' '.$balance['currency'])
            ->implode(', ');

        $body = trim((string) $message);

        if ($body !== '') {
            $body .= "\nOutstanding tuition charges: {$balanceText}.";
        } else {
            $body = "Please bring your tuition payment to accounting. Outstanding tuition charges: {$balanceText}.";
        }

        return $this->notifyStudent($student, 'Tuition payment reminder', $body, [
            'type' => 'tuition_charge_reminder',
            'severity' => 'warning',
            'action_url' => route('student.finance'),
            'data' => [
                'balances' => $balanceRows->all(),
                'sender_id' => $sender?->id,
            ],
        ]);
    }

    public function notifyAttendanceAlert(Attendance $attendance): void
    {
        if (! in_array($attendance->status, ['absent', 'late'], true)) {
            return;
        }

        $attendance->loadMissing(['student', 'course']);

        if (! $attendance->student) {
            return;
        }

        $status = ucfirst($attendance->status);
        $title = "Attendance alert: {$status}";
        $body = ($attendance->course->name ?? 'Course').' on '.$attendance->date->format('Y-m-d');

        $this->notifyStudent($attendance->student, $title, $body, [
            'type' => 'attendance_alert',
            'severity' => $attendance->status === 'absent' ? 'danger' : 'warning',
            'action_url' => route('attendance.student-report', [$attendance->course_id, $attendance->student_id]),
            'data' => ['attendance_id' => $attendance->id],
        ]);
    }

    public function notifyMarkPublished(Mark $mark): void
    {
        $mark->loadMissing(['student', 'course']);

        if (! $mark->student) {
            return;
        }

        $title = 'Marks published';
        $body = ($mark->course->name ?? 'Course').' final mark: '.($mark->final_mark ?? 'N/A');

        $this->notifyStudent($mark->student, $title, $body, [
            'type' => 'marks_published',
            'severity' => 'success',
            'action_url' => route('student-portal'),
            'data' => ['mark_id' => $mark->id],
        ]);
    }

    public function notifyMarkCorrected(Mark $mark): void
    {
        $mark->loadMissing(['student', 'course']);

        if (! $mark->student) {
            return;
        }

        $title = 'Marks corrected';
        $body = ($mark->course->name ?? 'Course').' final mark was corrected to: '.($mark->final_mark ?? 'N/A');

        $this->notifyStudent($mark->student, $title, $body, [
            'type' => 'marks_corrected',
            'severity' => 'warning',
            'action_url' => route('student-portal'),
            'data' => ['mark_id' => $mark->id],
        ]);
    }

    public function sendEnrollmentConfirmation(Student $student, Course $course): void
    {
        if (! $student->email) {
            return;
        }

        try {
            Mail::to($student->email)->queue(new EnrollmentConfirmation($student, $course));
        } catch (\Exception $e) {
            Log::warning("Failed to send enrollment confirmation to {$student->email}: ".$e->getMessage());
        }
    }

    public function sendPerformanceWarning(Student $student, string $reason, array $details = []): void
    {
        if (! $student->email) {
            return;
        }

        try {
            Mail::to($student->email)->queue(new PerformanceWarning($student, $reason, $details));
        } catch (\Exception $e) {
            Log::warning("Failed to send performance warning to {$student->email}: ".$e->getMessage());
        }
    }

    public function checkAndSendPerformanceWarnings(): array
    {
        $warnings = [];

        Student::query()
            ->select(['id', 'full_name', 'email'])
            ->orderBy('id')
            ->chunkById(500, function ($students) use (&$warnings) {
                $studentIds = $students->pluck('id');
                $studentsById = $students->keyBy('id');
                $attendanceRows = Attendance::query()
                    ->whereIn('student_id', $studentIds)
                    ->select('student_id', 'course_id')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END) as attended")
                    ->groupBy('student_id', 'course_id')
                    ->havingRaw('COUNT(*) >= ?', [5])
                    ->havingRaw("100.0 * SUM(CASE WHEN status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END) / COUNT(*) < ?", [75])
                    ->get();
                $courseNames = Course::whereIn('id', $attendanceRows->pluck('course_id')->filter()->unique())
                    ->pluck('name', 'id');

                foreach ($attendanceRows as $row) {
                    $student = $studentsById->get($row->student_id);
                    if (! $student) {
                        continue;
                    }

                    $total = (int) $row->total;
                    $attended = (int) $row->attended;
                    $percentage = ($attended / $total) * 100;
                    $courseName = $courseNames[$row->course_id] ?? 'Unknown Course';

                    $this->sendPerformanceWarning($student, 'low_attendance', [
                        'course' => $courseName,
                        'percentage' => round($percentage, 1),
                        'attended' => $attended,
                        'total' => $total,
                    ]);
                    $warnings[] = "Attendance warning sent to {$student->full_name} for course {$courseName}";
                }

                Mark::with('course')
                    ->whereIn('student_id', $studentIds)
                    ->where('visibility_status', 'published')
                    ->where('final_mark', '<', 50)
                    ->orderBy('student_id')
                    ->chunk(500, function ($lowMarks) use ($studentsById, &$warnings) {
                        foreach ($lowMarks as $mark) {
                            $student = $studentsById->get($mark->student_id);
                            if (! $student) {
                                continue;
                            }

                            $courseName = $mark->course?->name ?? 'Unknown Course';
                            $this->sendPerformanceWarning($student, 'low_mark', [
                                'course' => $courseName,
                                'mark' => $mark->final_mark,
                            ]);
                            $warnings[] = "Low mark warning sent to {$student->full_name} for course {$courseName}";
                        }
                    });
            });

        return $warnings;
    }

    private function userForStudent(Student $student): ?User
    {
        if (! $student->email) {
            return null;
        }

        return User::where('email', $student->email)->first();
    }

    private function assessmentUrl(AssessmentItem $assessmentItem): string
    {
        return route('assessments.index', [
            'section_id' => $assessmentItem->course_section_id,
            'assessment_id' => $assessmentItem->id,
        ]).'#assessment-'.$assessmentItem->id;
    }
}
