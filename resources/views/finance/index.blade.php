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

            <form method="GET" action="{{ route('finance') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
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
                    <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-2">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Apply</button>
                        <a href="{{ route('finance', $selectedStudent ? ['student_id' => $selectedStudent->id] : []) }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">Reset</a>
                    </div>
                </div>
            </form>

            <div class="grid min-w-0 gap-6 xl:grid-cols-[0.9fr_1.35fr]">
                <section class="min-w-0 space-y-6">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="text-base font-semibold text-gray-900">Student Search</h3>
                        <form method="GET" action="{{ route('finance') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <input
                                type="text"
                                name="q"
                                value="{{ $query }}"
                                placeholder="Name, email, ID, phone..."
                                class="min-w-0 flex-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">
                                Search
                            </button>
                        </form>
                    </div>

                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                            <h3 class="text-base font-semibold text-gray-900">Students</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @if(!($shouldLoadStudents ?? false))
                                <div class="px-4 py-8 text-center text-sm text-gray-500 sm:px-5">Use search or organization filters to load students.</div>
                            @else
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
                            @endif
                        </div>
                    </div>
                </section>

                <section class="min-w-0 space-y-6">
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 sm:p-8">
                        Open a student from the search results to enter invoices, payments, and tuition records.
                    </div>

                    <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                            <h3 class="text-base font-semibold text-gray-900">Recent Finance Records</h3>
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
                                @endphp
                                <article class="space-y-4 px-4 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="break-words text-sm font-semibold text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $transaction->transaction_date->format('Y-m-d') }} / {{ ucfirst($transaction->type) }}</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ number_format((float) $transaction->amount, 2) }}</p>
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
                                            <p class="mt-1 font-semibold text-gray-900">{{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs font-medium uppercase text-gray-500">Approval</p>
                                            <p class="mt-1 break-words text-gray-700">{{ $transaction->approver->name ?? '-' }}</p>
                                        </div>
                                    </div>

                                    @if($transaction->voided_at)
                                        <p class="text-xs text-red-700">Voided {{ $transaction->voided_at->format('Y-m-d H:i') }}</p>
                                    @endif

                                    @if(($canApproveFinance && $transaction->status === 'pending') || ($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id))
                                        <div class="flex flex-col gap-2">
                                            @if($canApproveFinance && $transaction->status === 'pending')
                                                <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}">
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
                            <table class="min-w-[920px] divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
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
                                        @endphp
                                        <tr>
                                            <td class="px-5 py-3 text-sm text-gray-600">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                            <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $transaction->student->full_name ?? '-' }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-600">
                                                <div class="font-medium text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</div>
                                                @if($transaction->invoice)
                                                    <div class="text-xs text-blue-700">Applied to {{ $transaction->invoice->documentNumber() }}</div>
                                                @endif
                                                @if($transaction->originalTransaction)
                                                    <div class="text-xs text-amber-700">Reversal for {{ $transaction->originalTransaction->documentNumber() }}</div>
                                                @endif
                                                @if($transaction->reference)
                                                    <div class="text-xs text-gray-500">{{ $transaction->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-sm text-gray-600">{{ ucfirst($transaction->type) }}</td>
                                            <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</td>
                                            <td class="px-5 py-3">
                                                <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $recordClass }}">{{ ucfirst($transaction->status) }}</span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $paymentClass }}">{{ ucfirst($transaction->payment_status) }}</span>
                                            </td>
                                            <td class="px-5 py-3 text-sm font-semibold text-gray-900">
                                                {{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}
                                            </td>
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
                                                    @if($canApproveFinance && $transaction->status === 'pending')
                                                        <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}">
                                                            @csrf
                                                            <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                                        </form>
                                                    @endif
                                                    @if($canVoidFinance && $transaction->status !== 'cancelled' && ! $transaction->original_transaction_id)
                                                        <form method="POST" action="{{ route('finance.transactions.void', $transaction) }}" class="flex justify-end gap-2" onsubmit="return confirm('Void this finance record and create a reversal entry?')">
                                                            @csrf
                                                            <input type="text" name="notes" placeholder="Reason" class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Void</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="px-5 py-8 text-center text-sm text-gray-500">No finance records match the current filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($transactions->hasPages())
                            <div class="border-t border-gray-200 px-4 py-4 sm:px-5">{{ $transactions->links() }}</div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
