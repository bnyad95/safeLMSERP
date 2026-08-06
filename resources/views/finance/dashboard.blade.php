<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Accounting &amp; Finance</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">Finance Dashboard</h2>
                <p class="mt-1 truncate text-sm text-gray-600 dark:text-gray-400">{{ $scopeLabel }}</p>
            </div>
            <x-dashboard-clock />
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    @php
                        $tone = [
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
                            'red' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300',
                        ][$stat['tone']];
                    @endphp
                    <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="inline-flex rounded-md border px-2 py-1 text-xs font-semibold {{ $tone }}">{{ $stat['label'] }}</div>
                        <p class="mt-4 break-words text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </section>
                @endforeach
            </div>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Priority Work</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Items requiring attention in the current finance scope.</p>
                </div>
                <div class="grid divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-gray-800">
                    <a href="{{ route('finance', ['payment_status' => 'overdue']) }}" class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue invoices</p>
                        <p class="mt-2 text-2xl font-semibold text-red-700 dark:text-red-300">{{ number_format($operationalStats['overdueInvoices']) }}</p>
                    </a>
                    <a href="{{ route('finance.tuition-reminders.index', ['date_from' => today()->format('Y-m-d'), 'date_to' => today()->addDays(30)->format('Y-m-d')]) }}" class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Due within 30 days</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($operationalStats['dueSoon']) }}</p>
                    </a>
                    <a href="{{ route('finance.tuition-reminders.index') }}" class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/60">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reminders sent in 7 days</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($operationalStats['remindersLastSevenDays']) }}</p>
                    </a>
                </div>
            </section>

            <div class="grid min-w-0 gap-6 xl:grid-cols-2">
                <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Overdue Tuition</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Oldest overdue invoices first.</p>
                        </div>
                        @if($canSendTuitionReminder)
                            <a href="{{ route('finance.tuition-reminders.index', ['payment_status' => 'overdue']) }}" class="shrink-0 text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Remind</a>
                        @endif
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($overdueInvoices as $invoice)
                            <a href="{{ route('finance.students.show', $invoice->student_id) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-gray-800/60">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $invoice->student?->full_name ?? 'Unknown student' }}</p>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $invoice->student?->student_id }} / {{ $invoice->invoice_number ?? 'Invoice' }}</p>
                                </div>
                                <div class="shrink-0 sm:text-right">
                                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ number_format((float) $invoice->remaining_amount, 2) }} {{ $invoice->currency }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Due {{ $invoice->due_date?->format('Y-m-d') ?? 'not set' }}</p>
                                </div>
                            </a>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No overdue tuition invoices.</p>
                        @endforelse
                    </div>
                </section>

                <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Upcoming Due Dates</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open installments due in the next 30 days.</p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($upcomingInvoices as $invoice)
                            <a href="{{ route('finance.students.show', $invoice->student_id) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-gray-800/60">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $invoice->student?->full_name ?? 'Unknown student' }}</p>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $invoice->invoice_number ?? 'Invoice' }}</p>
                                </div>
                                <div class="shrink-0 sm:text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $invoice->remaining_amount, 2) }} {{ $invoice->currency }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $invoice->due_date?->format('Y-m-d') }}</p>
                                </div>
                            </a>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No tuition installments are due in the next 30 days.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="grid min-w-0 gap-6 xl:grid-cols-[1.5fr_0.7fr]">
                <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Recent Finance Activity</h3>
                        <a href="{{ route('finance') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">View ledger</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950/50">
                                <tr class="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    <th class="px-5 py-3">Student</th>
                                    <th class="px-5 py-3">Record</th>
                                    <th class="px-5 py-3">Amount</th>
                                    <th class="px-5 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($recentTransactions as $transaction)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $transaction->student?->full_name ?? 'Unknown student' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ ucfirst($transaction->type) }}<span class="block text-xs text-gray-500 dark:text-gray-400">{{ $transaction->documentNumber() ?? $transaction->reference ?? $transaction->transaction_date?->format('Y-m-d') }}</span></td>
                                        <td class="whitespace-nowrap px-5 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ ucfirst($transaction->posting_status) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No finance activity has been recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Quick Actions</h3>
                        <div class="mt-4 grid gap-3">
                            <a href="{{ route('finance') }}" class="inline-flex justify-center rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">Find a student</a>
                            @if($canCreateRecord)
                                <a href="{{ route('finance') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">Add finance record</a>
                            @endif
                            @if($canApproveFinance)
                                <a href="{{ route('finance', ['status' => 'pending']) }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">Review approvals</a>
                            @endif
                            @if($canSendTuitionReminder)
                                <a href="{{ route('finance.tuition-reminders.index') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">Send tuition reminder</a>
                            @endif
                            <a href="{{ route('finance.export') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">Export finance report</a>
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Account Holds</h3>
                        <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($operationalStats['blockedAccounts']) }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Student accounts currently blocked for finance reasons.</p>
                    </section>

                    @if($recentReminders->isNotEmpty())
                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Recent Reminders</h3>
                            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($recentReminders as $reminder)
                                    <div class="py-3 first:pt-0 last:pb-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $reminder->student?->full_name ?? $reminder->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $reminder->created_at->diffForHumans() }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
