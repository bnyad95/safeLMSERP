<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Finance Statement - {{ $student->full_name }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @endif

        <style>
            @media print {
                .print-actions { display: none !important; }
                body { background: #fff !important; }
            }
        </style>
    </head>
    <body class="bg-gray-100 font-sans text-gray-900">
        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            @unless(request()->boolean('embed'))
                <div class="print-actions mb-5 flex flex-wrap justify-between gap-3">
                    <a href="{{ route('finance.students.show', $student) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Back to Finance
                    </a>
                    <button type="button" onclick="window.print()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Print Statement
                    </button>
                </div>
            @endunless

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 border-b border-gray-200 pb-6 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase text-gray-500">Student Finance Statement</p>
                        <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $student->full_name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ $student->student_id }} / {{ $student->email }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $student->department->name ?? 'No department' }} / {{ $student->university->name ?? 'No university' }}</p>
                    </div>
                    <div class="text-sm text-gray-600 md:text-right">
                        <p>Generated: {{ now()->format('Y-m-d H:i') }}</p>
                        <p>Status: <span class="font-semibold text-gray-900">{{ ucfirst($paymentStatus) }}</span></p>
                        <p>Balance: <span class="font-semibold text-gray-900">{{ $balances->isEmpty() ? '0.00 IQD' : $balances->map(fn ($row) => number_format((float) $row['balance'], 2).' '.$row['currency'])->implode(' / ') }}</span></p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @forelse($balances as $row)
                        <div class="rounded-md border border-gray-200 p-4">
                            <p class="text-sm font-medium text-gray-500">{{ $row['currency'] }} Balance</p>
                            <p class="mt-2 text-xl font-semibold text-gray-900">{{ number_format((float) $row['balance'], 2) }} {{ $row['currency'] }}</p>
                            <p class="mt-2 text-xs text-gray-500">Charges {{ number_format((float) $row['charges'], 2) }} / Credits {{ number_format((float) $row['credits'], 2) }}</p>
                        </div>
                    @empty
                        <div class="rounded-md border border-gray-200 p-4">
                            <p class="text-sm font-medium text-gray-500">Balance</p>
                            <p class="mt-2 text-xl font-semibold text-gray-900">0.00 IQD</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Document</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Debit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $runningBalance = 0;
                            @endphp
                            @forelse($transactions as $transaction)
                                @php
                                    $signedAmount = $transaction->signedAmount();
                                    $runningBalance += $signedAmount;
                                    $balanceAfter = $transaction->balance_after ?? $runningBalance;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-medium text-gray-900">{{ $transaction->documentNumber() ?? '-' }}</div>
                                        @if($transaction->reference)
                                            <div class="text-xs text-gray-500">{{ $transaction->reference }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($transaction->type) }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">
                                        {{ $signedAmount > 0 ? number_format($signedAmount, 2).' '.$transaction->currency : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">
                                        {{ $signedAmount < 0 ? number_format(abs($signedAmount), 2).' '.$transaction->currency : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ number_format((float) $balanceAfter, 2) }} {{ $transaction->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No finance records yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
