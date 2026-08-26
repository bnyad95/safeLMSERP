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
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($stats as $stat)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            @if($installmentPlanOverflowWarning)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                    {{ $installmentPlanOverflowWarning }}
                </div>
            @endif

            <div class="space-y-6">
                <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $selectedStudent->full_name }}</h3>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $selectedStudent->student_id }} / {{ $selectedStudent->email }}</p>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $selectedStudent->department->name ?? 'No department' }} / {{ $selectedStudent->status }}</p>
                            @if($paymentPlanSummary['total'] > 0)
                                <p class="mt-2 text-sm font-medium text-blue-700">{{ $paymentPlanSummary['total'] }} semester installments: {{ $paymentPlanSummary['label'] }}</p>
                            @elseif($selectedStudent->preferred_payment_method && $tuitionAgreements->isEmpty())
                                <p class="mt-2 text-sm font-medium text-blue-700">
                                    Registrar recorded plan: {{ ['full' => 'full tuition paid once', 'semester' => 'divide tuition by semesters', 'per_credit' => 'per-credit, billed automatically'][$selectedStudent->preferred_payment_method] ?? $selectedStudent->preferred_payment_method }}{{ $selectedStudent->preferred_installment_count ? ' ('.$selectedStudent->preferred_installment_count.' installments)' : '' }} — no tuition agreement created yet.
                                </p>
                            @endif
                            @if($selectedStudent->scholarship_percentage > 0)
                                <p class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    Scholarship: {{ rtrim(rtrim(number_format((float) $selectedStudent->scholarship_percentage, 2), '0'), '.') }}% of tuition covered automatically on every invoice.
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if($selectedStudent->user?->account_blocked_at)
                                <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Login blocked</span>
                            @else
                                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Balance: {{ ucfirst($selectedPaymentStatus) }}</span>
                            @endif
                            @forelse($selectedBalances as $balance)
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Remaining Due {{ money($balance['balance'], $balance['currency']) }} {{ $balance['currency'] }}</span>
                            @empty
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">No remaining due</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <div x-data="{ ledgerPrintLoaded: false }" class="contents">
                            <button type="button" x-on:click="ledgerPrintLoaded = true; $dispatch('open-modal', 'print-ledger')" class="inline-flex w-full justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                                Print Statement
                            </button>
                            @include('finance.partials.print-ledger')
                        </div>
                        <div x-data="{ statementLoaded: false }" class="contents">
                            <button type="button" x-on:click="statementLoaded = true; $dispatch('open-modal', 'print-statement')" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                                Report
                            </button>
                            @include('finance.partials.print-statement')
                        </div>
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'export-student-csv')" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            Export Student CSV
                        </button>
                        @if($canCreateInvoice || $canRecordPayment)
                            <button type="button" x-data x-on:click="$dispatch('open-modal', 'add-finance-record')" class="inline-flex w-full justify-center rounded-md border border-gray-900 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 sm:w-auto dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                                Add Finance Record
                            </button>
                        @endif
                        @if($canCreateInvoice)
                            <button type="button" x-data x-on:click="$dispatch('open-modal', 'generate-tuition-charge')" class="inline-flex w-full justify-center rounded-md border border-gray-900 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 sm:w-auto dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                                Generate Tuition Charge
                            </button>
                        @endif
                    </div>
                    @if($canManageAccountBlock)
                        <div class="mt-4 rounded-md border {{ $selectedStudent->user?->account_blocked_at ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold {{ $selectedStudent->user?->account_blocked_at ? 'text-red-900' : 'text-amber-900' }}">Student login access</p>
                                    @if($selectedStudent->user?->account_blocked_at)
                                        <p class="mt-0.5 text-xs text-red-700">
                                            Blocked {{ $selectedStudent->user->account_blocked_at->format('Y-m-d H:i') }}
                                            @if($selectedStudent->user->accountBlocker)
                                                by {{ $selectedStudent->user->accountBlocker->name }}
                                            @endif
                                        </p>
                                        @if($selectedStudent->user->account_block_reason)
                                            <p class="mt-0.5 break-words text-xs text-red-700">{{ $selectedStudent->user->account_block_reason }}</p>
                                        @endif
                                    @else
                                        <p class="mt-0.5 text-xs text-amber-800">Block only when tuition is unpaid; unblock once resolved.</p>
                                    @endif
                                </div>

                                @if($selectedStudent->user?->account_blocked_at)
                                    <form method="POST" action="{{ route('finance.students.account-block.destroy', $selectedStudent) }}" onsubmit="return confirm('Unblock this student login account?')" class="shrink-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 sm:w-auto">Unblock Account</button>
                                    </form>
                                @elseif($selectedStudent->user)
                                    <form method="POST" action="{{ route('finance.students.account-block.store', $selectedStudent) }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center lg:max-w-md">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Reason for hold" class="w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:w-48">
                                        <button type="submit" class="w-full shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700 sm:w-auto" onclick="return confirm('Block this student login because tuition is overdue?')">Block Account</button>
                                    </form>
                                @else
                                    <span class="shrink-0 rounded-md bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-600">No linked login account</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>

                @include('finance.partials.generate-tuition-charge')
                @include('finance.partials.export-student-csv')
                @include('finance.partials.add-finance-record')

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
                                    <p class="mt-1 text-gray-800 dark:text-gray-200">{{ ['semester' => 'Semester installments', 'per_credit' => 'Billed automatically each semester'][$agreement->payment_method] ?? 'Full payment' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Agreed Tuition</p>
                                    <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ money($agreement->total_amount, $agreement->currency) }} {{ $agreement->currency }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Schedule</p>
                                    <p class="mt-1 text-gray-800 dark:text-gray-200">
                                        {{ $agreement->transactions_count }} record(s) / {{ ucfirst($agreement->status) }}
                                        @if($agreement->isInstallmentPlan())
                                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                {{ $agreement->installments_generated }} of {{ $agreement->installment_count }} installments invoiced
                                                @if($agreement->remainingInstallments() > 0)
                                                    &mdash; remaining installments are created automatically as future semesters are added
                                                @endif
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No tuition agreement has been recorded yet.</p>
                        @endforelse
                    </div>
                </section>



            @php
                $advancedLedgerFilterActive = collect([
                    $filters['status'], $filters['payment_status'], $filters['currency'], $filters['academic_year'],
                ])->filter(fn ($value) => ! blank($value))->isNotEmpty();
            @endphp

            <form method="GET" action="{{ route('finance.students.show', $selectedStudent) }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5" x-data="{ showMore: {{ $advancedLedgerFilterActive ? 'true' : 'false' }} }">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Ledger Filters</h3>
                    <p class="text-sm font-medium text-gray-600">Filtered remaining due: {{ $filteredBalanceText }}</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All types</option>
                            <option value="credits" @selected($filters['type'] === 'credits')>Payments, Discounts, Scholarships &amp; Refunds</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
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
                    <div class="flex items-end justify-end">
                        <button type="button" @click="showMore = ! showMore" class="text-sm font-semibold text-blue-700 hover:underline">
                            <span x-show="! showMore">More filters</span>
                            <span x-show="showMore" x-cloak>Fewer filters</span>
                        </button>
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4" x-show="showMore" x-transition x-cloak>
                        <div class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-4">
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
                        </div>
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
                                    <p class="text-sm font-semibold text-gray-900">{{ money($transaction->amount, $transaction->currency) }}</p>
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
                                    <span class="mt-1 inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ $transaction->statusLabel() }}</span>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Remaining Due</p>
                                    <p class="mt-1 font-semibold text-gray-900">{{ $transaction->balance_after !== null ? money($transaction->balance_after, $transaction->currency).' '.$transaction->currency : '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Approval</p>
                                    <p class="mt-1 break-words text-gray-700">{{ $transaction->approver->name ?? '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium uppercase text-gray-500">Reason</p>
                                    <p class="mt-1 break-words text-gray-700">{{ $transaction->notes ?: '-' }}</p>
                                </div>
                            </div>
                            @if(($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending') || ($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id))
                                <div class="flex flex-col gap-2">
                                    @if($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending')
                                        <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" data-submit-once>
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
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Remaining Due</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Approval</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Reason</th>
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
                                    <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ money($transaction->amount, $transaction->currency) }} {{ $transaction->currency }}</td>
                                    <td class="px-5 py-3"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ $transaction->statusLabel() }}</span></td>
                                    <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ $transaction->balance_after !== null ? money($transaction->balance_after, $transaction->currency).' '.$transaction->currency : '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600">
                                        <div>{{ $transaction->approver->name ?? '-' }}</div>
                                        @if($transaction->approved_at)
                                            <div class="text-xs text-gray-500">{{ $transaction->approved_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                        @if($transaction->voided_at)
                                            <div class="text-xs text-red-700">Voided {{ $transaction->voided_at->format('Y-m-d H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                            <form id="void-form-{{ $transaction->id }}" method="POST" action="{{ route('finance.transactions.void', $transaction) }}" onsubmit="return confirm('Void this finance record and create a reversal entry?')">
                                                @csrf
                                                <input type="text" name="notes" required placeholder="Reason" class="w-36 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </form>
                                        @elseif($transaction->notes)
                                            <span class="text-xs text-gray-600">{{ $transaction->notes }}</span>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex flex-col items-end gap-2">
                                            @if($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending')
                                                <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" data-submit-once>
                                                    @csrf
                                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                </form>
                                            @endif
                                            @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                                <button type="submit" form="void-form-{{ $transaction->id }}" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Void</button>
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
