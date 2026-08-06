<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Accounting &amp; Finance</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">Finance Approvals</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Review pending finance records before they affect student balances.</p>
            </div>
            <a href="{{ route('finance.dashboard') }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-3 break-words text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </section>
                @endforeach
            </div>

            <form method="GET" action="{{ route('finance.approvals.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-5">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,2fr)_repeat(4,minmax(9rem,1fr))_auto] xl:items-end">
                    <div class="min-w-0">
                        <label for="approval-q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input id="approval-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Student, ID, email, receipt" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="approval-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Record type</label>
                        <select id="approval-type" name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All types</option>
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="approval-currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                        <select id="approval-currency" name="currency" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All currencies</option>
                            @foreach(['IQD', 'USD'] as $currency)
                                <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="approval-eligibility" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Approval access</label>
                        <select id="approval-eligibility" name="eligibility" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All pending</option>
                            <option value="actionable" @selected($filters['eligibility'] === 'actionable')>Ready for me</option>
                            <option value="mine" @selected($filters['eligibility'] === 'mine')>Recorded by me</option>
                        </select>
                    </div>
                    <div>
                        <label for="approval-sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Order</label>
                        <select id="approval-sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                            <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                        </select>
                    </div>
                    <div class="flex gap-2 sm:col-span-2 xl:col-span-1">
                        <button type="submit" class="flex-1 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">Apply</button>
                        <a href="{{ route('finance.approvals.index') }}" class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    </div>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Accounts Waiting for Approval</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ number_format($transactions->total()) }} matching finance record(s)</p>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A user cannot approve a record they entered.</p>
                </div>

                <div class="divide-y divide-gray-100 md:hidden dark:divide-gray-800">
                    @forelse($transactions as $transaction)
                        @php($canApproveRecord = auth()->user()->hasRole('super_administrator') || $transaction->recorded_by !== auth()->id())
                        <article class="space-y-4 px-4 py-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('finance.students.show', $transaction->student_id) }}" class="truncate font-semibold text-blue-700 hover:underline dark:text-blue-300">{{ $transaction->student?->full_name ?? 'Unknown student' }}</a>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $transaction->student?->student_id }} / {{ $transaction->student?->department?->name ?? 'No department' }}</p>
                                </div>
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-200">Pending</span>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div><dt class="text-xs text-gray-500 dark:text-gray-400">Type</dt><dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($transaction->type) }}</dd></div>
                                <div><dt class="text-xs text-gray-500 dark:text-gray-400">Amount</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</dd></div>
                                <div><dt class="text-xs text-gray-500 dark:text-gray-400">Recorded by</dt><dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $transaction->recorder?->name ?? 'Unknown' }}</dd></div>
                                <div><dt class="text-xs text-gray-500 dark:text-gray-400">Recorded</dt><dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $transaction->created_at->format('Y-m-d H:i') }}</dd></div>
                            </dl>
                            @if($canApproveRecord)
                                <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" data-submit-once>
                                    @csrf
                                    <input type="hidden" name="return_to" value="approvals">
                                    @foreach($filters as $name => $value)
                                        @if(filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                                    @endforeach
                                    <button type="submit" class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve record</button>
                                </form>
                            @else
                                <p class="rounded-md bg-gray-100 px-3 py-2 text-center text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Different approver required</p>
                            @endif
                        </article>
                    @empty
                        <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No pending finance records match these filters.</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950/50">
                            <tr class="text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-3">Student</th>
                                <th class="px-5 py-3">Record</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3">Entered by</th>
                                <th class="px-5 py-3">Submitted</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($transactions as $transaction)
                                @php($canApproveRecord = auth()->user()->hasRole('super_administrator') || $transaction->recorded_by !== auth()->id())
                                <tr>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('finance.students.show', $transaction->student_id) }}" class="font-semibold text-blue-700 hover:underline dark:text-blue-300">{{ $transaction->student?->full_name ?? 'Unknown student' }}</a>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $transaction->student?->student_id }} / {{ $transaction->student?->department?->college?->name ?? 'No college' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($transaction->type) }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $transaction->receipt_number ?? $transaction->reference ?? 'No reference' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ $transaction->recorder?->name ?? 'Unknown' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-4 text-right">
                                        @if($canApproveRecord)
                                            <form method="POST" action="{{ route('finance.transactions.approve', $transaction) }}" data-submit-once>
                                                @csrf
                                                <input type="hidden" name="return_to" value="approvals">
                                                @foreach($filters as $name => $value)
                                                    @if(filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                                                @endforeach
                                                <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                            </form>
                                        @else
                                            <span class="inline-flex rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Different approver required</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No pending finance records match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($transactions->hasPages())
                    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $transactions->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
