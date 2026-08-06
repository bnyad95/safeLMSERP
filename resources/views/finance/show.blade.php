<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold text-gray-900">{{ $selectedStudent->full_name }}</h2>
                <p class="mt-1 text-sm text-gray-600">Student finance workspace: input records, review balances, and manage the ledger.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <a href="{{ route('finance') }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                    Back to Finance
                </a>
            </div>
        </div>
    </x-slot>

    <div class="finance-workspace py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @php
                $initialFinanceType = old('type', $allowedEntryTypes[0] ?? 'invoice');
                $initialPaymentPlan = old('payment_plan', 'full');
                $initialSemesterIds = collect(old('semester_ids', []))->map(fn ($id) => (string) $id)->values();
            @endphp
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <div
                x-data="{
                        showForm: {{ $errors->any() ? 'true' : 'false' }},
                        recordType: @js($initialFinanceType),
                        paymentPlan: @js($initialPaymentPlan),
                        amount: @js(old('amount', '')),
                        selectedSemesters: @js($initialSemesterIds),
                        formattedAmount() {
                            const amount = Number.parseFloat(this.amount || 0);

                            return Number.isFinite(amount) ? amount.toFixed(2) : '0.00';
                        },
                        perSemesterAmount() {
                            const amount = Number.parseFloat(this.amount || 0);
                            const count = this.selectedSemesters.length;

                            return count > 0 ? (amount / count).toFixed(2) : '0.00';
                        }
                    }"
                class="space-y-6"
            >
                <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $selectedStudent->full_name }}</h3>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $selectedStudent->student_id }} / {{ $selectedStudent->email }}</p>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $selectedStudent->department->name ?? 'No department' }} / {{ $selectedStudent->status }}</p>
                            @if($paymentPlanSummary['total'] > 0)
                                <p class="mt-2 text-sm font-medium text-blue-700">{{ $paymentPlanSummary['total'] }} semester installments: {{ $paymentPlanSummary['label'] }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($selectedStudent->user?->account_blocked_at)
                                <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Login blocked</span>
                            @else
                                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ ucfirst($selectedPaymentStatus) }} account</span>
                            @endif
                            @forelse($selectedBalances as $balance)
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Balance {{ number_format((float) $balance['balance'], 2) }} {{ $balance['currency'] }}</span>
                            @empty
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">No balance</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <a href="{{ route('finance.statement', $selectedStudent) }}" class="inline-flex w-full justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                            Print Statement
                        </a>
                        <a href="{{ route('finance.export', ['student_id' => $selectedStudent->id]) }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                            Export Student CSV
                        </a>
                        @if($canCreateInvoice || $canRecordPayment)
                            <button type="button" @click="showForm = ! showForm" class="w-full rounded-md border border-gray-900 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 sm:w-auto">
                                <span x-show="! showForm">Add Finance Record</span>
                                <span x-show="showForm">Close Form</span>
                            </button>
                        @endif
                    </div>
                    @if($canManageAccountBlock)
                        <div class="mt-4 rounded-md border {{ $selectedStudent->user?->account_blocked_at ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold {{ $selectedStudent->user?->account_blocked_at ? 'text-red-900' : 'text-amber-900' }}">Student login access</p>
                                    @if($selectedStudent->user?->account_blocked_at)
                                        <p class="mt-1 text-sm text-red-700">
                                            Blocked {{ $selectedStudent->user->account_blocked_at->format('Y-m-d H:i') }}
                                            @if($selectedStudent->user->accountBlocker)
                                                by {{ $selectedStudent->user->accountBlocker->name }}
                                            @endif
                                        </p>
                                        @if($selectedStudent->user->account_block_reason)
                                            <p class="mt-1 break-words text-sm text-red-700">{{ $selectedStudent->user->account_block_reason }}</p>
                                        @endif
                                    @else
                                        <p class="mt-1 text-sm text-amber-800">Block login only when the student has unpaid tuition. Use unblock after the balance is resolved or finance approves access.</p>
                                    @endif
                                </div>

                                @if($selectedStudent->user?->account_blocked_at)
                                    <form method="POST" action="{{ route('finance.students.account-block.destroy', $selectedStudent) }}" onsubmit="return confirm('Unblock this student login account?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 sm:w-auto">Unblock Account</button>
                                    </form>
                                @elseif($selectedStudent->user)
                                    <form method="POST" action="{{ route('finance.students.account-block.store', $selectedStudent) }}" class="flex w-full flex-col gap-2 lg:max-w-lg">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reason for overdue tuition hold" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <button type="submit" class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 sm:w-auto" onclick="return confirm('Block this student login because tuition is overdue?')">Block Account</button>
                                    </form>
                                @else
                                    <span class="rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-600">No linked login account</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>

                <section class="min-w-0 overflow-hidden border-y border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tuition Agreements</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Agreed annual tuition and payment schedules.</p>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $tuitionAgreements->count() }} recent</span>
                    </div>
                    <div class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                        @forelse($tuitionAgreements as $agreement)
                            <div class="grid gap-3 px-4 py-4 text-sm sm:grid-cols-4 sm:px-5">
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Academic Year</p>
                                    <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $agreement->academicYear->name ?? 'Legacy agreement' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Method</p>
                                    <p class="mt-1 text-gray-800 dark:text-gray-200">{{ $agreement->payment_method === 'semester' ? 'Semester installments' : 'Full payment' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Agreed Tuition</p>
                                    <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $agreement->total_amount, 2) }} {{ $agreement->currency }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Schedule</p>
                                    <p class="mt-1 text-gray-800 dark:text-gray-200">{{ $agreement->transactions_count }} record(s) / {{ ucfirst($agreement->status) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No tuition agreement has been recorded yet.</p>
                        @endforelse
                    </div>
                </section>

                @if($canCreateInvoice || $canRecordPayment)
                <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5" x-show="showForm" x-transition>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900">Tuition & Finance Record</h3>
                        <p class="mt-1 text-sm text-gray-500">Invoice and receipt numbers are generated automatically unless you enter one.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('finance.transactions.store') }}" class="mt-5 grid grid-cols-1 gap-4">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">

                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <select name="type" x-model="recordType" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                @foreach($types as $value => $label)
                                    @if(in_array($value, $allowedEntryTypes, true))
                                        <option value="{{ $value }}" @selected($initialFinanceType === $value)>{{ $label }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">
                                    <span x-show="recordType === 'invoice'">Agreed Tuition Charge</span>
                                    <span x-show="recordType !== 'invoice'">Amount</span>
                                </label>
                                <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" value="{{ old('amount') }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>

                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Currency</label>
                                <select name="currency" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                    <option value="IQD" @selected(old('currency', 'IQD') === 'IQD')>IQD</option>
                                    <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                                </select>
                            </div>
                        </div>

                        @if($canCreateInvoice)
                            <div x-show="recordType === 'invoice'" class="min-w-0 rounded-md border border-blue-100 bg-blue-50 p-4">
                                <label class="block text-sm font-semibold text-gray-900">Student tuition agreement</label>
                                <p class="mt-1 text-xs text-blue-900">Enter the agreed tuition charge, choose how the student wants to pay it, and the system will create the correct invoice schedule.</p>
                                <select name="payment_plan" x-model="paymentPlan" class="mt-3 block w-full min-w-0 rounded-md border-blue-200 bg-white text-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="full" @selected(old('payment_plan', 'full') === 'full')>Full tuition paid once</option>
                                    <option value="semester" @selected(old('payment_plan') === 'semester')>Divide tuition by semesters</option>
                                </select>

                                <div x-show="paymentPlan === 'full'" class="mt-4 rounded-md border border-blue-200 bg-white px-3 py-2 text-sm text-blue-900">
                                    <p>One tuition invoice will be created for <span class="font-semibold" x-text="formattedAmount()"></span>.</p>
                                    @if($canCollectPayment)
                                    <label class="mt-3 flex items-start gap-2">
                                        <input type="checkbox" name="collect_now" value="1" @checked(old('collect_now')) class="mt-0.5 rounded border-blue-300 text-blue-600 focus:ring-blue-500">
                                        <span>
                                            <span class="block font-semibold">Collect the full payment now</span>
                                            <span class="block text-xs">{{ $canPostImmediately ? 'Posts immediately and generates a receipt.' : 'Records a payment for independent approval before posting.' }}</span>
                                        </span>
                                    </label>
                                    @endif
                                </div>

                                <div x-show="paymentPlan === 'semester'" class="mt-4 space-y-3">
                                    <div class="rounded-md border border-blue-200 bg-white px-3 py-2 text-sm text-blue-900">
                                        The total tuition charge will be divided into
                                        <span class="font-semibold" x-text="selectedSemesters.length"></span>
                                        semester invoices:
                                        <span class="font-semibold" x-text="perSemesterAmount()"></span>
                                        each.
                                    </div>

                                    <div class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                                        @forelse($semesterOptions as $semester)
                                            <label class="flex min-w-0 items-start gap-2 rounded-md border border-blue-100 bg-white p-3 text-sm {{ $semester->end_date ? '' : 'opacity-60' }}">
                                                <input
                                                    type="checkbox"
                                                    name="semester_ids[]"
                                                    value="{{ $semester->id }}"
                                                    x-model="selectedSemesters"
                                                    @disabled(! $semester->end_date)
                                                    @checked(in_array((string) $semester->id, old('semester_ids', []), true))
                                                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                >
                                                <span class="min-w-0">
                                                    <span class="block truncate font-medium text-gray-900">{{ $semester->name }} {{ $semester->academic_year }}</span>
                                                    <span class="block text-xs text-gray-500">{{ $semester->end_date ? 'Due '.\Illuminate\Support\Carbon::parse($semester->end_date)->format('Y-m-d') : 'Missing semester end date' }}</span>
                                                </span>
                                            </label>
                                        @empty
                                            <span class="text-sm text-blue-900">No semesters are defined for this student's university yet.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="min-w-0" x-show="recordType !== 'invoice'">
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" :disabled="recordType === 'invoice'" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                @foreach(($creationStatuses ?? $statuses) as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="status" value="pending" :disabled="recordType !== 'invoice'">

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Transaction Date</label>
                                <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            </div>
                            <div x-show="recordType !== 'invoice' || paymentPlan !== 'semester'" class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                                <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Reference</label>
                                <input type="text" name="reference" value="{{ old('reference') }}" placeholder="Receipt, invoice, voucher..." class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Academic Year</label>
                                <select name="academic_year_id" :required="recordType === 'invoice'" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select academic year</option>
                                    @foreach($academicYearOptions as $academicYear)
                                        <option value="{{ $academicYear->id }}" @selected(old('academic_year_id') == $academicYear->id)>{{ $academicYear->name }} / {{ ucfirst($academicYear->status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div x-show="recordType === 'invoice'" class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Invoice Number</label>
                                <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" placeholder="Auto for invoices" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div x-show="recordType !== 'invoice'" class="min-w-0">
                                <label class="block text-sm font-medium text-gray-700">Receipt Number</label>
                                <input type="text" name="receipt_number" value="{{ old('receipt_number') }}" placeholder="Auto for payments/credits" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div x-show="['payment', 'discount', 'scholarship'].includes(recordType)" class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700">Apply Credit To Invoice</label>
                            <select name="invoice_transaction_id" class="mt-1 block w-full min-w-0 truncate rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">No invoice allocation</option>
                                @foreach($invoiceOptions as $invoice)
                                    <option value="{{ $invoice->id }}" @selected(old('invoice_transaction_id') == $invoice->id)>
                                        {{ $invoice->documentNumber() }} / {{ number_format((float) ($invoice->remaining_amount ?? $invoice->amount), 2) }} {{ $invoice->currency }} remaining / {{ ucfirst($invoice->payment_status) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Used for payments, discounts, and scholarships. Currency and student must match the invoice.</p>
                        </div>

                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="3" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                                Save Finance Record
                            </button>
                        </div>
                    </form>
                </section>
            @endif

            <form method="GET" action="{{ route('finance.students.show', $selectedStudent) }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Ledger Filters</h3>
                    <p class="text-sm font-medium text-gray-600">Filtered balance: {{ $filteredBalanceText }}</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All types</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Record Status</label>
                        <select name="status" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All statuses</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                        <select name="payment_status" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All payment</option>
                            @foreach($paymentStatuses as $value => $label)
                                <option value="{{ $value }}" @selected($filters['payment_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Currency</label>
                        <select name="currency" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All currencies</option>
                            @foreach(['IQD', 'USD'] as $currency)
                                <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Academic Year</label>
                        <select name="academic_year" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All years</option>
                            @foreach($filterOptions['academicYears'] as $year)
                                <option value="{{ $year }}" @selected($filters['academic_year'] === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Date From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Date To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Filter</button>
                        <a href="{{ route('finance.students.show', $selectedStudent) }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">Reset</a>
                    </div>
                </div>
            </form>

            <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                    <h3 class="text-base font-semibold text-gray-900">Student Ledger</h3>
                </div>
                <div class="divide-y divide-gray-100 md:hidden">
                    @forelse($transactions as $transaction)
                        @php
                            $statusClasses = [
                                'paid' => 'bg-emerald-50 text-emerald-700',
                                'approved' => 'bg-emerald-50 text-emerald-700',
                                'partial' => 'bg-amber-50 text-amber-700',
                                'overdue' => 'bg-red-50 text-red-700',
                                'cancelled' => 'bg-gray-100 text-gray-600',
                                'open' => 'bg-blue-50 text-blue-700',
                                'pending' => 'bg-blue-50 text-blue-700',
                            ];
                            $paymentClass = $statusClasses[$transaction->payment_status] ?? 'bg-gray-100 text-gray-700';
                            $recordClass = $statusClasses[$transaction->status] ?? 'bg-gray-100 text-gray-700';
                            $isSemesterTuitionInstallment = $transaction->type === 'invoice' && $transaction->reference && str_contains($transaction->reference, ' - ');
                        @endphp
                        <article class="space-y-4 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-semibold text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $transaction->transaction_date->format('Y-m-d') }} / {{ ucfirst($transaction->type) }}</p>
                                    @if($transaction->receipt_number && $transaction->posting_status === 'posted' && $transaction->status !== 'cancelled')
                                        <a href="{{ route('finance.transactions.receipt', $transaction) }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-900">Print receipt</a>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-gray-900">{{ number_format((float) $transaction->amount, 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ $transaction->currency }}</p>
                                </div>
                            </div>
                            @if($transaction->reference)
                                <p class="break-words text-xs text-gray-500">{{ $transaction->reference }}</p>
                            @endif
                            @if($isSemesterTuitionInstallment)
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Semester tuition installment</span>
                                    @if($transaction->due_date)
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">Due {{ $transaction->due_date->format('Y-m-d') }}</span>
                                    @endif
                                </div>
                            @endif
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Record</p>
                                    <span class="mt-1 inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ ucfirst($transaction->status) }}</span>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Payment</p>
                                    <span class="mt-1 inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $paymentClass }}">{{ ucfirst($transaction->payment_status) }}</span>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Balance</p>
                                    <p class="mt-1 font-semibold text-gray-900">{{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Approval</p>
                                    <p class="mt-1 break-words text-gray-700">{{ $transaction->approver->name ?? '-' }}</p>
                                </div>
                            </div>
                            @if(($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending') || ($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id))
                                <div class="flex flex-col gap-2">
                                    @if($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending')
                                        <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                        </form>
                                    @endif
                                    @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                        <form method="POST" action="{{ route('finance.transactions.void', $transaction) }}" class="space-y-2" onsubmit="return confirm('Void this finance record and create a reversal entry?')">
                                            @csrf
                                            <input type="text" name="notes" required placeholder="Reason" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <button type="submit" class="w-full rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700">Void</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-gray-500">No finance records match the current filters.</div>
                    @endforelse
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-[920px] divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Document</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Amount</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Payment</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Balance</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Approval</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($transactions as $transaction)
                                @php
                                    $statusClasses = [
                                        'paid' => 'bg-emerald-50 text-emerald-700',
                                        'approved' => 'bg-emerald-50 text-emerald-700',
                                        'partial' => 'bg-amber-50 text-amber-700',
                                        'overdue' => 'bg-red-50 text-red-700',
                                        'cancelled' => 'bg-gray-100 text-gray-600',
                                        'open' => 'bg-blue-50 text-blue-700',
                                        'pending' => 'bg-blue-50 text-blue-700',
                                    ];
                                    $paymentClass = $statusClasses[$transaction->payment_status] ?? 'bg-gray-100 text-gray-700';
                                    $recordClass = $statusClasses[$transaction->status] ?? 'bg-gray-100 text-gray-700';
                                    $isSemesterTuitionInstallment = $transaction->type === 'invoice' && $transaction->reference && str_contains($transaction->reference, ' - ');
                                @endphp
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-600">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600">
                                        <div class="font-medium text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</div>
                                        @if($transaction->receipt_number && $transaction->posting_status === 'posted' && $transaction->status !== 'cancelled')
                                            <a href="{{ route('finance.transactions.receipt', $transaction) }}" target="_blank" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Print receipt</a>
                                        @endif
                                        @if($transaction->invoice)
                                            <div class="text-xs text-blue-700">Applied to {{ $transaction->invoice->documentNumber() }}</div>
                                        @endif
                                        @if($transaction->originalTransaction)
                                            <div class="text-xs text-amber-700">Reversal for {{ $transaction->originalTransaction->documentNumber() }}</div>
                                        @endif
                                        @if($transaction->reference)
                                            <div class="text-xs text-gray-500">{{ $transaction->reference }}</div>
                                        @endif
                                        @if($isSemesterTuitionInstallment)
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">Semester tuition installment</span>
                                                @if($transaction->due_date)
                                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Due {{ $transaction->due_date->format('Y-m-d') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-600">{{ ucfirst($transaction->type) }}</td>
                                    <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</td>
                                    <td class="px-5 py-3"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ ucfirst($transaction->status) }}</span></td>
                                    <td class="px-5 py-3"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $paymentClass }}">{{ ucfirst($transaction->payment_status) }}</span></td>
                                    <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600">
                                        <div>{{ $transaction->approver->name ?? '-' }}</div>
                                        @if($transaction->approved_at)
                                            <div class="text-xs text-gray-500">{{ $transaction->approved_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                        @if($transaction->voided_at)
                                            <div class="text-xs text-red-700">Voided {{ $transaction->voided_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            @if($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending')
                                                <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                </form>
                                            @endif
                                            @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                                <form method="POST" action="{{ route('finance.transactions.void', $transaction) }}" class="flex justify-end gap-2" onsubmit="return confirm('Void this finance record and create a reversal entry?')">
                                                    @csrf
                                                    <input type="text" name="notes" required placeholder="Reason" class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Void</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-8 text-center text-sm text-gray-500">No finance records match the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($transactions->hasPages())
                    <div class="border-t border-gray-200 px-4 py-4 sm:px-5">{{ $transactions->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
