<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold text-gray-900">My Finance</h2>
                <p class="mt-1 text-sm text-gray-600">Your tuition charges, payments, balances, and payment status.</p>
            </div>
            <a href="{{ route('student-portal') }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="finance-workspace py-5 sm:py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($student)
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $student->full_name }}</h3>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $student->student_id }} / {{ $student->email }}</p>
                            <p class="mt-1 break-words text-sm text-gray-500">{{ $student->department->name ?? 'No department' }} / {{ $student->university->name ?? 'No university' }}</p>
                        </div>
                        <span class="self-start rounded-md {{ $paymentStatus === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">
                            {{ ucfirst($paymentStatus) }} account
                        </span>
                    </div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse($balances as $row)
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-gray-500">{{ $row['currency'] }} Balance</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format((float) $row['balance'], 2) }} {{ $row['currency'] }}</p>
                            <p class="mt-2 text-xs text-gray-500">Charges {{ number_format((float) $row['charges'], 2) }} / Credits {{ number_format((float) $row['credits'], 2) }}</p>
                        </div>
                    @empty
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <p class="text-sm font-medium text-gray-500">Balance</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900">0.00 IQD</p>
                            <p class="mt-2 text-xs text-gray-500">No finance records yet.</p>
                        </div>
                    @endforelse
                </section>

                <section class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                        <h3 class="text-base font-semibold text-gray-900">Finance Records</h3>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        @forelse($transactions as $transaction)
                            @php
                                $signedAmount = $transaction->signedAmount();
                                $statusClasses = [
                                    'paid' => 'bg-emerald-50 text-emerald-700',
                                    'approved' => 'bg-emerald-50 text-emerald-700',
                                    'partial' => 'bg-amber-50 text-amber-700',
                                    'overdue' => 'bg-red-50 text-red-700',
                                    'cancelled' => 'bg-gray-100 text-gray-600',
                                    'open' => 'bg-blue-50 text-blue-700',
                                    'pending' => 'bg-blue-50 text-blue-700',
                                ];
                            @endphp
                            <article class="space-y-3 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-semibold text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $transaction->transaction_date->format('Y-m-d') }} / {{ ucfirst($transaction->type) }}</p>
                                        @if($transaction->receipt_number && $transaction->posting_status === 'posted' && $transaction->status !== 'cancelled')
                                            <a href="{{ route('student.finance.receipt', $transaction) }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:text-blue-900">View receipt</a>
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
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs font-medium uppercase text-gray-500">Status</p>
                                        <span class="mt-1 inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $statusClasses[$transaction->payment_status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($transaction->payment_status) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase text-gray-500">Balance</p>
                                        <p class="mt-1 font-semibold text-gray-900">{{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}</p>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="px-4 py-8 text-center text-sm text-gray-500">No finance records yet.</div>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Document</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Debit</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Credit</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Payment</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($transactions as $transaction)
                                    @php
                                        $signedAmount = $transaction->signedAmount();
                                        $statusClasses = [
                                            'paid' => 'bg-emerald-50 text-emerald-700',
                                            'partial' => 'bg-amber-50 text-amber-700',
                                            'overdue' => 'bg-red-50 text-red-700',
                                            'cancelled' => 'bg-gray-100 text-gray-600',
                                            'open' => 'bg-blue-50 text-blue-700',
                                        ];
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                        <td class="px-5 py-3 text-sm">
                                            <div class="font-medium text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</div>
                                            @if($transaction->receipt_number && $transaction->posting_status === 'posted' && $transaction->status !== 'cancelled')
                                                <a href="{{ route('student.finance.receipt', $transaction) }}" target="_blank" class="text-xs font-semibold text-blue-700 hover:text-blue-900">View receipt</a>
                                            @endif
                                            @if($transaction->reference)
                                                <div class="text-xs text-gray-500">{{ $transaction->reference }}</div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ ucfirst($transaction->type) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-900">{{ $signedAmount > 0 ? number_format($signedAmount, 2).' '.$transaction->currency : '-' }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-900">{{ $signedAmount < 0 ? number_format(abs($signedAmount), 2).' '.$transaction->currency : '-' }}</td>
                                        <td class="px-5 py-3">
                                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $statusClasses[$transaction->payment_status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($transaction->payment_status) }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900">{{ $transaction->balance_after !== null ? number_format((float) $transaction->balance_after, 2).' '.$transaction->currency : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No finance records yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(method_exists($transactions, 'links'))
                        <div class="border-t border-gray-100 px-4 py-4">{{ $transactions->links() }}</div>
                    @endif
                </section>
            @else
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center">
                    <h3 class="text-base font-semibold text-gray-900">No student finance profile found</h3>
                    <p class="mt-2 text-sm text-gray-500">Your account email is not connected to a student record yet.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
