<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use App\Models\Student;
use App\Models\TuitionAgreement;
use Illuminate\Support\Facades\DB;

class FinanceLedgerService
{
    public function documentNumber(array $transaction, string $column): ?string
    {
        $isInvoice = $transaction['type'] === 'invoice';

        if ($column === 'invoice_number' && ! $isInvoice) {
            return null;
        }

        if ($column === 'receipt_number' && $isInvoice) {
            return null;
        }

        if (! empty($transaction[$column])) {
            return $transaction[$column];
        }

        $prefix = $isInvoice ? 'INV' : 'RCT';
        $year = date('Y', strtotime($transaction['transaction_date']));
        $base = "{$prefix}-{$year}-";
        DB::table('finance_document_sequences')->insertOrIgnore([
            'document_type' => $prefix,
            'document_year' => (int) $year,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('finance_document_sequences')
            ->where('document_type', $prefix)
            ->where('document_year', (int) $year)
            ->lockForUpdate()
            ->first();
        $next = (int) $sequence->next_number;

        do {
            $number = $base.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (FinanceTransaction::where($column, $number)->exists());

        DB::table('finance_document_sequences')
            ->where('id', $sequence->id)
            ->update([
                'next_number' => $next,
                'updated_at' => now(),
            ]);

        return $number;
    }

    public function balanceAfter(int $studentId, string $type, float $amount, string $currency): float
    {
        $charges = FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->where('posting_status', 'posted')
            ->whereIn('type', FinanceTransaction::chargeTypes())
            ->sum('amount');
        $credits = FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->where('posting_status', 'posted')
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');
        $signedAmount = in_array($type, FinanceTransaction::creditTypes(), true) ? -$amount : $amount;

        return round($charges - $credits + $signedAmount, 2);
    }

    public function recalculateStudentBalances(int $studentId, string $currency): void
    {
        $balance = 0.0;
        FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->oldest('transaction_date')
            ->oldest()
            ->lazy(500)
            ->each(function (FinanceTransaction $transaction) use (&$balance) {
                $transaction->timestamps = false;
                if ($transaction->isPosted()) {
                    $balance += $transaction->signedAmount();
                    $transaction->balance_after = round($balance, 2);
                } else {
                    $transaction->balance_after = null;
                }
                $transaction->save();
            });
    }

    public function refreshAllocatedInvoice(FinanceTransaction $transaction): void
    {
        $invoice = $transaction->invoice;

        if (! $invoice && $transaction->type === 'invoice') {
            $invoice = $transaction;
        }

        if (! $invoice || $invoice->type !== 'invoice' || $invoice->status === 'cancelled') {
            return;
        }

        $paid = (float) $invoice->allocations()
            ->where('status', '!=', 'cancelled')
            ->where('posting_status', 'posted')
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');
        $amount = (float) $invoice->amount;
        $status = match (true) {
            $paid >= $amount => 'paid',
            // Date-only comparison: an invoice due today is not overdue until tomorrow,
            // matching paymentStatusForTransaction()'s creation-time check.
            $invoice->due_date && $invoice->due_date->lt(today()) => 'overdue',
            $paid > 0 => 'partial',
            default => 'open',
        };

        $invoice->update(['payment_status' => $status]);
        $this->refreshTuitionAgreementStatus($invoice->tuition_agreement_id);
    }

    public function paymentStatusForTransaction(array $transaction): string
    {
        if ($transaction['status'] === 'cancelled') {
            return 'cancelled';
        }

        if ($transaction['status'] === 'partial') {
            return 'partial';
        }

        if (in_array($transaction['type'], FinanceTransaction::creditTypes(), true)) {
            return in_array($transaction['status'], ['paid', 'approved'], true) ? 'paid' : 'open';
        }

        if (
            $transaction['type'] === 'invoice'
            && ! empty($transaction['due_date'])
            && strtotime((string) $transaction['due_date']) < strtotime(now()->toDateString())
        ) {
            return 'overdue';
        }

        return 'open';
    }

    public function refreshStudentInvoiceStatuses(Student $student): void
    {
        $student->financeTransactions()
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['open', 'partial', 'overdue'])
            ->lazyById(200)
            ->each(fn (FinanceTransaction $invoice) => $this->refreshAllocatedInvoice($invoice));
    }

    public function overdueInvoiceQuery(Student $student)
    {
        return $student->financeTransactions()
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'overdue');
    }

    public function synchronizeFinanceHold(?Student $student): void
    {
        if (! $student) {
            return;
        }

        $this->refreshStudentInvoiceStatuses($student);
        $account = $student->user;

        if ($account && $account->account_block_type === 'finance' && ! $this->overdueInvoiceQuery($student)->exists()) {
            $account->update([
                'account_blocked_at' => null,
                'account_blocked_by' => null,
                'account_block_reason' => null,
                'account_block_type' => null,
            ]);
        }
    }

    public function refreshTuitionAgreementStatus(?int $agreementId): void
    {
        if (! $agreementId) {
            return;
        }

        $agreement = TuitionAgreement::find($agreementId);
        if (! $agreement || $agreement->status === 'cancelled') {
            return;
        }

        $invoiceQuery = $agreement->transactions()
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled');
        $invoiceCount = (clone $invoiceQuery)->count();
        $hasUnpaid = (clone $invoiceQuery)->where('payment_status', '!=', 'paid')->exists();

        $agreement->update([
            'status' => $invoiceCount === 0 ? 'cancelled' : ($hasUnpaid ? 'active' : 'completed'),
            'agreement_key' => $invoiceCount === 0 ? null : $agreement->agreement_key,
        ]);
    }

    public function tuitionAgreementKey(int $studentId, ?int $academicYearId, ?string $academicYear): string
    {
        $yearKey = $academicYearId
            ? 'id:'.$academicYearId
            : 'name:'.strtolower(trim($academicYear ?: 'legacy'));

        return hash('sha256', $studentId.'|'.$yearKey);
    }
}
