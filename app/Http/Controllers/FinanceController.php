<?php

namespace App\Http\Controllers;

use App\Models\FinanceTransaction;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyPermission('finance.view');

        $filters = $this->financeFilters($request);
        $query = $filters['q'];
        $user = $request->user();
        $selectedStudent = null;

        if ($request->filled('student_id')) {
            $params = $request->except('student_id');

            return redirect()->route('finance.students.show', array_merge(['student' => $request->integer('student_id')], $params));
        }

        $students = Student::with(['department.college', 'university'])
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('full_name', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('student_id', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('roll_number', 'like', "%{$query}%");
                });
            })
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
            ->latest()
            ->limit($query === '' ? 8 : 25)
            ->get();

        $transactionQuery = FinanceTransaction::with(['student.department.college', 'recorder', 'approver', 'voider', 'invoice', 'originalTransaction'])
            ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
            ->latest('transaction_date')
            ->latest();
        $filteredTransactions = (clone $transactionQuery)->get();
        $transactions = $transactionQuery->paginate(20)->withQueryString();

        $scopeTransactions = FinanceTransaction::with('student.department.college')
            ->tap(fn ($builder) => $this->applyFinanceFilters($builder, array_merge($filters, [
                'type' => '',
                'status' => '',
                'payment_status' => '',
                'currency' => '',
                'academic_year' => '',
                'date_from' => '',
                'date_to' => '',
            ])))
            ->get();
        $scopeBalances = $this->balancesByCurrency($scopeTransactions);
        $filteredBalances = $this->balancesByCurrency($filteredTransactions);
        $selectedBalances = $selectedStudent ? $this->balancesByCurrency($selectedStudent->financeTransactions) : collect();
        $selectedBalance = (float) $selectedBalances->sum('balance');
        $selectedPaymentStatus = $this->paymentStatusForBalance($selectedBalance);

        return view('finance.index', [
            'query' => $query,
            'filters' => $filters,
            'students' => $students,
            'selectedStudent' => $selectedStudent,
            'transactions' => $transactions,
            'stats' => [
                ['label' => 'Total Charges', 'value' => $this->formatCurrencyTotals($scopeBalances, 'charges'), 'detail' => 'Invoices and refunds by currency'],
                ['label' => 'Total Credits', 'value' => $this->formatCurrencyTotals($scopeBalances, 'credits'), 'detail' => 'Payments, discounts, scholarships by currency'],
                ['label' => 'Open Balance', 'value' => $this->formatCurrencyTotals($scopeBalances, 'balance'), 'detail' => 'Scoped balances by currency'],
                ['label' => 'Filtered Balance', 'value' => $this->formatCurrencyTotals($filteredBalances, 'balance'), 'detail' => $selectedStudent ? 'Selected filters for this student' : 'Current filters'],
            ],
            'selectedBalances' => $selectedBalances,
            'selectedBalance' => $selectedBalance,
            'selectedPaymentStatus' => $selectedPaymentStatus,
            'invoiceOptions' => $selectedStudent ? $this->invoiceOptions($selectedStudent) : collect(),
            'filterOptions' => $this->financeFilterOptions(),
            'types' => [
                'invoice' => 'Invoice / Tuition Charge',
                'payment' => 'Payment',
                'discount' => 'Discount',
                'scholarship' => 'Scholarship',
                'refund' => 'Refund',
            ],
            'statuses' => ['pending' => 'Pending', 'paid' => 'Paid', 'partial' => 'Partial', 'approved' => 'Approved', 'cancelled' => 'Cancelled'],
            'paymentStatuses' => ['open' => 'Open', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'],
            'canCreateInvoice' => $user->hasRole('super_administrator') || $user->hasPermission('finance.create_invoice'),
            'canRecordPayment' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.record_payment', 'finance.record_expense', 'finance.refund']),
            'canApproveFinance' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.approve_payment', 'finance.approve_expense']),
            'canVoidFinance' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.refund', 'finance.approve_payment', 'finance.approve_expense']),
            'canSendTuitionReminder' => $this->canSendTuitionReminder($user),
        ]);
    }

    public function showStudent(Request $request, Student $student)
    {
        $this->authorizeStudentFinanceView($request->user());

        $filters = $this->financeFilters($request);
        $user = $request->user();

        $student->load([
            'department.college',
            'university',
            'user.accountBlocker',
            'financeTransactions.recorder',
            'financeTransactions.invoice',
        ]);

        $transactionQuery = FinanceTransaction::with(['student.department.college', 'recorder', 'approver', 'voider', 'invoice', 'originalTransaction'])
            ->where('student_id', $student->id)
            ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
            ->latest('transaction_date')
            ->latest();
        $filteredTransactions = (clone $transactionQuery)->get();
        $transactions = $transactionQuery->paginate(20)->withQueryString();
        $selectedBalances = $this->balancesByCurrency($student->financeTransactions);
        $filteredBalances = $this->balancesByCurrency($filteredTransactions);
        $selectedBalance = (float) $selectedBalances->sum('balance');
        $selectedPaymentStatus = $this->paymentStatusForBalance($selectedBalance);
        $nextDueInvoice = $this->nextDueInvoice($student);
        $paymentPlanSummary = $this->paymentPlanSummary($student->financeTransactions);

        return view('finance.show', [
            'filters' => $filters,
            'selectedStudent' => $student,
            'transactions' => $transactions,
            'stats' => [
                ['label' => 'Outstanding Tuition', 'value' => $this->formatCurrencyTotals($selectedBalances, 'balance'), 'detail' => 'Remaining balance by currency'],
                ['label' => 'Paid / Credits', 'value' => $this->formatCurrencyTotals($selectedBalances, 'credits'), 'detail' => 'Payments, discounts, scholarships'],
                ['label' => 'Next Due', 'value' => $nextDueInvoice ? number_format((float) $nextDueInvoice->amount, 2).' '.$nextDueInvoice->currency : 'No due invoices', 'detail' => $nextDueInvoice ? 'Due '.$nextDueInvoice->due_date->format('Y-m-d') : 'No open invoice due date'],
                ['label' => 'Payment Status', 'value' => ucfirst($selectedPaymentStatus), 'detail' => $paymentPlanSummary['total'] > 0 ? $paymentPlanSummary['label'] : 'No semester payment plan'],
            ],
            'selectedBalances' => $selectedBalances,
            'filteredBalanceText' => $this->formatCurrencyTotals($filteredBalances, 'balance'),
            'selectedPaymentStatus' => $selectedPaymentStatus,
            'paymentPlanSummary' => $paymentPlanSummary,
            'invoiceOptions' => $this->invoiceOptions($student),
            'filterOptions' => $this->financeFilterOptions(),
            'semesterOptions' => Semester::where('university_id', $student->university_id)
                ->orderByDesc('academic_year')
                ->orderBy('start_date')
                ->orderBy('name')
                ->get(),
            'types' => [
                'invoice' => 'Invoice / Tuition Charge',
                'payment' => 'Payment',
                'discount' => 'Discount',
                'scholarship' => 'Scholarship',
                'refund' => 'Refund',
            ],
            'statuses' => ['pending' => 'Pending', 'paid' => 'Paid', 'partial' => 'Partial', 'approved' => 'Approved', 'cancelled' => 'Cancelled'],
            'paymentStatuses' => ['open' => 'Open', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'],
            'canCreateInvoice' => $user->hasRole('super_administrator') || $user->hasPermission('finance.create_invoice'),
            'canRecordPayment' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.record_payment', 'finance.record_expense', 'finance.refund']),
            'canApproveFinance' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.approve_payment', 'finance.approve_expense']),
            'canVoidFinance' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.refund', 'finance.approve_payment', 'finance.approve_expense']),
            'canManageAccountBlock' => $this->canManageStudentAccountBlock($user),
        ]);
    }

    public function tuitionReminders(Request $request)
    {
        $this->authorizeTuitionReminder($request);

        $filters = $this->financeFilters($request);
        if (! in_array($filters['payment_status'], ['', 'open', 'partial', 'overdue'], true)) {
            $filters['payment_status'] = '';
        }
        $students = $this->tuitionReminderStudents($filters);
        $reminderRows = $students->map(function (Student $student) {
            $balances = $this->positiveBalances($this->balancesByCurrency($student->financeTransactions));

            return [
                'student' => $student,
                'balances' => $balances,
                'balanceText' => $balances
                    ->map(fn ($balance) => number_format((float) $balance['balance'], 2).' '.$balance['currency'])
                    ->implode(' / '),
                'oldestDueDate' => $student->financeTransactions
                    ->where('type', 'invoice')
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('payment_status', ['open', 'partial', 'overdue'])
                    ->filter(fn ($transaction) => $transaction->due_date)
                    ->min('due_date'),
            ];
        });
        $balanceTotals = $this->sumBalanceRows($reminderRows->pluck('balances')->flatten(1));

        return view('finance.tuition-reminders', [
            'filters' => $filters,
            'filterOptions' => $this->financeFilterOptions(),
            'reminderRows' => $reminderRows,
            'stats' => [
                ['label' => 'Students In View', 'value' => (string) $reminderRows->count(), 'detail' => 'Filtered students with unpaid tuition'],
                ['label' => 'Outstanding Tuition', 'value' => $this->formatCurrencyTotals($balanceTotals, 'balance'), 'detail' => 'Open balances by currency'],
                ['label' => 'Reminder Scope', 'value' => $filters['q'] !== '' ? 'Filtered' : 'All unpaid', 'detail' => 'Use filters to narrow recipients'],
            ],
            'paymentStatuses' => ['open' => 'Open', 'partial' => 'Partial', 'overdue' => 'Overdue'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeFinanceEntry($request->input('type'));

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'invoice_transaction_id' => ['nullable', 'exists:finance_transactions,id'],
            'type' => ['required', 'in:invoice,payment,discount,scholarship,refund'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', 'in:IQD,USD'],
            'status' => ['required', 'in:pending,paid,partial,approved,cancelled'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:finance_transactions,invoice_number'],
            'receipt_number' => ['nullable', 'string', 'max:100', 'unique:finance_transactions,receipt_number'],
            'reference' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'payment_plan' => ['nullable', 'in:full,semester'],
            'semester_ids' => ['nullable', 'array'],
            'semester_ids.*' => ['integer', 'distinct', 'exists:semesters,id'],
            'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['invoice_transaction_id'] = $this->validatedInvoiceAllocation($validated);
        $validated['recorded_by'] = $request->user()->id;
        $validated['invoice_number'] = $this->documentNumber($validated, 'invoice_number');
        $validated['receipt_number'] = $this->documentNumber($validated, 'receipt_number');
        $validated['balance_after'] = $this->balanceAfter($validated['student_id'], $validated['type'], (float) $validated['amount'], $validated['currency']);
        $validated['payment_status'] = $this->paymentStatusForTransaction($validated);

        $transactions = DB::transaction(function () use ($validated) {
            $transactions = $this->createFinanceTransactions($validated);

            if ($transactions->isNotEmpty()) {
                $firstTransaction = $transactions->first();
                $this->recalculateStudentBalances((int) $firstTransaction->student_id, $firstTransaction->currency);
                $transactions->each(fn (FinanceTransaction $transaction) => $this->refreshAllocatedInvoice($transaction->fresh()));
            }

            return $transactions;
        });

        $transactions->each(fn (FinanceTransaction $transaction) => app(NotificationService::class)->notifyPaymentDue($transaction));

        return redirect()
            ->route('finance.students.show', $validated['student_id'])
            ->with('success', $this->financeRecordSuccessMessage($transactions));
    }

    public function approve(Request $request, FinanceTransaction $financeTransaction)
    {
        $this->authorizeFinanceApproval($financeTransaction);
        abort_if($financeTransaction->status === 'cancelled', 422);

        DB::transaction(function () use ($request, $financeTransaction) {
            $financeTransaction->update([
                'status' => 'approved',
                'payment_status' => $this->paymentStatusForTransaction([
                    'type' => $financeTransaction->type,
                    'status' => 'approved',
                    'due_date' => $financeTransaction->due_date,
                ]),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $this->refreshAllocatedInvoice($financeTransaction);
        });

        return redirect()
            ->route('finance.students.show', $financeTransaction->student_id)
            ->with('success', 'Finance record approved.');
    }

    public function void(Request $request, FinanceTransaction $financeTransaction)
    {
        $this->authorizeFinanceVoid($financeTransaction);
        abort_if($financeTransaction->status === 'cancelled' || $financeTransaction->original_transaction_id, 422);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $financeTransaction, $validated) {
            $reversalType = $this->reversalType($financeTransaction->type);
            $reversal = FinanceTransaction::create([
                'student_id' => $financeTransaction->student_id,
                'original_transaction_id' => $financeTransaction->id,
                'recorded_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'type' => $reversalType,
                'amount' => $financeTransaction->amount,
                'balance_after' => $this->balanceAfter($financeTransaction->student_id, $reversalType, (float) $financeTransaction->amount, $financeTransaction->currency),
                'currency' => $financeTransaction->currency,
                'status' => 'approved',
                'payment_status' => 'paid',
                'receipt_number' => $this->documentNumber([
                    'type' => $reversalType,
                    'transaction_date' => now()->toDateString(),
                    'receipt_number' => null,
                ], 'receipt_number'),
                'reference' => 'VOID-'.$financeTransaction->documentNumber(),
                'academic_year' => $financeTransaction->academic_year,
                'transaction_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? 'Reversal for '.$financeTransaction->documentNumber(),
            ]);

            $financeTransaction->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'voided_by' => $request->user()->id,
                'voided_at' => now(),
            ]);

            $this->recalculateStudentBalances((int) $financeTransaction->student_id, $financeTransaction->currency);
            $this->refreshAllocatedInvoice($financeTransaction);
            $this->refreshAllocatedInvoice($reversal);
        });

        return redirect()
            ->route('finance.students.show', $financeTransaction->student_id)
            ->with('success', 'Finance record voided with a reversal entry.');
    }

    public function blockStudentAccount(Request $request, Student $student)
    {
        $this->authorizeStudentAccountBlock($request);

        $student->load(['user.roles', 'financeTransactions']);
        $account = $student->user;
        abort_unless($account, 404, 'Student login account was not found.');
        abort_unless($account->roles()->where('name', 'student')->exists(), 403, 'Only student login accounts can be blocked from finance.');

        $balances = $this->positiveBalances($this->balancesByCurrency($student->financeTransactions));
        if ($balances->isEmpty()) {
            return redirect()
                ->route('finance.students.show', $student)
                ->with('error', 'This student has no unpaid tuition balance to block.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $account->update([
            'account_blocked_at' => now(),
            'account_blocked_by' => $request->user()->id,
            'account_block_reason' => $validated['reason'] ?: 'Blocked by finance because of unpaid tuition.',
        ]);

        return redirect()
            ->route('finance.students.show', $student)
            ->with('success', 'Student account blocked until the tuition balance is resolved.');
    }

    public function unblockStudentAccount(Request $request, Student $student)
    {
        $this->authorizeStudentAccountBlock($request);

        $student->load('user');
        $account = $student->user;
        abort_unless($account, 404, 'Student login account was not found.');

        $account->update([
            'account_blocked_at' => null,
            'account_blocked_by' => null,
            'account_block_reason' => null,
        ]);

        return redirect()
            ->route('finance.students.show', $student)
            ->with('success', 'Student account unblocked.');
    }

    public function sendTuitionReminders(Request $request)
    {
        $this->authorizeTuitionReminder($request);

        $validated = $request->validate([
            'scope' => ['required', 'in:selected,selected_students,filtered'],
            'student_id' => ['nullable', 'exists:students,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['scope'] === 'selected' && empty($validated['student_id'])) {
            return $this->redirectToFinance($request)->with('error', 'Select a student before sending a tuition reminder.');
        }

        if ($validated['scope'] === 'selected_students' && empty($validated['student_ids'])) {
            return $this->redirectToFinance($request)->with('error', 'Choose at least one student before sending a tuition reminder.');
        }

        $studentIds = match ($validated['scope']) {
            'selected' => [(int) $validated['student_id']],
            'selected_students' => array_map('intval', $validated['student_ids'] ?? []),
            default => [],
        };

        $filters = $this->financeFilters($request);
        $students = $this->tuitionReminderStudents(
            $filters,
            $studentIds
        );

        if ($students->isEmpty()) {
            return $this->redirectToFinance($request)->with('error', 'No unpaid tuition charges were found for the selected scope.');
        }

        $notificationService = app(NotificationService::class);
        $sent = 0;

        foreach ($students as $student) {
            $balances = $this->positiveBalances($this->balancesByCurrency($student->financeTransactions));

            if ($balances->isEmpty()) {
                continue;
            }

            $notificationService->notifyTuitionChargeReminder($student, $balances, $validated['message'] ?? null, $request->user());
            $sent++;
        }

        if ($sent === 0) {
            return $this->redirectToFinance($request)->with('error', 'No unpaid tuition charges were found for the selected scope.');
        }

        return $this->redirectToFinance($request)->with('success', "Tuition reminder sent to {$sent} student".($sent === 1 ? '.' : 's.'));
    }

    public function studentFinance(Request $request)
    {
        $this->requireAnyRole('student');

        $student = Student::with(['department', 'university'])
            ->where('email', $request->user()->email)
            ->first();
        $transactions = collect();
        $balances = collect();

        if ($student) {
            $transactions = $student->financeTransactions()
                ->with(['invoice', 'originalTransaction'])
                ->oldest('transaction_date')
                ->oldest()
                ->get();
            $balances = $this->balancesByCurrency($transactions);
        }

        return view('finance.student', [
            'student' => $student,
            'transactions' => $transactions,
            'balances' => $balances,
            'paymentStatus' => $this->paymentStatusForBalance((float) $balances->sum('balance')),
        ]);
    }

    public function statement(Student $student)
    {
        $this->authorizeStudentFinanceView(auth()->user());

        $student->load(['department', 'university']);
        $transactions = $student->financeTransactions()
            ->with(['recorder', 'invoice'])
            ->oldest('transaction_date')
            ->oldest()
            ->get();

        $balances = $this->balancesByCurrency($transactions);

        return view('finance.statement', [
            'student' => $student,
            'transactions' => $transactions,
            'balances' => $balances,
            'charges' => (float) $balances->sum('charges'),
            'credits' => (float) $balances->sum('credits'),
            'balance' => (float) $balances->sum('balance'),
            'paymentStatus' => $this->paymentStatusForBalance((float) $balances->sum('balance')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->financeFilters($request);
        $studentId = $request->integer('student_id') ?: null;

        if ($studentId) {
            $this->authorizeStudentFinanceView($request->user());
        } else {
            $this->requireAnyPermission('finance.view');
        }

        $fileName = $studentId ? "student-{$studentId}-finance.csv" : 'finance-transactions.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($studentId, $filters) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Date',
                'Student ID',
                'Student Name',
                'Type',
                'Invoice Number',
                'Receipt Number',
                'Allocated Invoice',
                'Reference',
                'Amount',
                'Currency',
                'Status',
                'Payment Status',
                'Balance After',
                'Due Date',
                'Academic Year',
                'Recorded By',
                'Approved By',
                'Voided At',
                'Notes',
            ]);

            FinanceTransaction::with(['student', 'recorder', 'approver', 'invoice'])
                ->when($studentId, fn ($builder) => $builder->where('student_id', $studentId))
                ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
                ->oldest('transaction_date')
                ->oldest()
                ->chunk(200, function ($transactions) use ($output) {
                    foreach ($transactions as $transaction) {
                        fputcsv($output, [
                            $transaction->transaction_date?->format('Y-m-d'),
                            $transaction->student->student_id ?? '',
                            $transaction->student->full_name ?? '',
                            $transaction->type,
                            $transaction->invoice_number,
                            $transaction->receipt_number,
                            $transaction->invoice?->documentNumber(),
                            $transaction->reference,
                            $transaction->amount,
                            $transaction->currency,
                            $transaction->status,
                            $transaction->payment_status,
                            $transaction->balance_after,
                            $transaction->due_date?->format('Y-m-d'),
                            $transaction->academic_year,
                            $transaction->recorder->name ?? '',
                            $transaction->approver->name ?? '',
                            $transaction->voided_at?->format('Y-m-d H:i'),
                            $transaction->notes,
                        ]);
                    }
                });

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function financeFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'type' => in_array($request->input('type'), ['invoice', 'payment', 'discount', 'scholarship', 'refund'], true) ? $request->input('type') : '',
            'status' => in_array($request->input('status'), ['pending', 'paid', 'partial', 'approved', 'cancelled'], true) ? $request->input('status') : '',
            'payment_status' => in_array($request->input('payment_status'), ['open', 'partial', 'paid', 'overdue', 'cancelled'], true) ? $request->input('payment_status') : '',
            'currency' => in_array($request->input('currency'), ['IQD', 'USD'], true) ? $request->input('currency') : '',
            'academic_year' => trim((string) $request->input('academic_year', '')),
            'date_from' => $this->normalizedDateFilter($request->input('date_from')),
            'date_to' => $this->normalizedDateFilter($request->input('date_to')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
        ];
    }

    private function normalizedDateFilter($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : '';
    }

    private function canSendTuitionReminder($user): bool
    {
        return $user->hasRole('super_administrator')
            || ($user->hasPermission('finance.view') && $user->hasAnyPermission(['finance.create_invoice', 'finance.record_payment']));
    }

    private function authorizeTuitionReminder(Request $request): void
    {
        abort_unless($this->canSendTuitionReminder($request->user()), 403);
    }

    private function tuitionReminderStudents(array $filters, array $studentIds = [])
    {
        return Student::with(['department', 'financeTransactions'])
            ->when($studentIds !== [], fn ($query) => $query->whereKey($studentIds))
            ->when($studentIds === [] && $filters['q'], function ($builder) use ($filters) {
                $search = $filters['q'];
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            })
            ->when($studentIds === [] && $filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($studentIds === [] && $filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
            ->whereHas('financeTransactions', function ($query) {
                $query
                    ->where('type', 'invoice')
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('payment_status', ['open', 'partial', 'overdue']);
            })
            ->whereHas('financeTransactions', function ($query) use ($filters) {
                $this->applyTuitionReminderInvoiceFilters($query, $filters);
            })
            ->latest()
            ->limit(200)
            ->get()
            ->filter(fn ($student) => $this->positiveBalances($this->balancesByCurrency($student->financeTransactions))->isNotEmpty())
            ->values();
    }

    private function applyTuitionReminderInvoiceFilters($query, array $filters): void
    {
        $query
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['open', 'partial', 'overdue'])
            ->when($filters['payment_status'] && in_array($filters['payment_status'], ['open', 'partial', 'overdue'], true), fn ($builder) => $builder->where('payment_status', $filters['payment_status']))
            ->when($filters['currency'], fn ($builder) => $builder->where('currency', $filters['currency']))
            ->when($filters['academic_year'], fn ($builder) => $builder->where('academic_year', $filters['academic_year']))
            ->when($filters['date_from'], fn ($builder) => $builder->whereDate('due_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($builder) => $builder->whereDate('due_date', '<=', $filters['date_to']));
    }

    private function positiveBalances($balances)
    {
        return collect($balances)
            ->filter(fn ($balance) => (float) ($balance['balance'] ?? 0) > 0)
            ->values();
    }

    private function sumBalanceRows($balances)
    {
        return collect($balances)
            ->groupBy('currency')
            ->map(fn ($currencyBalances, $currency) => [
                'currency' => $currency,
                'charges' => $currencyBalances->sum(fn ($balance) => (float) ($balance['charges'] ?? 0)),
                'credits' => $currencyBalances->sum(fn ($balance) => (float) ($balance['credits'] ?? 0)),
                'balance' => $currencyBalances->sum(fn ($balance) => (float) ($balance['balance'] ?? 0)),
            ])
            ->sortKeys()
            ->values();
    }

    private function redirectToFinance(Request $request)
    {
        $params = collect([
            'student_id' => $request->input('student_id'),
            'q' => $request->input('q'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'payment_status' => $request->input('payment_status'),
            'currency' => $request->input('currency'),
            'academic_year' => $request->input('academic_year'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'college_id' => $request->input('college_id'),
            'department_id' => $request->input('department_id'),
        ])->filter(fn ($value) => filled($value))->all();

        if ($request->input('return_to') === 'tuition-reminders') {
            return redirect()->route('finance.tuition-reminders.index', $params);
        }

        if (! empty($params['student_id'])) {
            $studentId = $params['student_id'];
            unset($params['student_id']);

            return redirect()->route('finance.students.show', array_merge(['student' => $studentId], $params));
        }

        return redirect()->route('finance', $params);
    }

    private function applyFinanceFilters($builder, array $filters): void
    {
        $builder
            ->when($filters['q'], function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($student) use ($search) {
                            $student
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('student_id', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['type'], fn ($query) => $query->where('type', $filters['type']))
            ->when($filters['status'], fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['payment_status'], fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->when($filters['currency'], fn ($query) => $query->where('currency', $filters['currency']))
            ->when($filters['academic_year'], fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->when($filters['date_from'], fn ($query) => $query->whereDate('transaction_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($query) => $query->whereDate('transaction_date', '<=', $filters['date_to']))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('student.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->whereHas('student', fn ($student) => $student->where('department_id', $filters['department_id'])));
    }

    private function financeFilterOptions(): array
    {
        return [
            'academicYears' => FinanceTransaction::whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
        ];
    }

    private function balancesByCurrency($transactions)
    {
        return $transactions
            ->groupBy('currency')
            ->map(function ($currencyTransactions, $currency) {
                $charges = $currencyTransactions->whereIn('type', FinanceTransaction::chargeTypes())->sum(fn ($transaction) => (float) $transaction->amount);
                $credits = $currencyTransactions->whereIn('type', FinanceTransaction::creditTypes())->sum(fn ($transaction) => (float) $transaction->amount);

                return [
                    'currency' => $currency,
                    'charges' => $charges,
                    'credits' => $credits,
                    'balance' => $charges - $credits,
                ];
            })
            ->sortKeys();
    }

    private function formatCurrencyTotals($balances, string $field): string
    {
        if ($balances->isEmpty()) {
            return '0.00 IQD';
        }

        return $balances
            ->map(fn ($row) => number_format((float) $row[$field], 2).' '.$row['currency'])
            ->implode(' / ');
    }

    private function invoiceOptions(Student $student)
    {
        return $student->financeTransactions()
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid')
            ->oldest('due_date')
            ->get();
    }

    private function nextDueInvoice(Student $student): ?FinanceTransaction
    {
        return $student->financeTransactions
            ->filter(fn (FinanceTransaction $transaction) => $transaction->type === 'invoice'
                && $transaction->status !== 'cancelled'
                && in_array($transaction->payment_status, ['open', 'partial', 'overdue'], true)
                && $transaction->due_date)
            ->sortBy('due_date')
            ->first();
    }

    private function paymentPlanSummary($transactions): array
    {
        $installments = collect($transactions)
            ->filter(fn (FinanceTransaction $transaction) => $transaction->type === 'invoice'
                && $transaction->reference
                && str_contains($transaction->reference, ' - '));
        $total = $installments->count();
        $paid = $installments->where('payment_status', 'paid')->count();
        $overdue = $installments->where('payment_status', 'overdue')->count();
        $open = $installments
            ->whereIn('payment_status', ['open', 'partial'])
            ->count();

        return [
            'total' => $total,
            'paid' => $paid,
            'open' => $open,
            'overdue' => $overdue,
            'label' => $total > 0 ? "{$paid} paid / {$open} open / {$overdue} overdue" : 'No semester payment plan',
        ];
    }

    private function createFinanceTransactions(array $validated)
    {
        if ($validated['type'] === 'invoice' && ($validated['payment_plan'] ?? 'full') === 'semester') {
            return $this->createSemesterTuitionInvoices($validated);
        }

        $payload = collect($validated)->except(['payment_plan', 'semester_ids'])->all();

        return collect([FinanceTransaction::create($payload)]);
    }

    private function financeRecordSuccessMessage($transactions): string
    {
        if ($transactions->count() > 1 && $transactions->every(fn (FinanceTransaction $transaction) => $transaction->type === 'invoice')) {
            $schedule = $transactions
                ->map(fn (FinanceTransaction $transaction) => number_format((float) $transaction->amount, 2).' '.$transaction->currency.' due '.($transaction->due_date?->format('Y-m-d') ?? 'no due date'))
                ->implode('; ');

            return $transactions->count().' semester invoices created: '.$schedule.'.';
        }

        return 'Finance record saved successfully.';
    }

    private function createSemesterTuitionInvoices(array $validated)
    {
        $semesters = $this->selectedTuitionSemesters($validated);
        $totalAmount = (float) $validated['amount'];
        $semesterCount = $semesters->count();
        $baseAmount = round($totalAmount / $semesterCount, 2);
        $allocated = 0.0;

        return $semesters
            ->values()
            ->map(function (Semester $semester, int $index) use ($validated, $semesterCount, $baseAmount, &$allocated) {
                $amount = $index === $semesterCount - 1 ? round((float) $validated['amount'] - $allocated, 2) : $baseAmount;
                $allocated += $amount;

                $payload = collect($validated)->except(['payment_plan', 'semester_ids'])->merge([
                    'amount' => $amount,
                    'invoice_number' => null,
                    'reference' => substr(trim(($validated['reference'] ?? 'Tuition').' - '.$semester->name.' '.$semester->academic_year), 0, 100),
                    'academic_year' => $semester->academic_year,
                    'due_date' => $semester->end_date ?: ($validated['due_date'] ?? null),
                ])->all();
                $payload['invoice_number'] = $this->documentNumber($payload, 'invoice_number');
                $payload['balance_after'] = $this->balanceAfter($payload['student_id'], $payload['type'], (float) $payload['amount'], $payload['currency']);
                $payload['payment_status'] = $this->paymentStatusForTransaction($payload);

                return FinanceTransaction::create($payload);
            });
    }

    private function selectedTuitionSemesters(array $validated)
    {
        $semesterIds = collect($validated['semester_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();

        if ($semesterIds->isEmpty()) {
            throw ValidationException::withMessages(['semester_ids' => 'Select at least one semester to split the tuition.']);
        }

        $student = Student::findOrFail($validated['student_id']);
        $semesters = Semester::whereIn('id', $semesterIds)
            ->where('university_id', $student->university_id)
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->orderBy('name')
            ->get();

        if ($semesters->count() !== $semesterIds->count()) {
            throw ValidationException::withMessages(['semester_ids' => 'One or more selected semesters do not belong to this student university.']);
        }

        if ($semesters->contains(fn (Semester $semester) => blank($semester->end_date))) {
            throw ValidationException::withMessages(['semester_ids' => 'Every selected semester must have an end date before tuition can be split by semester.']);
        }

        return $semesters;
    }

    private function authorizeFinanceEntry(?string $type): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        $permission = match ($type) {
            'invoice' => 'finance.create_invoice',
            'payment' => 'finance.record_payment',
            'refund' => 'finance.refund',
            default => 'finance.record_expense',
        };

        abort_unless($user->hasPermission($permission), 403);
    }

    private function authorizeFinanceApproval(FinanceTransaction $transaction): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        $permission = in_array($transaction->type, ['payment'], true)
            ? 'finance.approve_payment'
            : 'finance.approve_expense';

        abort_unless($user->hasPermission($permission), 403);
    }

    private function authorizeFinanceVoid(FinanceTransaction $transaction): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        abort_unless($user->hasAnyPermission(['finance.refund', 'finance.approve_payment', 'finance.approve_expense']), 403);
    }

    private function authorizeStudentAccountBlock(Request $request): void
    {
        abort_unless($this->canManageStudentAccountBlock($request->user()), 403);
    }

    private function authorizeStudentFinanceView(?User $user): void
    {
        abort_unless($this->canViewStudentFinance($user), 403);
    }

    private function canViewStudentFinance(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_administrator', 'administrator', 'chief_accountant', 'accountant'])
            || $user->hasPermission('finance.view');
    }

    private function canManageStudentAccountBlock(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_administrator')
            || $user->hasAnyRole(['administrator', 'chief_accountant', 'accountant'])
            || $user->hasAnyPermission(['finance.create_invoice', 'finance.record_payment', 'finance.approve_payment']);
    }

    private function validatedInvoiceAllocation(array $transaction): ?int
    {
        if (! in_array($transaction['type'], FinanceTransaction::creditTypes(), true) || empty($transaction['invoice_transaction_id'])) {
            return null;
        }

        $invoice = FinanceTransaction::whereKey($transaction['invoice_transaction_id'])
            ->where('student_id', $transaction['student_id'])
            ->where('currency', $transaction['currency'])
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled')
            ->first();

        abort_unless($invoice, 422);

        return $invoice->id;
    }

    private function documentNumber(array $transaction, string $column): ?string
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
        $next = FinanceTransaction::whereNotNull($column)
            ->where($column, 'like', $base.'%')
            ->count() + 1;

        do {
            $number = $base.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $exists = FinanceTransaction::where($column, $number)->exists();
            $next++;
        } while ($exists);

        return $number;
    }

    private function balanceAfter(int $studentId, string $type, float $amount, string $currency): float
    {
        $charges = FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->whereIn('type', FinanceTransaction::chargeTypes())
            ->sum('amount');
        $credits = FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');
        $signedAmount = in_array($type, FinanceTransaction::creditTypes(), true) ? -$amount : $amount;

        return round($charges - $credits + $signedAmount, 2);
    }

    private function recalculateStudentBalances(int $studentId, string $currency): void
    {
        $balance = 0.0;
        FinanceTransaction::where('student_id', $studentId)
            ->where('currency', $currency)
            ->oldest('transaction_date')
            ->oldest()
            ->get()
            ->each(function (FinanceTransaction $transaction) use (&$balance) {
                $balance += $transaction->signedAmount();
                $transaction->timestamps = false;
                $transaction->balance_after = round($balance, 2);
                $transaction->save();
            });
    }

    private function refreshAllocatedInvoice(FinanceTransaction $transaction): void
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
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');
        $amount = (float) $invoice->amount;
        $status = match (true) {
            $paid >= $amount => 'paid',
            $paid > 0 => 'partial',
            $invoice->due_date && $invoice->due_date->isPast() => 'overdue',
            default => 'open',
        };

        $invoice->update(['payment_status' => $status]);
    }

    private function paymentStatusForTransaction(array $transaction): string
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

        if ($transaction['type'] === 'invoice' && $transaction['status'] === 'paid') {
            return 'paid';
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

    private function paymentStatusForBalance(float $balance): string
    {
        if ($balance <= 0.0) {
            return 'paid';
        }

        return 'open';
    }

    private function reversalType(string $type): string
    {
        return in_array($type, FinanceTransaction::creditTypes(), true) ? 'refund' : 'discount';
    }
}
