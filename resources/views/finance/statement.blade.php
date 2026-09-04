<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Finance Statement') }} - {{ $student->full_name }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @endif

        <style>
            @page {
                size: A4;
                margin: 15mm;
            }
            .a4-width {
                width: 210mm;
            }
            .a4-page {
                width: 210mm;
                min-height: 297mm;
            }
            @media print {
                .print-actions { display: none !important; }
                body { background: #fff !important; }
                .a4-page {
                    width: auto;
                    min-height: 0;
                    margin: 0 !important;
                    box-shadow: none !important;
                }
            }
        </style>
    </head>
    <body class="bg-gray-200 font-sans text-gray-900">
        <main class="px-4 py-8">
            @unless(request()->boolean('embed'))
                <div class="a4-width print-actions mx-auto mb-5 flex flex-wrap justify-between gap-3">
                    <a href="{{ route('finance.students.show', $student) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('Back to Finance') }}
                    </a>
                    <button type="button" onclick="window.print()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        {{ __('Print Statement') }}
                    </button>
                </div>
            @endunless

            <section class="a4-page mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-row items-start justify-between gap-5 border-b border-gray-200 pb-6">
                    <div>
                        <p class="text-sm font-semibold uppercase text-gray-500">{{ __('Student Finance Statement') }}</p>
                        <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $student->full_name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ $student->student_id }} / {{ $student->email }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $student->department->name ?? __('No department') }} / {{ $student->university->name ?? __('No university') }}</p>
                    </div>
                    <div class="shrink-0 text-right text-sm text-gray-600">
                        <p>{{ __('Generated:') }} {{ now()->format('Y-m-d H:i') }}</p>
                        <p>{{ __('Status:') }} <span class="font-semibold text-gray-900">{{ ucfirst($paymentStatus) }}</span></p>
                        <p>{{ __('Remaining Due:') }} <span class="font-semibold text-gray-900">{{ $balances->isEmpty() ? '0 IQD' : $balances->map(fn ($row) => money($row['balance'], $row['currency']).' '.$row['currency'])->implode(' / ') }}</span></p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Document') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Debit') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Credit') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Remaining Due') }}</th>
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
                                        {{ $signedAmount > 0 ? money($signedAmount, $transaction->currency).' '.$transaction->currency : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">
                                        {{ $signedAmount < 0 ? money(abs($signedAmount), $transaction->currency).' '.$transaction->currency : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money($balanceAfter, $transaction->currency) }} {{ $transaction->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No finance records yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
