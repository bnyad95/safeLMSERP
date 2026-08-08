<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionAgreement;
use App\Models\TuitionChargeLine;
use App\Models\TuitionRate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TuitionChargeService
{
    public function generateForStudentSemester(
        Student $student,
        Semester $semester,
        string $currency,
        User $actor,
        string $transactionDate,
        ?string $dueDate = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($student, $semester, $currency, $actor, $transactionDate, $dueDate, $notes) {
            $student = Student::lockForUpdate()->findOrFail($student->id);
            $semester = Semester::with('academicYear')->lockForUpdate()->findOrFail($semester->id);

            if (! $semester->academic_year_id || ! $semester->academicYear) {
                return $this->failure('This semester is not linked to an academic year record and cannot be used for per-credit billing.');
            }
            if ($semester->academicYear->isLocked()) {
                return $this->failure('Cannot generate tuition charges for a closed or archived academic year.');
            }
            if ($student->university_id !== $semester->university_id) {
                return $this->failure('The student and semester must belong to the same university.');
            }

            $enrollments = Enrollment::with('courseSection.course')
                ->where('student_id', $student->id)
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query->where('semester_id', $semester->id))
                ->lockForUpdate()
                ->get();

            if ($enrollments->isEmpty()) {
                return $this->failure('No active enrollments found for this student in the selected semester.');
            }

            $chargedEnrollmentIds = TuitionChargeLine::whereIn('enrollment_id', $enrollments->pluck('id'))
                ->whereHas('financeTransaction', fn ($query) => $query->where('status', '!=', 'cancelled'))
                ->pluck('enrollment_id')
                ->all();

            $billable = $enrollments->reject(fn (Enrollment $enrollment) => in_array($enrollment->id, $chargedEnrollmentIds, true));

            if ($billable->isEmpty()) {
                return [
                    'ok' => true,
                    'created' => false,
                    'message' => 'All active enrollments for this student and semester have already been charged. No new tuition charge was generated.',
                    'skipped_enrollment_ids' => $chargedEnrollmentIds,
                ];
            }

            $rate = TuitionRate::where('department_id', $student->department_id)
                ->where('academic_year_id', $semester->academic_year_id)
                ->where('currency', $currency)
                ->first();

            if (! $rate) {
                $departmentName = $student->department?->name ?? 'this student\'s department';

                return $this->failure("No tuition rate configured for {$departmentName} in {$semester->academicYear->name} ({$currency}). Configure a tuition rate before generating charges.");
            }

            $lineData = $billable->map(function (Enrollment $enrollment) use ($rate) {
                $credits = (float) $enrollment->courseSection->course->credits;

                return [
                    'enrollment' => $enrollment,
                    'credits' => $credits,
                    'amount' => round($credits * (float) $rate->rate_per_credit, 2),
                ];
            });
            $totalAmount = round($lineData->sum('amount'), 2);

            $agreement = TuitionAgreement::where('student_id', $student->id)
                ->where('academic_year_id', $semester->academic_year_id)
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($agreement && $agreement->payment_method !== 'per_credit') {
                return $this->failure('A manually created tuition agreement already exists for this student and academic year. Cancel it or use the manual finance entry flow instead of per-credit generation.');
            }
            if ($agreement && $agreement->currency !== $currency) {
                return $this->failure("This tuition agreement is denominated in {$agreement->currency}; generate charges using the same currency.");
            }

            $ledger = app(FinanceLedgerService::class);

            if (! $agreement) {
                $agreement = TuitionAgreement::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $semester->academic_year_id,
                    'created_by' => $actor->id,
                    'payment_method' => 'per_credit',
                    'total_amount' => 0,
                    'currency' => $currency,
                    'status' => 'active',
                    'agreed_at' => $transactionDate,
                    'agreement_key' => $ledger->tuitionAgreementKey($student->id, $semester->academic_year_id, $semester->academicYear->name),
                ]);
            }

            $invoicePayload = [
                'type' => 'invoice',
                'transaction_date' => $transactionDate,
                'due_date' => $dueDate ?? ($semester->end_date?->toDateString()),
            ];

            $invoice = FinanceTransaction::create([
                'student_id' => $student->id,
                'tuition_agreement_id' => $agreement->id,
                'semester_id' => $semester->id,
                'recorded_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'type' => 'invoice',
                'amount' => $totalAmount,
                'currency' => $currency,
                'status' => 'approved',
                'posting_status' => 'posted',
                'invoice_number' => $ledger->documentNumber($invoicePayload, 'invoice_number'),
                'reference' => substr("Per-credit tuition - {$semester->name} {$semester->academicYear->name}", 0, 100),
                'academic_year' => $semester->academicYear->name,
                'transaction_date' => $transactionDate,
                'due_date' => $invoicePayload['due_date'],
                'notes' => $notes,
                'balance_after' => $ledger->balanceAfter($student->id, 'invoice', $totalAmount, $currency),
                'payment_status' => $ledger->paymentStatusForTransaction([
                    'type' => 'invoice',
                    'status' => 'approved',
                    'due_date' => $invoicePayload['due_date'],
                ]),
            ]);

            $lines = $lineData->map(fn (array $line) => TuitionChargeLine::create([
                'finance_transaction_id' => $invoice->id,
                'enrollment_id' => $line['enrollment']->id,
                'course_id' => $line['enrollment']->courseSection->course_id,
                'course_section_id' => $line['enrollment']->course_section_id,
                'tuition_rate_id' => $rate->id,
                'credits' => $line['credits'],
                'rate_per_credit' => $rate->rate_per_credit,
                'amount' => $line['amount'],
                'is_retake' => $line['enrollment']->is_retake,
            ]));

            $ledger->recalculateStudentBalances($student->id, $currency);
            $ledger->refreshAllocatedInvoice($invoice->fresh());
            $agreement->increment('total_amount', $totalAmount);
            $ledger->synchronizeFinanceHold($student);

            ActivityLog::create([
                'log_name' => 'finance_transaction',
                'description' => 'tuition_charge_generated',
                'subject_type' => FinanceTransaction::class,
                'subject_id' => $invoice->id,
                'causer_type' => User::class,
                'causer_id' => $actor->id,
                'properties' => [
                    'student_id' => $student->id,
                    'semester_id' => $semester->id,
                    'currency' => $currency,
                    'amount' => $totalAmount,
                    'enrollment_count' => $lines->count(),
                ],
            ]);

            return [
                'ok' => true,
                'created' => true,
                'transaction' => $invoice,
                'lines' => $lines,
                'agreement' => $agreement,
                'skipped_enrollment_ids' => $chargedEnrollmentIds,
            ];
        });
    }

    public function generateForSemesterRoster(
        Semester $semester,
        string $currency,
        User $actor,
        string $transactionDate,
        ?Collection $studentIds = null,
        ?string $dueDate = null,
        ?string $notes = null
    ): Collection {
        $targetStudentIds = $studentIds ?? Enrollment::where('status', 'enrolled')
            ->whereHas('courseSection', fn ($query) => $query->where('semester_id', $semester->id))
            ->distinct()
            ->pluck('student_id');

        return $targetStudentIds->mapWithKeys(function (int $studentId) use ($semester, $currency, $actor, $transactionDate, $dueDate, $notes) {
            $student = Student::find($studentId);

            if (! $student) {
                return [$studentId => $this->failure('Student record not found.')];
            }

            return [$studentId => $this->generateForStudentSemester($student, $semester, $currency, $actor, $transactionDate, $dueDate, $notes)];
        });
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
