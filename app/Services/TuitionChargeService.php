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
        ?string $notes = null,
        ?float $amountOverride = null
    ): array {
        return DB::transaction(function () use ($student, $semester, $currency, $actor, $transactionDate, $dueDate, $notes, $amountOverride) {
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

            if (! $rate && $amountOverride === null) {
                $departmentName = $student->department?->name ?? 'this student\'s department';

                return $this->failure("No tuition rate configured for {$departmentName} in {$semester->academicYear->name} ({$currency}). Configure a tuition rate, or enter an amount manually.");
            }

            if ($amountOverride !== null) {
                $totalAmount = round($amountOverride, 2);
                $totalCredits = $billable->sum(fn (Enrollment $enrollment) => (float) $enrollment->courseSection->course->credits);
                $impliedRate = $totalCredits > 0 ? round($totalAmount / $totalCredits, 2) : 0.0;
                $allocated = 0.0;
                $billableList = $billable->values();
                $lineData = $billableList->map(function (Enrollment $enrollment, int $index) use ($billableList, $impliedRate, $totalAmount, &$allocated) {
                    $credits = (float) $enrollment->courseSection->course->credits;
                    $isLast = $index === $billableList->count() - 1;
                    $amount = $isLast ? round($totalAmount - $allocated, 2) : round($credits * $impliedRate, 2);
                    $allocated += $amount;

                    return [
                        'enrollment' => $enrollment,
                        'credits' => $credits,
                        'amount' => $amount,
                        'rate_per_credit' => $impliedRate,
                    ];
                });
            } else {
                $lineData = $billable->map(function (Enrollment $enrollment) use ($rate) {
                    $credits = (float) $enrollment->courseSection->course->credits;

                    return [
                        'enrollment' => $enrollment,
                        'credits' => $credits,
                        'amount' => round($credits * (float) $rate->rate_per_credit, 2),
                        'rate_per_credit' => $rate->rate_per_credit,
                    ];
                });
                $totalAmount = round($lineData->sum('amount'), 2);
            }

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
                'tuition_rate_id' => $rate?->id,
                'credits' => $line['credits'],
                'rate_per_credit' => $line['rate_per_credit'],
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

    /**
     * Bills the next installment of every active multi-semester tuition plan
     * in this semester's university that still has installments remaining.
     * Called when a new semester is created, so a student can be signed up
     * for an N-semester plan (e.g. a full 8-semester program) even though
     * only the semesters that exist today can be invoiced right away.
     */
    public function generateNextInstallmentsForSemester(Semester $semester, User $actor): Collection
    {
        return DB::transaction(function () use ($semester, $actor) {
            $semester->loadMissing('academicYear');

            $agreements = TuitionAgreement::query()
                ->whereHas('student', fn ($query) => $query->where('university_id', $semester->university_id))
                ->whereNotNull('installment_count')
                ->whereColumn('installments_generated', '<', 'installment_count')
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->get();

            $ledger = app(FinanceLedgerService::class);
            $created = collect();

            foreach ($agreements as $agreement) {
                $alreadyInvoiced = FinanceTransaction::where('tuition_agreement_id', $agreement->id)
                    ->where('semester_id', $semester->id)
                    ->where('type', 'invoice')
                    ->exists();

                if ($alreadyInvoiced) {
                    continue;
                }

                $isFinalInstallment = ($agreement->installment_count - $agreement->installments_generated) <= 1;
                $amount = $isFinalInstallment
                    ? round((float) $agreement->total_amount - ((float) $agreement->installment_amount * $agreement->installments_generated), 2)
                    : (float) $agreement->installment_amount;

                $invoicePayload = [
                    'type' => 'invoice',
                    'transaction_date' => now()->toDateString(),
                    'due_date' => $semester->end_date?->toDateString(),
                ];

                $invoice = FinanceTransaction::create([
                    'student_id' => $agreement->student_id,
                    'tuition_agreement_id' => $agreement->id,
                    'semester_id' => $semester->id,
                    'recorded_by' => $actor->id,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                    'type' => 'invoice',
                    'amount' => $amount,
                    'currency' => $agreement->currency,
                    'status' => 'approved',
                    'posting_status' => 'posted',
                    'invoice_number' => $ledger->documentNumber($invoicePayload, 'invoice_number'),
                    'reference' => substr('Tuition installment - '.$semester->name.' '.($semester->academicYear->name ?? $semester->academic_year), 0, 100),
                    'academic_year' => $semester->academicYear->name ?? $semester->academic_year,
                    'transaction_date' => $invoicePayload['transaction_date'],
                    'due_date' => $invoicePayload['due_date'],
                    'balance_after' => $ledger->balanceAfter($agreement->student_id, 'invoice', $amount, $agreement->currency),
                    'payment_status' => $ledger->paymentStatusForTransaction([
                        'type' => 'invoice',
                        'status' => 'approved',
                        'due_date' => $invoicePayload['due_date'],
                    ]),
                ]);

                $agreement->increment('installments_generated');

                $ledger->recalculateStudentBalances($agreement->student_id, $agreement->currency);
                $ledger->refreshAllocatedInvoice($invoice->fresh());
                $ledger->synchronizeFinanceHold($agreement->student()->with('user')->first());

                ActivityLog::create([
                    'log_name' => 'finance_transaction',
                    'description' => 'tuition_installment_generated',
                    'subject_type' => FinanceTransaction::class,
                    'subject_id' => $invoice->id,
                    'causer_type' => User::class,
                    'causer_id' => $actor->id,
                    'properties' => [
                        'tuition_agreement_id' => $agreement->id,
                        'student_id' => $agreement->student_id,
                        'semester_id' => $semester->id,
                        'amount' => $amount,
                        'installment_number' => $agreement->installments_generated,
                        'installment_count' => $agreement->installment_count,
                    ],
                ]);

                $created->push($invoice);
            }

            return $created;
        });
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
