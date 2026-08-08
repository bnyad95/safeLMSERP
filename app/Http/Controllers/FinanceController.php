<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AppNotification;
use App\Models\FinanceTransaction;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionAgreement;
use App\Models\User;
use App\Services\FinanceLedgerService;
use App\Services\NotificationService;
use App\Services\TuitionChargeService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->requireAnyPermission('finance.view');

        $user = $request->user();
        $user->loadMissing(['university', 'college', 'department']);

        $financeQuery = $this->scopedFinanceQuery($user);
        $studentQuery = $this->scopedStudentQuery($user);
        $outstanding = $this->balancesByCurrencyQuery($financeQuery)
            ->filter(fn (array $balance) => $balance['balance'] > 0)
            ->values();
        $collectedToday = (clone $financeQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->where('type', 'payment')
            ->where('posting_status', 'posted')
            ->whereDate('transaction_date', today())
            ->select('currency')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency ?: 'IQD', 'amount' => (float) $row->amount]);

        $overdueQuery = (clone $financeQuery)
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'overdue');
        $pendingQuery = (clone $financeQuery)
            ->where('posting_status', 'pending')
            ->where('status', 'pending')
            ->whereIn('type', $this->approvableFinanceTypes($user));
        $dueSoonQuery = (clone $financeQuery)
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['open', 'partial'])
            ->whereBetween('due_date', [today(), today()->addDays(30)]);

        $overdueInvoices = (clone $overdueQuery)
            ->with(['student.department'])
            ->oldest('due_date')
            ->limit(6)
            ->get()
            ->each(fn (FinanceTransaction $invoice) => $invoice->setAttribute('remaining_amount', $this->remainingInvoiceAmount($invoice)));
        $upcomingInvoices = (clone $dueSoonQuery)
            ->with('student')
            ->oldest('due_date')
            ->limit(6)
            ->get()
            ->each(fn (FinanceTransaction $invoice) => $invoice->setAttribute('remaining_amount', $this->remainingInvoiceAmount($invoice)));
        $recentTransactions = (clone $financeQuery)
            ->with('student')
            ->latest('transaction_date')
            ->latest('id')
            ->limit(8)
            ->get();
        $recentReminders = AppNotification::query()
            ->with('student')
            ->where('type', 'tuition_charge_reminder')
            ->whereIn('student_id', (clone $studentQuery)->select('students.id'))
            ->latest()
            ->limit(5)
            ->get();

        $scopeLabel = match (true) {
            $this->hasGlobalFinanceScope($user) => 'All institutions',
            filled($user->department_id) => $user->department?->name ?? 'Assigned department',
            filled($user->college_id) => $user->college?->name ?? 'Assigned college',
            filled($user->university_id) => $user->university?->name ?? 'Assigned university',
            default => 'No organization assigned',
        };

        return view('finance.dashboard', [
            'scopeLabel' => $scopeLabel,
            'chartData' => $this->financeDashboardChartData($financeQuery, $overdueQuery),
            'stats' => [
                ['label' => 'Outstanding Tuition', 'value' => $this->formatCurrencyTotals($outstanding, 'balance'), 'detail' => 'Posted balance in your organization scope', 'tone' => 'blue'],
                ['label' => 'Collected Today', 'value' => $this->formatCurrencyTotals($collectedToday, 'amount'), 'detail' => 'Posted student payments today', 'tone' => 'emerald'],
                ['label' => 'Overdue Students', 'value' => number_format((clone $overdueQuery)->distinct()->count('student_id')), 'detail' => number_format((clone $overdueQuery)->count()).' overdue invoice(s)', 'tone' => 'red'],
                ['label' => 'Pending Approvals', 'value' => number_format((clone $pendingQuery)->count()), 'detail' => 'Finance records waiting for approval', 'tone' => 'amber'],
            ],
            'operationalStats' => [
                'overdueInvoices' => (clone $overdueQuery)->count(),
                'dueSoon' => (clone $dueSoonQuery)->count(),
                'blockedAccounts' => (clone $studentQuery)
                    ->whereHas('user', fn ($account) => $account
                        ->whereNotNull('account_blocked_at')
                        ->where('account_block_type', 'finance'))
                    ->count(),
                'remindersLastSevenDays' => AppNotification::query()
                    ->where('type', 'tuition_charge_reminder')
                    ->whereIn('student_id', (clone $studentQuery)->select('students.id'))
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'overdueInvoices' => $overdueInvoices,
            'upcomingInvoices' => $upcomingInvoices,
            'recentTransactions' => $recentTransactions,
            'recentReminders' => $recentReminders,
            'canCreateRecord' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.create_invoice', 'finance.record_payment', 'finance.record_expense', 'finance.refund']),
            'canApproveFinance' => $user->hasRole('super_administrator') || $user->hasAnyPermission(['finance.approve_payment', 'finance.approve_expense']),
            'canSendTuitionReminder' => $this->canSendTuitionReminder($user),
        ]);
    }

    public function approvals(Request $request)
    {
        $this->requireAnyPermission('finance.approve_payment', 'finance.approve_expense');

        $user = $request->user();
        $types = $this->approvableFinanceTypes($user);
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => in_array($request->input('type'), $types, true) ? $request->input('type') : '',
            'currency' => in_array($request->input('currency'), ['IQD', 'USD'], true) ? $request->input('currency') : '',
            'eligibility' => in_array($request->input('eligibility'), ['actionable', 'mine'], true) ? $request->input('eligibility') : '',
            'sort' => $request->input('sort') === 'newest' ? 'newest' : 'oldest',
        ];

        $pendingQuery = $this->scopedFinanceQuery($user)
            ->where('status', 'pending')
            ->where('posting_status', 'pending')
            ->whereIn('type', $types);
        $filteredQuery = (clone $pendingQuery)
            ->when($filters['q'], function ($query, string $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('receipt_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($student) => $student
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['type'], fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['currency'], fn ($query, string $currency) => $query->where('currency', $currency))
            ->when($filters['eligibility'] === 'actionable' && ! $user->hasRole('super_administrator'), fn ($query) => $query->where('recorded_by', '!=', $user->id))
            ->when($filters['eligibility'] === 'mine', fn ($query) => $query->where('recorded_by', $user->id));

        $amountsByCurrency = (clone $filteredQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->select('currency')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn ($row) => ['currency' => $row->currency ?: 'IQD', 'amount' => (float) $row->amount]);
        $transactions = (clone $filteredQuery)
            ->with(['student.department.college', 'recorder', 'invoice'])
            ->when(
                $filters['sort'] === 'newest',
                fn ($query) => $query->latest('created_at')->latest('id'),
                fn ($query) => $query->oldest('created_at')->oldest('id')
            )
            ->paginate(30)
            ->withQueryString();

        return view('finance.approvals', [
            'transactions' => $transactions,
            'filters' => $filters,
            'types' => collect([
                'payment' => 'Payment',
                'discount' => 'Discount',
                'scholarship' => 'Scholarship',
                'refund' => 'Refund',
            ])->only($types),
            'stats' => [
                ['label' => 'Waiting for Approval', 'value' => number_format((clone $pendingQuery)->count()), 'detail' => 'Pending records in your organization scope'],
                ['label' => 'Ready for You', 'value' => number_format($user->hasRole('super_administrator') ? (clone $pendingQuery)->count() : (clone $pendingQuery)->where('recorded_by', '!=', $user->id)->count()), 'detail' => 'Records you are allowed to approve'],
                ['label' => 'Recorded by You', 'value' => number_format((clone $pendingQuery)->where('recorded_by', $user->id)->count()), 'detail' => 'Must be approved by another user'],
                ['label' => 'Filtered Amount', 'value' => $this->formatCurrencyTotals($amountsByCurrency, 'amount'), 'detail' => 'Pending value by currency'],
            ],
        ]);
    }

    private function financeDashboardChartData($financeQuery, $overdueQuery): array
    {
        $collectionStart = today()->subDays(29);
        $collectionRows = (clone $financeQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->where('type', 'payment')
            ->where('posting_status', 'posted')
            ->where('transaction_date', '>=', $collectionStart)
            ->select('transaction_date', 'currency')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('transaction_date', 'currency')
            ->orderBy('transaction_date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->transaction_date->format('Y-m-d'),
                'currency' => $row->currency ?: 'IQD',
                'amount' => (float) $row->amount,
            ]);

        $collegeRows = (clone $financeQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->leftJoin('students as dashboard_students', 'finance_transactions.student_id', '=', 'dashboard_students.id')
            ->leftJoin('departments as dashboard_departments', 'dashboard_students.department_id', '=', 'dashboard_departments.id')
            ->leftJoin('colleges as dashboard_colleges', 'dashboard_departments.college_id', '=', 'dashboard_colleges.id')
            ->where('finance_transactions.posting_status', 'posted')
            ->selectRaw('COALESCE(dashboard_colleges.id, 0) as college_id')
            ->selectRaw("COALESCE(dashboard_colleges.name, 'Unassigned college') as college")
            ->addSelect('finance_transactions.currency')
            ->selectRaw(
                'SUM(CASE WHEN finance_transactions.type IN (?, ?) THEN finance_transactions.amount ELSE 0 END) - SUM(CASE WHEN finance_transactions.type IN (?, ?, ?) THEN finance_transactions.amount ELSE 0 END) as balance',
                [...FinanceTransaction::chargeTypes(), ...FinanceTransaction::creditTypes()]
            )
            ->groupBy('dashboard_colleges.id', 'dashboard_colleges.name', 'finance_transactions.currency')
            ->orderByDesc('balance')
            ->get()
            ->filter(fn ($row) => (float) $row->balance > 0)
            ->map(fn ($row) => [
                'college_id' => (int) $row->college_id,
                'college' => $row->college,
                'currency' => $row->currency ?: 'IQD',
                'balance' => (float) $row->balance,
            ])
            ->values();

        $invoiceStatusRows = (clone $financeQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partial', 'open', 'overdue'])
            ->select('payment_status', 'currency')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('payment_status', 'currency')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->payment_status,
                'currency' => $row->currency ?: 'IQD',
                'total' => (int) $row->total,
            ]);

        $allocationTotals = FinanceTransaction::query()
            ->select('invoice_transaction_id')
            ->selectRaw('SUM(amount) as allocated_amount')
            ->whereNotNull('invoice_transaction_id')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->groupBy('invoice_transaction_id');
        $agingCase = "CASE
            WHEN DATEDIFF(CURRENT_DATE, finance_transactions.due_date) <= 30 THEN '1-30 days'
            WHEN DATEDIFF(CURRENT_DATE, finance_transactions.due_date) <= 60 THEN '31-60 days'
            WHEN DATEDIFF(CURRENT_DATE, finance_transactions.due_date) <= 90 THEN '61-90 days'
            ELSE '90+ days'
        END";
        $agingRows = (clone $overdueQuery)
            ->withoutEagerLoads()
            ->reorder()
            ->leftJoinSub($allocationTotals, 'dashboard_allocations', fn ($join) => $join
                ->on('finance_transactions.id', '=', 'dashboard_allocations.invoice_transaction_id'))
            ->addSelect('finance_transactions.currency')
            ->selectRaw("{$agingCase} as age_bucket")
            ->selectRaw('SUM(GREATEST(finance_transactions.amount - COALESCE(dashboard_allocations.allocated_amount, 0), 0)) as balance')
            ->groupBy('finance_transactions.currency', 'age_bucket')
            ->get()
            ->map(fn ($row) => [
                'bucket' => $row->age_bucket,
                'currency' => $row->currency ?: 'IQD',
                'balance' => (float) $row->balance,
            ]);

        return [
            'currencies' => ['IQD', 'USD'],
            'dates' => collect(range(0, 29))
                ->map(fn (int $offset) => $collectionStart->copy()->addDays($offset)->format('Y-m-d')),
            'collections' => $collectionRows,
            'outstandingByCollege' => $collegeRows,
            'invoiceStatuses' => $invoiceStatusRows,
            'overdueAging' => $agingRows,
            'financeUrl' => route('finance'),
            'remindersUrl' => route('finance.tuition-reminders.index'),
        ];
    }

    public function index(Request $request)
    {
        $this->requireAnyPermission('finance.view');

        $filters = $this->financeFilters($request);
        $query = $filters['q'];
        $user = $request->user();
        $selectedStudent = null;
        $shouldLoadStudents = $query !== '' || (bool) $filters['college_id'] || (bool) $filters['department_id'];

        if ($request->filled('student_id')) {
            $params = $request->except('student_id');

            return redirect()->route('finance.students.show', array_merge(['student' => $request->integer('student_id')], $params));
        }

        $students = $shouldLoadStudents
            ? $this->scopedStudentQuery($user)
                ->with(['department.college', 'university'])
                ->when($query !== '', function ($builder) use ($query) {
                    $builder->where(function ($q) use ($query) {
                        $q->where('full_name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%")
                            ->orWhere('student_id', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
                })
                ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
                ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
                ->latest()
                ->limit(25)
                ->get()
            : collect();

        $transactionQuery = $this->scopedFinanceQuery($user)
            ->with(['student.department.college', 'recorder', 'approver', 'voider', 'invoice', 'originalTransaction'])
            ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
            ->latest('transaction_date')
            ->latest();
        $transactions = $transactionQuery->paginate(20)->withQueryString();

        $scopeBalanceQuery = $this->scopedFinanceQuery($user);
        $this->applyFinanceFilters($scopeBalanceQuery, array_merge($filters, [
            'type' => '',
            'status' => '',
            'payment_status' => '',
            'currency' => '',
            'academic_year' => '',
            'date_from' => '',
            'date_to' => '',
        ]));
        $scopeBalances = $this->balancesByCurrencyQuery($scopeBalanceQuery);
        $filteredBalances = $this->balancesByCurrencyQuery($transactionQuery);
        $selectedBalances = $selectedStudent ? $this->balancesByCurrencyQuery($selectedStudent->financeTransactions()) : collect();
        $selectedBalance = (float) $selectedBalances->sum('balance');
        $selectedPaymentStatus = $this->paymentStatusForBalances($selectedBalances);

        return view('finance.index', [
            'query' => $query,
            'filters' => $filters,
            'students' => $students,
            'shouldLoadStudents' => $shouldLoadStudents,
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
            'filterOptions' => $this->financeFilterOptions($user),
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
        $this->authorizeStudentScope($request->user(), $student);

        $filters = $this->financeFilters($request);
        $user = $request->user();
        app(FinanceLedgerService::class)->refreshStudentInvoiceStatuses($student);

        $student->load([
            'department.college',
            'university',
            'user.accountBlocker',
        ]);

        $transactionQuery = $this->scopedFinanceQuery($user)
            ->with(['student.department.college', 'recorder', 'approver', 'voider', 'invoice', 'originalTransaction'])
            ->where('student_id', $student->id)
            ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
            ->latest('transaction_date')
            ->latest();
        $transactions = $transactionQuery->paginate(20)->withQueryString();
        $selectedBalances = $this->balancesByCurrencyQuery($student->financeTransactions());
        $filteredBalances = $this->balancesByCurrencyQuery($transactionQuery);
        $selectedBalance = (float) $selectedBalances->sum('balance');
        $selectedPaymentStatus = $this->paymentStatusForBalances($selectedBalances);
        $nextDueInvoice = $this->nextDueInvoiceQuery($student);
        $paymentPlanSummary = $this->paymentPlanSummaryQuery($student);

        return view('finance.show', [
            'filters' => $filters,
            'selectedStudent' => $student,
            'transactions' => $transactions,
            'stats' => [
                ['label' => 'Outstanding Tuition', 'value' => $this->formatCurrencyTotals($selectedBalances, 'balance'), 'detail' => 'Remaining balance by currency'],
                ['label' => 'Paid / Credits', 'value' => $this->formatCurrencyTotals($selectedBalances, 'credits'), 'detail' => 'Payments, discounts, scholarships'],
                ['label' => 'Next Due', 'value' => $nextDueInvoice ? number_format($this->remainingInvoiceAmount($nextDueInvoice), 2).' '.$nextDueInvoice->currency : 'No due invoices', 'detail' => $nextDueInvoice ? 'Due '.$nextDueInvoice->due_date->format('Y-m-d') : 'No open invoice due date'],
                ['label' => 'Payment Status', 'value' => ucfirst($selectedPaymentStatus), 'detail' => $paymentPlanSummary['total'] > 0 ? $paymentPlanSummary['label'] : 'No semester payment plan'],
            ],
            'selectedBalances' => $selectedBalances,
            'filteredBalanceText' => $this->formatCurrencyTotals($filteredBalances, 'balance'),
            'selectedPaymentStatus' => $selectedPaymentStatus,
            'paymentPlanSummary' => $paymentPlanSummary,
            'filterOptions' => $this->financeFilterOptions($user),
            'tuitionAgreements' => $student->tuitionAgreements()
                ->with('academicYear')
                ->withCount('transactions')
                ->latest('agreed_at')
                ->limit(10)
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
            'tuitionChargeSemesterOptions' => Semester::where('university_id', $student->university_id)
                ->with('academicYear')
                ->whereHas('academicYear', fn ($query) => $query->whereIn('status', ['upcoming', 'active']))
                ->orderByDesc('academic_year')
                ->orderBy('start_date')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function createStudentRecord(Request $request, Student $student)
    {
        $this->authorizeStudentFinanceView($request->user());
        $this->authorizeStudentScope($request->user(), $student);

        $user = $request->user();
        $allowedEntryTypes = $this->allowedFinanceEntryTypes($user);
        abort_if($allowedEntryTypes === [], 403);

        $student->load(['department.college', 'university']);
        $canPostImmediately = $user->hasRole('super_administrator');

        return view('finance.create', [
            'selectedStudent' => $student,
            'types' => [
                'invoice' => 'Invoice / Tuition Charge',
                'payment' => 'Payment',
                'discount' => 'Discount',
                'scholarship' => 'Scholarship',
                'refund' => 'Refund',
            ],
            'creationStatuses' => $canPostImmediately
                ? ['pending' => 'Pending approval', 'paid' => 'Post immediately']
                : ['pending' => 'Pending approval'],
            'allowedEntryTypes' => $allowedEntryTypes,
            'canCreateInvoice' => in_array('invoice', $allowedEntryTypes, true),
            'canCollectPayment' => $canPostImmediately || $user->hasPermission('finance.record_payment'),
            'canPostImmediately' => $canPostImmediately,
            'invoiceOptions' => $this->invoiceOptions($student),
            'semesterOptions' => Semester::where('university_id', $student->university_id)
                ->with('academicYear')
                ->whereHas('academicYear', fn ($query) => $query->whereIn('status', ['upcoming', 'active']))
                ->orderByDesc('academic_year')
                ->orderBy('start_date')
                ->orderBy('name')
                ->get(),
            'academicYearOptions' => AcademicYear::where('university_id', $student->university_id)
                ->whereIn('status', ['upcoming', 'active'])
                ->orderByDesc('name')
                ->get(),
        ]);
    }

    public function generateTuitionCharge(Request $request, Student $student)
    {
        $this->authorizeStudentFinanceView($request->user());
        $this->authorizeStudentScope($request->user(), $student);
        abort_unless(
            $request->user()->hasRole('super_administrator') || $request->user()->hasPermission('finance.create_invoice'),
            403
        );

        $validated = $request->validate([
            'semester_id' => ['required', 'exists:semesters,id'],
            'currency' => ['required', 'in:IQD,USD'],
            'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $semester = Semester::where('university_id', $student->university_id)->findOrFail($validated['semester_id']);

        $result = app(TuitionChargeService::class)->generateForStudentSemester(
            $student,
            $semester,
            $validated['currency'],
            $request->user(),
            $validated['transaction_date'],
            $validated['due_date'] ?? null,
            $validated['notes'] ?? null
        );

        if (! $result['ok']) {
            return redirect()->route('finance.students.show', $student)->with('error', $result['message']);
        }

        if (! $result['created']) {
            return redirect()->route('finance.students.show', $student)->with('success', $result['message']);
        }

        return redirect()->route('finance.students.show', $student)
            ->with('success', "Tuition charge generated: {$result['transaction']->amount} {$validated['currency']} for {$result['lines']->count()} course(s).");
    }

    public function tuitionReminders(Request $request)
    {
        $this->authorizeTuitionReminder($request);

        $filters = $this->financeFilters($request);
        if (! in_array($filters['payment_status'], ['', 'open', 'partial', 'overdue'], true)) {
            $filters['payment_status'] = '';
        }
        $studentPaginator = $this->tuitionReminderStudentQuery($filters, [], $request->user())
            ->paginate(50)
            ->withQueryString();
        $students = collect($studentPaginator->items());
        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->all();
        $balancesByStudent = $this->balanceRowsByStudentIds($studentIds);
        $oldestDueDates = $this->oldestDueDatesByStudentIds($studentIds, $filters);
        $reminderRows = $students->map(function (Student $student) use ($balancesByStudent, $oldestDueDates) {
            $balances = $this->positiveBalances($balancesByStudent->get($student->id, collect()));

            return [
                'student' => $student,
                'balances' => $balances,
                'balanceText' => $balances
                    ->map(fn ($balance) => number_format((float) $balance['balance'], 2).' '.$balance['currency'])
                    ->implode(' / '),
                'oldestDueDate' => $oldestDueDates->get($student->id),
            ];
        })->filter(fn ($row) => $row['balances']->isNotEmpty())->values();
        $balanceTotals = $this->sumBalanceRows($reminderRows->pluck('balances')->flatten(1));

        return view('finance.tuition-reminders', [
            'filters' => $filters,
            'filterOptions' => $this->financeFilterOptions($request->user()),
            'reminderRows' => $reminderRows,
            'reminderPaginator' => $studentPaginator,
            'stats' => [
                ['label' => 'Matching Students', 'value' => (string) $studentPaginator->total(), 'detail' => 'Filtered students with unpaid tuition'],
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
            'status' => ['required', 'in:pending,paid'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:finance_transactions,invoice_number'],
            'receipt_number' => ['nullable', 'string', 'max:100', 'unique:finance_transactions,receipt_number'],
            'reference' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'payment_plan' => ['nullable', 'in:full,semester'],
            'collect_now' => ['nullable', 'boolean'],
            'semester_ids' => ['nullable', 'array'],
            'semester_ids.*' => ['integer', 'distinct', 'exists:semesters,id'],
            'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['recorded_by'] = $request->user()->id;
        $this->validateFinancePostingAuthority($request, $validated);

        $transactions = DB::transaction(function () use (&$validated, $request) {
            $this->scopedStudentQuery($request->user())
                ->whereKey($validated['student_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $validated['invoice_transaction_id'] = $this->validatedInvoiceAllocation($validated);
            $isSemesterPlan = $validated['type'] === 'invoice' && ($validated['payment_plan'] ?? 'full') === 'semester';
            $ledger = app(FinanceLedgerService::class);
            $validated['invoice_number'] = $isSemesterPlan ? null : $ledger->documentNumber($validated, 'invoice_number');
            $validated['receipt_number'] = $isSemesterPlan ? null : $ledger->documentNumber($validated, 'receipt_number');
            $validated['posting_status'] = $validated['type'] === 'invoice' || $validated['status'] === 'paid'
                ? 'posted'
                : 'pending';
            $validated['balance_after'] = $validated['posting_status'] === 'posted'
                ? $ledger->balanceAfter($validated['student_id'], $validated['type'], (float) $validated['amount'], $validated['currency'])
                : null;
            $validated['payment_status'] = $ledger->paymentStatusForTransaction($validated);
            $transactions = $this->createFinanceTransactions(
                $validated,
                $request->user()->hasRole('super_administrator')
            );

            if ($transactions->isNotEmpty()) {
                $firstTransaction = $transactions->first();
                $ledger->recalculateStudentBalances((int) $firstTransaction->student_id, $firstTransaction->currency);
                $transactions->each(fn (FinanceTransaction $transaction) => $ledger->refreshAllocatedInvoice($transaction->fresh()));
                $ledger->synchronizeFinanceHold($firstTransaction->student()->with('user')->first());
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

        if ($financeTransaction->status !== 'pending' || $financeTransaction->posting_status !== 'pending') {
            return $this->financeApprovalRedirect($request, $financeTransaction)
                ->with('error', 'This finance record is no longer waiting for approval.');
        }

        if (! $request->user()->hasRole('super_administrator') && $financeTransaction->recorded_by === $request->user()->id) {
            return $this->financeApprovalRedirect($request, $financeTransaction)
                ->with('error', 'A finance record must be approved by a different authorized user.');
        }

        $approved = DB::transaction(function () use ($request, $financeTransaction) {
            $financeTransaction = FinanceTransaction::query()->whereKey($financeTransaction->id)->lockForUpdate()->firstOrFail();
            if ($financeTransaction->status !== 'pending' || $financeTransaction->posting_status !== 'pending') {
                return false;
            }

            if ($financeTransaction->invoice_transaction_id && in_array($financeTransaction->type, FinanceTransaction::creditTypes(), true)) {
                $invoice = FinanceTransaction::query()->whereKey($financeTransaction->invoice_transaction_id)->lockForUpdate()->firstOrFail();
                $alreadyPosted = (float) $invoice->allocations()
                    ->where('id', '!=', $financeTransaction->id)
                    ->where('posting_status', 'posted')
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('type', FinanceTransaction::creditTypes())
                    ->sum('amount');

                if ($alreadyPosted + (float) $financeTransaction->amount > (float) $invoice->amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'This approval would exceed the remaining invoice balance.',
                    ]);
                }
            }
            $ledger = app(FinanceLedgerService::class);
            $financeTransaction->update([
                'status' => 'approved',
                'posting_status' => 'posted',
                'payment_status' => $ledger->paymentStatusForTransaction([
                    'type' => $financeTransaction->type,
                    'status' => 'approved',
                    'due_date' => $financeTransaction->due_date,
                ]),
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $ledger->recalculateStudentBalances((int) $financeTransaction->student_id, $financeTransaction->currency);
            $ledger->refreshAllocatedInvoice($financeTransaction);
            $ledger->synchronizeFinanceHold($financeTransaction->student()->with('user')->first());

            return true;
        });

        if (! $approved) {
            return $this->financeApprovalRedirect($request, $financeTransaction)
                ->with('error', 'This finance record was already processed by another user.');
        }

        return $this->financeApprovalRedirect($request, $financeTransaction)
            ->with('success', 'Finance record approved.');
    }

    public function void(Request $request, FinanceTransaction $financeTransaction)
    {
        $this->authorizeFinanceVoid($financeTransaction);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $financeTransaction, $validated) {
            Student::whereKey($financeTransaction->student_id)->lockForUpdate()->firstOrFail();
            $financeTransaction = FinanceTransaction::query()
                ->whereKey($financeTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($financeTransaction->status === 'cancelled' || $financeTransaction->original_transaction_id, 422);

            if ($financeTransaction->type === 'invoice') {
                $hasAllocations = $financeTransaction->allocations()
                    ->where('status', '!=', 'cancelled')
                    ->exists();
                if ($hasAllocations) {
                    throw ValidationException::withMessages([
                        'transaction' => 'Reverse the payments and credits allocated to this invoice before voiding it.',
                    ]);
                }

                $hasInstallmentSchedule = $financeTransaction->tuition_agreement_id
                    && FinanceTransaction::where('tuition_agreement_id', $financeTransaction->tuition_agreement_id)
                        ->where('type', 'invoice')
                        ->where('status', '!=', 'cancelled')
                        ->whereKeyNot($financeTransaction->id)
                        ->exists();
                if ($hasInstallmentSchedule) {
                    throw ValidationException::withMessages([
                        'transaction' => 'A semester installment cannot be voided by itself because it would break the tuition agreement schedule.',
                    ]);
                }
            }

            if (! $financeTransaction->isPosted()) {
                $financeTransaction->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                    'voided_by' => $request->user()->id,
                    'voided_at' => now(),
                    'notes' => $validated['notes'],
                ]);

                return;
            }

            $ledger = app(FinanceLedgerService::class);
            $reversalType = $this->reversalType($financeTransaction->type);
            $reversal = FinanceTransaction::create([
                'student_id' => $financeTransaction->student_id,
                'tuition_agreement_id' => $financeTransaction->tuition_agreement_id,
                'semester_id' => $financeTransaction->semester_id,
                'original_transaction_id' => $financeTransaction->id,
                'recorded_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'type' => $reversalType,
                'amount' => $financeTransaction->amount,
                'balance_after' => $ledger->balanceAfter($financeTransaction->student_id, $reversalType, (float) $financeTransaction->amount, $financeTransaction->currency),
                'currency' => $financeTransaction->currency,
                'status' => 'approved',
                'posting_status' => 'posted',
                'payment_status' => 'paid',
                'receipt_number' => $ledger->documentNumber([
                    'type' => $reversalType,
                    'transaction_date' => now()->toDateString(),
                    'receipt_number' => null,
                ], 'receipt_number'),
                'reference' => 'VOID-'.$financeTransaction->documentNumber(),
                'academic_year' => $financeTransaction->academic_year,
                'transaction_date' => now()->toDateString(),
                'notes' => $validated['notes'],
            ]);

            $financeTransaction->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'voided_by' => $request->user()->id,
                'voided_at' => now(),
            ]);

            if ($financeTransaction->type === 'invoice') {
                $ledger->refreshTuitionAgreementStatus($financeTransaction->tuition_agreement_id);
            }

            $ledger->recalculateStudentBalances((int) $financeTransaction->student_id, $financeTransaction->currency);
            $ledger->refreshAllocatedInvoice($financeTransaction);
            $ledger->refreshAllocatedInvoice($reversal);
            $ledger->synchronizeFinanceHold($financeTransaction->student()->with('user')->first());
        });

        return redirect()
            ->route('finance.students.show', $financeTransaction->student_id)
            ->with('success', 'Finance record voided with a reversal entry.');
    }

    public function blockStudentAccount(Request $request, Student $student)
    {
        $this->authorizeStudentAccountBlock($request);
        $this->authorizeStudentScope($request->user(), $student);

        $student->load(['user.roles']);
        $account = $student->user;
        abort_unless($account, 404, 'Student login account was not found.');
        abort_unless($account->roles()->where('name', 'student')->exists(), 403, 'Only student login accounts can be blocked from finance.');

        if ($account->account_blocked_at && $account->account_block_type !== 'finance') {
            return redirect()
                ->route('finance.students.show', $student)
                ->with('error', 'This account has a non-finance hold. Finance cannot replace it.');
        }

        $ledger = app(FinanceLedgerService::class);
        $ledger->refreshStudentInvoiceStatuses($student);
        $overdueInvoices = $ledger->overdueInvoiceQuery($student)->exists();
        if (! $overdueInvoices) {
            return redirect()
                ->route('finance.students.show', $student)
                ->with('error', 'This student has no overdue tuition installment to justify a finance block.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $account->update([
            'account_blocked_at' => now(),
            'account_blocked_by' => $request->user()->id,
            'account_block_reason' => $validated['reason'],
            'account_block_type' => 'finance',
        ]);

        return redirect()
            ->route('finance.students.show', $student)
            ->with('success', 'Student account blocked until the tuition balance is resolved.');
    }

    public function unblockStudentAccount(Request $request, Student $student)
    {
        $this->authorizeStudentAccountBlock($request);
        $this->authorizeStudentScope($request->user(), $student);

        $student->load('user');
        $account = $student->user;
        abort_unless($account, 404, 'Student login account was not found.');

        if ($account->account_block_type !== 'finance') {
            return redirect()
                ->route('finance.students.show', $student)
                ->with('error', 'Only a finance hold can be removed from this workspace.');
        }

        $account->update([
            'account_blocked_at' => null,
            'account_blocked_by' => null,
            'account_block_reason' => null,
            'account_block_type' => null,
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
        $notificationService = app(NotificationService::class);
        $sent = 0;
        $this->tuitionReminderStudentQuery($filters, $studentIds, $request->user())
            ->chunkById(200, function ($students) use ($notificationService, $validated, $request, &$sent) {
                $balancesByStudent = $this->balanceRowsByStudentIds($students->modelKeys());

                foreach ($students as $student) {
                    $balances = $this->positiveBalances($balancesByStudent->get($student->id, collect()));
                    if ($balances->isEmpty()) {
                        continue;
                    }

                    $notificationService->notifyTuitionChargeReminder($student, $balances, $validated['message'] ?? null, $request->user());
                    $sent++;
                }
            });

        if ($sent === 0) {
            return $this->redirectToFinance($request)->with('error', 'No unpaid tuition charges were found for the selected scope.');
        }

        return $this->redirectToFinance($request)->with('success', "Tuition reminder sent to {$sent} student".($sent === 1 ? '.' : 's.'));
    }

    public function studentFinance(Request $request)
    {
        $this->requireAnyRole('student');

        $student = Student::with(['department', 'university'])
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere(function ($legacy) use ($request) {
                        $legacy->whereNull('user_id')->where('email', $request->user()->email);
                    });
            })
            ->first();
        $transactions = collect();
        $balances = collect();

        if ($student) {
            app(FinanceLedgerService::class)->refreshStudentInvoiceStatuses($student);
            $transactions = $student->financeTransactions()
                ->with(['invoice', 'originalTransaction'])
                ->latest('transaction_date')
                ->latest()
                ->paginate(20);
            $balances = $this->balancesByCurrencyQuery($student->financeTransactions());
        }

        return view('finance.student', [
            'student' => $student,
            'transactions' => $transactions,
            'balances' => $balances,
            'paymentStatus' => $this->paymentStatusForBalances($balances),
        ]);
    }

    public function statement(Student $student)
    {
        $user = auth()->user();
        $this->authorizeStudentFinanceView($user);
        $this->authorizeStudentScope($user, $student);

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
            'paymentStatus' => $this->paymentStatusForBalances($balances),
        ]);
    }

    public function receipt(Request $request, FinanceTransaction $financeTransaction)
    {
        abort_unless(
            in_array($financeTransaction->type, FinanceTransaction::creditTypes(), true)
            && $financeTransaction->posting_status === 'posted'
            && $financeTransaction->status !== 'cancelled',
            404
        );

        $user = $request->user();
        if ($user->hasRole('student')) {
            $student = Student::query()
                ->whereKey($financeTransaction->student_id)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhere(function ($legacy) use ($user) {
                            $legacy->whereNull('user_id')->where('email', $user->email);
                        });
                })
                ->firstOrFail();
        } else {
            $this->authorizeStudentFinanceView($user);
            $this->authorizeFinanceTransactionScope($user, $financeTransaction);
            $student = $financeTransaction->student;
        }

        $financeTransaction->load(['recorder', 'approver', 'invoice']);
        $student->load(['department.college', 'university']);

        return view('finance.receipt', compact('financeTransaction', 'student'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $this->financeFilters($request);
        $studentId = $request->integer('student_id') ?: null;

        if ($studentId) {
            $this->authorizeStudentFinanceView($user);
            $student = $this->scopedStudentQuery($user)->findOrFail($studentId);
            $studentId = $student->id;
        } else {
            $this->requireAnyPermission('finance.view');
        }

        $fileName = $studentId ? "student-{$studentId}-finance.csv" : 'finance-transactions.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($studentId, $filters, $user) {
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
                'Posting Status',
                'Payment Status',
                'Balance After',
                'Due Date',
                'Academic Year',
                'Recorded By',
                'Approved By',
                'Voided At',
                'Notes',
            ]);

            $query = FinanceTransaction::with(['student', 'recorder', 'approver', 'invoice'])
                ->when($studentId, fn ($builder) => $builder->where('student_id', $studentId))
                ->tap(fn ($builder) => $this->applyFinanceFilters($builder, $filters))
                ->oldest('transaction_date')
                ->oldest();

            if (! $studentId) {
                if (! $this->hasGlobalFinanceScope($user)) {
                    OrganizationScope::apply($query, $user, 'student_record');
                }
                $this->applyFinanceOrganizationConstraint($query, $user, 'student_record');
            }

            $query->chunk(200, function ($transactions) use ($output) {
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
                        $transaction->posting_status,
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

    private function tuitionReminderStudentQuery(array $filters, array $studentIds = [], ?User $user = null)
    {
        $query = $user ? $this->scopedStudentQuery($user) : Student::query();

        return $query
            ->with('department')
            ->when($studentIds !== [], fn ($query) => $query->whereKey($studentIds))
            ->when($studentIds === [] && $filters['q'], function ($builder) use ($filters) {
                $search = $filters['q'];
                $builder->where(function ($query) use ($search) {
                    $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($studentIds === [] && $filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($studentIds === [] && $filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
            ->whereHas('financeTransactions', function ($query) {
                $query
                    ->where('type', 'invoice')
                    ->where('posting_status', 'posted')
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('payment_status', ['open', 'partial', 'overdue']);
            })
            ->whereHas('financeTransactions', function ($query) use ($filters) {
                $this->applyTuitionReminderInvoiceFilters($query, $filters);
            })
            ->latest();
    }

    private function applyTuitionReminderInvoiceFilters($query, array $filters): void
    {
        $query
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['open', 'partial', 'overdue'])
            ->when($filters['payment_status'] && in_array($filters['payment_status'], ['open', 'partial', 'overdue'], true), fn ($builder) => $builder->where('payment_status', $filters['payment_status']))
            ->when($filters['currency'], fn ($builder) => $builder->where('currency', $filters['currency']))
            ->when($filters['academic_year'], fn ($builder) => $builder->where('academic_year', $filters['academic_year']))
            ->when($filters['date_from'], fn ($builder) => $builder->where('due_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($builder) => $builder->where('due_date', '<=', $filters['date_to']));
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

    private function financeApprovalRedirect(Request $request, FinanceTransaction $transaction)
    {
        if ($request->input('return_to') === 'approvals') {
            $params = collect($request->only(['q', 'type', 'currency', 'eligibility', 'sort']))
                ->filter(fn ($value) => filled($value))
                ->all();

            return redirect()->route('finance.approvals.index', $params);
        }

        return redirect()->route('finance.students.show', $transaction->student_id);
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
            ->when($filters['date_from'], fn ($query) => $query->where('transaction_date', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($query) => $query->where('transaction_date', '<=', $filters['date_to']))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('student.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->whereHas('student', fn ($student) => $student->where('department_id', $filters['department_id'])));
    }

    private function financeFilterOptions(User $user): array
    {
        $query = $this->scopedFinanceQuery($user);

        return [
            'academicYears' => $query
                ->whereNotNull('academic_year')
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year'),
        ];
    }

    private function balancesByCurrency($transactions)
    {
        return $transactions
            ->where('posting_status', 'posted')
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

    private function balancesByCurrencyQuery($query)
    {
        return (clone $query)
            ->withoutEagerLoads()
            ->reorder()
            ->where('posting_status', 'posted')
            ->select('currency')
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) as charges',
                FinanceTransaction::chargeTypes()
            )
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?, ?) THEN amount ELSE 0 END) as credits',
                FinanceTransaction::creditTypes()
            )
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->mapWithKeys(function ($row) {
                $currency = $row->currency ?: 'IQD';
                $charges = (float) $row->charges;
                $credits = (float) $row->credits;

                return [
                    $currency => [
                        'currency' => $currency,
                        'charges' => $charges,
                        'credits' => $credits,
                        'balance' => $charges - $credits,
                    ],
                ];
            });
    }

    private function balanceRowsByStudentIds(array $studentIds)
    {
        if ($studentIds === []) {
            return collect();
        }

        return FinanceTransaction::query()
            ->whereIn('student_id', $studentIds)
            ->where('posting_status', 'posted')
            ->select('student_id', 'currency')
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END) as charges',
                FinanceTransaction::chargeTypes()
            )
            ->selectRaw(
                'SUM(CASE WHEN type IN (?, ?, ?) THEN amount ELSE 0 END) as credits',
                FinanceTransaction::creditTypes()
            )
            ->groupBy('student_id', 'currency')
            ->orderBy('currency')
            ->get()
            ->groupBy('student_id')
            ->map(function ($rows) {
                return $rows->mapWithKeys(function ($row) {
                    $currency = $row->currency ?: 'IQD';
                    $charges = (float) $row->charges;
                    $credits = (float) $row->credits;

                    return [
                        $currency => [
                            'currency' => $currency,
                            'charges' => $charges,
                            'credits' => $credits,
                            'balance' => $charges - $credits,
                        ],
                    ];
                });
            });
    }

    private function oldestDueDatesByStudentIds(array $studentIds, array $filters)
    {
        if ($studentIds === []) {
            return collect();
        }

        $query = FinanceTransaction::query()
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('due_date');
        $this->applyTuitionReminderInvoiceFilters($query, $filters);

        return $query
            ->select('student_id')
            ->selectRaw('MIN(due_date) as oldest_due_date')
            ->groupBy('student_id')
            ->pluck('oldest_due_date', 'student_id');
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
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid')
            ->oldest('due_date')
            ->get()
            ->each(function (FinanceTransaction $invoice) {
                $invoice->setAttribute('remaining_amount', $this->remainingInvoiceAmount($invoice));
            });
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

    private function nextDueInvoiceQuery(Student $student): ?FinanceTransaction
    {
        return $student->financeTransactions()
            ->where('type', 'invoice')
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['open', 'partial', 'overdue'])
            ->whereNotNull('due_date')
            ->oldest('due_date')
            ->oldest()
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

    private function paymentPlanSummaryQuery(Student $student): array
    {
        $row = $student->financeTransactions()
            ->where('type', 'invoice')
            ->where(function ($query) {
                $query->whereNotNull('semester_id')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('tuition_agreement_id')
                            ->whereNotNull('reference')
                            ->where('reference', 'like', '% - %');
                    });
            })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as paid', ['paid'])
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as overdue', ['overdue'])
            ->selectRaw('SUM(CASE WHEN payment_status IN (?, ?) THEN 1 ELSE 0 END) as open', ['open', 'partial'])
            ->first();
        $total = (int) ($row->total ?? 0);
        $paid = (int) ($row->paid ?? 0);
        $overdue = (int) ($row->overdue ?? 0);
        $open = (int) ($row->open ?? 0);

        return [
            'total' => $total,
            'paid' => $paid,
            'open' => $open,
            'overdue' => $overdue,
            'label' => $total > 0 ? "{$paid} paid / {$open} open / {$overdue} overdue" : 'No semester payment plan',
        ];
    }

    private function createFinanceTransactions(array $validated, bool $canPostImmediately)
    {
        if ($validated['type'] === 'invoice') {
            $semesters = ($validated['payment_plan'] ?? 'full') === 'semester'
                ? $this->selectedTuitionSemesters($validated)
                : collect();
            $academicYear = $this->tuitionAcademicYear($validated, $semesters);
            $agreementKey = app(FinanceLedgerService::class)->tuitionAgreementKey(
                (int) $validated['student_id'],
                $academicYear?->id,
                $academicYear?->name ?? ($validated['academic_year'] ?? null)
            );
            $existingAgreement = TuitionAgreement::query()
                ->where('student_id', $validated['student_id'])
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($academicYear, $agreementKey, $validated) {
                    $query->where('agreement_key', $agreementKey);

                    if ($academicYear) {
                        $query->orWhere('academic_year_id', $academicYear->id)
                            ->orWhere(function ($legacy) use ($academicYear) {
                                $legacy->whereNull('agreement_key')
                                    ->whereNull('academic_year_id')
                                    ->whereHas('transactions', fn ($transactions) => $transactions->where('academic_year', $academicYear->name));
                            });

                        return;
                    }

                    $legacyYear = trim((string) ($validated['academic_year'] ?? ''));
                    $query->orWhere(function ($legacy) use ($legacyYear) {
                        $legacy->whereNull('agreement_key')
                            ->whereNull('academic_year_id');

                        if ($legacyYear !== '') {
                            $legacy->whereHas('transactions', fn ($transactions) => $transactions->where('academic_year', $legacyYear));
                        } else {
                            $legacy->whereDoesntHave('transactions', fn ($transactions) => $transactions->whereNotNull('academic_year'));
                        }
                    });
                })
                ->exists();
            if ($existingAgreement) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'This student already has a tuition agreement for the selected academic year.',
                ]);
            }
            $agreement = TuitionAgreement::create([
                'student_id' => $validated['student_id'],
                'academic_year_id' => $academicYear?->id,
                'created_by' => $validated['recorded_by'],
                'payment_method' => ($validated['payment_plan'] ?? 'full') === 'semester' ? 'semester' : 'full',
                'total_amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'status' => 'active',
                'agreed_at' => $validated['transaction_date'],
                'notes' => $validated['notes'] ?? null,
                'agreement_key' => $agreementKey,
            ]);
            $validated['tuition_agreement_id'] = $agreement->id;
            $validated['academic_year_id'] = $academicYear?->id;
            $validated['academic_year'] = $academicYear?->name ?? ($validated['academic_year'] ?? null);

            if (($validated['payment_plan'] ?? 'full') === 'semester') {
                return $this->createSemesterTuitionInvoices($validated, $semesters);
            }

            $payload = collect($validated)->except(['payment_plan', 'semester_ids', 'academic_year_id', 'collect_now'])->all();
            $invoice = FinanceTransaction::create($payload);
            $transactions = collect([$invoice]);

            if (! empty($validated['collect_now'])) {
                $paymentStatus = $canPostImmediately ? 'approved' : 'pending';
                $paymentPostingStatus = $canPostImmediately ? 'posted' : 'pending';
                $payment = FinanceTransaction::create([
                    'student_id' => $invoice->student_id,
                    'tuition_agreement_id' => $agreement->id,
                    'invoice_transaction_id' => $invoice->id,
                    'recorded_by' => $validated['recorded_by'],
                    'approved_by' => $canPostImmediately ? $validated['recorded_by'] : null,
                    'approved_at' => $canPostImmediately ? now() : null,
                    'type' => 'payment',
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'status' => $paymentStatus,
                    'posting_status' => $paymentPostingStatus,
                    'payment_status' => $canPostImmediately ? 'paid' : 'open',
                    'receipt_number' => app(FinanceLedgerService::class)->documentNumber([
                        'type' => 'payment',
                        'transaction_date' => $validated['transaction_date'],
                        'receipt_number' => null,
                    ], 'receipt_number'),
                    'reference' => $validated['reference'] ?? 'Full tuition payment',
                    'academic_year' => $validated['academic_year'] ?? null,
                    'transaction_date' => $validated['transaction_date'],
                    'notes' => 'Collected with full tuition agreement.',
                ]);
                $transactions->push($payment);
                if ($canPostImmediately) {
                    $agreement->update(['status' => 'completed']);
                }
            }

            return $transactions;
        }

        $payload = collect($validated)->except(['payment_plan', 'semester_ids', 'academic_year_id', 'collect_now'])->all();

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

    private function createSemesterTuitionInvoices(array $validated, $semesters)
    {
        $totalAmount = (float) $validated['amount'];
        $semesterCount = $semesters->count();
        $baseAmount = round($totalAmount / $semesterCount, 2);
        $allocated = 0.0;

        return $semesters
            ->values()
            ->map(function (Semester $semester, int $index) use ($validated, $semesterCount, $baseAmount, &$allocated) {
                $amount = $index === $semesterCount - 1 ? round((float) $validated['amount'] - $allocated, 2) : $baseAmount;
                $allocated += $amount;

                $payload = collect($validated)->except(['payment_plan', 'semester_ids', 'academic_year_id', 'collect_now'])->merge([
                    'amount' => $amount,
                    'semester_id' => $semester->id,
                    'invoice_number' => null,
                    'reference' => substr(trim(($validated['reference'] ?? 'Tuition').' - '.$semester->name.' '.$semester->academic_year), 0, 100),
                    'academic_year' => $semester->academic_year,
                    'due_date' => $semester->end_date ?: ($validated['due_date'] ?? null),
                ])->all();
                $ledger = app(FinanceLedgerService::class);
                $payload['invoice_number'] = $ledger->documentNumber($payload, 'invoice_number');
                $payload['balance_after'] = $ledger->balanceAfter($payload['student_id'], $payload['type'], (float) $payload['amount'], $payload['currency']);
                $payload['payment_status'] = $ledger->paymentStatusForTransaction($payload);

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

        if ($semesters->pluck('academic_year_id')->filter()->unique()->count() !== 1) {
            throw ValidationException::withMessages(['semester_ids' => 'All installments must belong to one academic year.']);
        }

        if ($semesters->contains(fn (Semester $semester) => ! $semester->academicYear || $semester->academicYear->isLocked())) {
            throw ValidationException::withMessages(['semester_ids' => 'Closed or archived semesters cannot receive a new tuition agreement.']);
        }

        return $semesters;
    }

    private function tuitionAcademicYear(array $validated, $semesters): ?AcademicYear
    {
        if ($semesters->isNotEmpty()) {
            return $semesters->first()->academicYear;
        }

        $student = Student::findOrFail($validated['student_id']);
        $academicYear = AcademicYear::query()
            ->whereKey($validated['academic_year_id'] ?? 0)
            ->where('university_id', $student->university_id)
            ->whereIn('status', ['upcoming', 'active'])
            ->first();

        if (! $academicYear && ! empty($validated['academic_year'])) {
            $academicYear = AcademicYear::query()
                ->where('university_id', $student->university_id)
                ->where('name', $validated['academic_year'])
                ->whereIn('status', ['upcoming', 'active'])
                ->first();
        }

        if (! $academicYear && AcademicYear::where('university_id', $student->university_id)->whereIn('status', ['upcoming', 'active'])->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Select an active or upcoming academic year for this tuition agreement.',
            ]);
        }

        return $academicYear;
    }

    private function authorizeFinanceEntry(?string $type): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        abort_unless($user->hasPermission('finance.view'), 403);

        $permission = match ($type) {
            'invoice' => 'finance.create_invoice',
            'payment' => 'finance.record_payment',
            'refund' => 'finance.refund',
            default => 'finance.record_expense',
        };

        abort_unless($user->hasPermission($permission), 403);
    }

    private function validateFinancePostingAuthority(Request $request, array $validated): void
    {
        $user = $request->user();
        if ($user->hasRole('super_administrator')) {
            return;
        }

        if ($validated['type'] !== 'invoice' && $validated['status'] === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Finance credits must be recorded as pending and approved by another authorized user.',
            ]);
        }

        if ($validated['type'] === 'invoice' && ! empty($validated['collect_now']) && ! $user->hasPermission('finance.record_payment')) {
            throw ValidationException::withMessages([
                'collect_now' => 'Recording a collected tuition payment requires the Record Payments permission.',
            ]);
        }
    }

    private function allowedFinanceEntryTypes(User $user): array
    {
        if ($user->hasRole('super_administrator')) {
            return ['invoice', 'payment', 'discount', 'scholarship', 'refund'];
        }

        return collect([
            'invoice' => 'finance.create_invoice',
            'payment' => 'finance.record_payment',
            'discount' => 'finance.record_expense',
            'scholarship' => 'finance.record_expense',
            'refund' => 'finance.refund',
        ])->filter(fn (string $permission) => $user->hasPermission($permission))->keys()->all();
    }

    private function approvableFinanceTypes(User $user): array
    {
        if ($user->hasRole('super_administrator')) {
            return ['payment', 'discount', 'scholarship', 'refund'];
        }

        return collect([
            'payment' => 'finance.approve_payment',
            'discount' => 'finance.approve_expense',
            'scholarship' => 'finance.approve_expense',
            'refund' => 'finance.approve_expense',
        ])->filter(fn (string $permission) => $user->hasPermission($permission))->keys()->all();
    }

    private function authorizeFinanceApproval(FinanceTransaction $transaction): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless($user->hasPermission('finance.view'), 403);
        $this->authorizeFinanceTransactionScope($user, $transaction);

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
        abort_unless($user->hasPermission('finance.view'), 403);
        $this->authorizeFinanceTransactionScope($user, $transaction);

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

        return $user->hasPermission('finance.view');
    }

    private function canManageStudentAccountBlock(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasPermission('finance.view')
            && $user->hasAnyPermission(['finance.create_invoice', 'finance.record_payment', 'finance.approve_payment']);
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

        $allocated = (float) $invoice->allocations()
            ->where('status', '!=', 'cancelled')
            ->where('posting_status', 'posted')
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');
        $remaining = round((float) $invoice->amount - $allocated, 2);

        if ((float) $transaction['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'The amount exceeds the remaining invoice balance of '.number_format($remaining, 2).' '.$invoice->currency.'.',
            ]);
        }

        return $invoice->id;
    }

    private function scopedStudentQuery(User $user)
    {
        $query = $this->hasGlobalFinanceScope($user)
            ? Student::withoutGlobalScope('organization')
            : Student::query();
        if (! $this->hasGlobalFinanceScope($user)) {
            OrganizationScope::apply($query, $user, 'student');
        }
        $this->applyFinanceOrganizationConstraint($query, $user, 'student');

        return $query;
    }

    private function scopedFinanceQuery(User $user)
    {
        $query = $this->hasGlobalFinanceScope($user)
            ? FinanceTransaction::withoutGlobalScope('organization')
            : FinanceTransaction::query();
        if (! $this->hasGlobalFinanceScope($user)) {
            OrganizationScope::apply($query, $user, 'student_record');
        }
        $this->applyFinanceOrganizationConstraint($query, $user, 'student_record');

        return $query;
    }

    private function applyFinanceOrganizationConstraint($query, User $user, string $modelType): void
    {
        if ($this->hasGlobalFinanceScope($user)) {
            return;
        }

        if ($user->department_id) {
            if ($modelType === 'student') {
                $query->where('department_id', $user->department_id);

                return;
            }

            if ($modelType === 'student_record') {
                $query->whereHas('student', fn ($student) => $student->where('department_id', $user->department_id));

                return;
            }
        }

        if ($user->college_id) {
            if ($modelType === 'student') {
                $query->whereHas('department', fn ($department) => $department->where('college_id', $user->college_id));

                return;
            }

            if ($modelType === 'student_record') {
                $query->whereHas('student.department', fn ($department) => $department->where('college_id', $user->college_id));

                return;
            }
        }

        if ($user->university_id) {
            if ($modelType === 'student') {
                $query->where('university_id', $user->university_id);

                return;
            }

            if ($modelType === 'student_record') {
                $query->whereHas('student', fn ($student) => $student->where('university_id', $user->university_id));

                return;
            }
        }

        $query->whereRaw('1 = 0');
    }

    private function hasGlobalFinanceScope(User $user): bool
    {
        return $user->hasRole('super_administrator')
            || $user->hasDirectPermissionGrant('finance.view_global');
    }

    private function authorizeStudentScope(User $user, Student $student): void
    {
        $visible = $this->scopedStudentQuery($user)
            ->whereKey($student->id)
            ->exists();

        abort_unless($visible, 404);
    }

    private function authorizeFinanceTransactionScope(User $user, FinanceTransaction $transaction): void
    {
        $visible = $this->scopedFinanceQuery($user)
            ->whereKey($transaction->id)
            ->exists();

        abort_unless($visible, 404);
    }

    private function paymentStatusForBalances($balances): string
    {
        return collect($balances)->contains(fn ($balance) => (float) ($balance['balance'] ?? 0) > 0)
            ? 'open'
            : 'paid';
    }

    private function remainingInvoiceAmount(FinanceTransaction $invoice): float
    {
        $allocated = (float) $invoice->allocations()
            ->where('posting_status', 'posted')
            ->where('status', '!=', 'cancelled')
            ->whereIn('type', FinanceTransaction::creditTypes())
            ->sum('amount');

        return max(0, round((float) $invoice->amount - $allocated, 2));
    }

    private function reversalType(string $type): string
    {
        return in_array($type, FinanceTransaction::creditTypes(), true) ? 'refund' : 'discount';
    }
}
