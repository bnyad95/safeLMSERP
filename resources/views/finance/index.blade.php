<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold text-gray-900">Student Finance</h2>
                <p class="mt-1 text-sm text-gray-600">Search students and record invoices, payments, discounts, scholarships, and refunds.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if($canSendTuitionReminder)
                    <a href="{{ route('finance.tuition-reminders.index', request()->query()) }}" class="inline-flex w-full justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                        Tuition Reminder
                    </a>
                @endif
                <a href="{{ route('finance.export', array_merge(request()->query(), ['student_id' => $selectedStudent?->id])) }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                    Export CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="finance-workspace py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            @php
                $hasAdvancedFinanceFilters = (bool) ($filters['payment_status'] || $filters['currency'] || $filters['academic_year'] || $filters['date_from'] || $filters['date_to']);
            @endphp
            <form method="GET" action="{{ route('finance') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5" x-data="{ showMore: {{ $hasAdvancedFinanceFilters ? 'true' : 'false' }} }">
                <input type="hidden" name="applied" value="1">
                @if($selectedStudent)
                    <input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">
                @endif
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="min-w-0 sm:col-span-2 lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Student, email, ID, phone" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
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
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Apply</button>
                        <a href="{{ route('finance', $selectedStudent ? ['student_id' => $selectedStudent->id] : []) }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">Reset</a>
                    </div>
                </div>

                <button type="button" x-on:click="showMore = ! showMore" class="mt-4 flex items-center gap-1 text-sm font-semibold text-indigo-700 hover:underline">
                    <span x-text="showMore ? 'Fewer filters' : 'More filters'"></span>
                    <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': showMore }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="showMore" x-cloak class="mt-4 grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-5">
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
                </div>
            </form>

            <div class="min-w-0 space-y-4">
                @if($shouldLoadStudents)
                <section class="min-w-0 space-y-6">
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                            <h3 class="text-base font-semibold text-gray-900">Students</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                <a href="{{ route('finance.students.show', $student) }}" class="block px-4 py-4 hover:bg-gray-50 sm:px-5">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-900">{{ $student->full_name }}</p>
                                            <p class="mt-1 break-words text-sm text-gray-500">{{ $student->student_id }} / {{ $student->email }}</p>
                                            <p class="mt-1 break-words text-xs text-gray-500">{{ $student->phone ?? 'No phone' }} / {{ $student->department->name ?? 'No department' }}</p>
                                        </div>
                                        @if($selectedStudent?->id === $student->id)
                                            <span class="self-start rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">Selected</span>
                                        @endif
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-gray-500 sm:px-5">No students match this search.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
                @else
                <section class="min-w-0 space-y-6">

                    <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                            <h3 class="text-base font-semibold text-gray-900">Recent Finance Records</h3>
                        </div>
                        @if(!request()->has('applied'))
                            <div class="px-4 py-8 text-center text-sm text-gray-500 sm:px-5">Apply a filter to view finance records.</div>
                        @else
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
                                @endphp
                                <article class="space-y-4 px-4 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-semibold text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $transaction->transaction_date->format('Y-m-d') }} / {{ ucfirst($transaction->type) }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ money($transaction->amount, $transaction->currency) }}</p>
                                            <p class="text-xs text-gray-500">{{ $transaction->currency }}</p>
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-medium text-gray-900">{{ $transaction->student->full_name ?? '-' }}</p>
                                        @if($transaction->reference)
                                            <p class="mt-1 break-words text-xs text-gray-500">{{ $transaction->reference }}</p>
                                        @endif
                                        @if($transaction->invoice)
                                            <p class="mt-1 break-words text-xs text-blue-700">Applied to {{ $transaction->invoice->documentNumber() }}</p>
                                        @endif
                                        @if($transaction->originalTransaction)
                                            <p class="mt-1 break-words text-xs text-amber-700">Reversal for {{ $transaction->originalTransaction->documentNumber() }}</p>
                                        @endif
                                    </div>

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
                                            <p class="mt-1 font-semibold text-gray-900">{{ $transaction->balance_after !== null ? money($transaction->balance_after, $transaction->currency).' '.$transaction->currency : '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium uppercase text-gray-500">Approval</p>
                                            <p class="mt-1 break-words text-gray-700">{{ $transaction->approver->name ?? '-' }}</p>
                                        </div>
                                    </div>

                                    @if($transaction->voided_at)
                                        <p class="text-xs text-red-700">Voided {{ $transaction->voided_at->format('Y-m-d H:i') }}</p>
                                    @endif

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
                                                    <input type="text" name="notes" placeholder="Reason" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
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
                            <table class="w-full min-w-0 table-fixed divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="w-[32%] px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Record</th>
                                        <th class="w-[20%] px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                        <th class="w-[18%] px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Amount</th>
                                        <th class="w-[18%] px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                        <th class="w-[12%] px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
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
                                        @endphp
                                        <tr>
                                            <td class="min-w-0 px-5 py-3 text-sm text-gray-600">
                                                <div class="truncate font-medium text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</div>
                                                <div class="text-xs text-gray-500">{{ $transaction->transaction_date->format('Y-m-d') }} / {{ ucfirst($transaction->type) }}</div>
                                                @if($transaction->invoice)
                                                    <div class="truncate text-xs text-blue-700">Applied to {{ $transaction->invoice->documentNumber() }}</div>
                                                @endif
                                                @if($transaction->originalTransaction)
                                                    <div class="truncate text-xs text-amber-700">Reversal for {{ $transaction->originalTransaction->documentNumber() }}</div>
                                                @endif
                                            </td>
                                            <td class="min-w-0 max-w-[10rem] truncate px-5 py-3 text-sm font-medium text-gray-900">{{ $transaction->student->full_name ?? '-' }}</td>
                                            <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ money($transaction->amount, $transaction->currency) }} {{ $transaction->currency }}</td>
                                            <td class="px-5 py-3">
                                                <div class="flex flex-col items-start gap-1">
                                                    <span class="inline-flex whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ ucfirst($transaction->status) }}</span>
                                                    <span class="inline-flex whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold {{ $paymentClass }}">{{ ucfirst($transaction->payment_status) }}</span>
                                                </div>
                                                @if($transaction->voided_at)
                                                    <div class="mt-1 text-xs text-red-700">Voided {{ $transaction->voided_at->format('Y-m-d') }}</div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-right">
                                                <div class="flex flex-col items-end gap-1">
                                                    @if($canApproveFinance && $transaction->status === 'pending' && $transaction->posting_status === 'pending')
                                                        <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" data-submit-once>
                                                            @csrf
                                                            <button type="submit" class="whitespace-nowrap rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                        </form>
                                                    @endif
                                                    @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                                        <a href="{{ route('finance.students.show', $transaction->student_id) }}" class="text-xs font-semibold text-red-600 hover:text-red-800">Void&hellip;</a>
                                                    @endif
                                                    @if(! $canApproveFinance && ! $canVoidFinance)
                                                        <a href="{{ route('finance.students.show', $transaction->student_id) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View</a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">No finance records match the current filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($transactions->hasPages())
                            <div class="border-t border-gray-200 px-4 py-4 sm:px-5">{{ $transactions->links() }}</div>
                        @endif
                        @endif
                    </div>
                </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
