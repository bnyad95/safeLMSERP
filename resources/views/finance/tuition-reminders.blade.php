<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-semibold text-gray-900">Tuition Reminder</h2>
                <p class="mt-1 text-sm text-gray-600">Filter unpaid tuition charges, select students, and send payment notifications.</p>
            </div>
            <a href="{{ route('finance') }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">
                Back to Finance
            </a>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <form method="GET" action="{{ route('finance.tuition-reminders.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="min-w-0 sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Search Student</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Name, email, ID, phone" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                        <select name="payment_status" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All unpaid</option>
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
                        <label class="block text-sm font-medium text-gray-700">Due From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Due To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-2">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Apply Filter</button>
                        <a href="{{ route('finance.tuition-reminders.index') }}" class="inline-flex w-full justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:w-auto">Reset</a>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('finance.tuition-reminders.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="return_to" value="tuition-reminders">
                @foreach(['q', 'payment_status', 'currency', 'academic_year', 'date_from', 'date_to', 'college_id', 'department_id'] as $filterField)
                    @if(filled($filters[$filterField] ?? null))
                        <input type="hidden" name="{{ $filterField }}" value="{{ $filters[$filterField] }}">
                    @endif
                @endforeach

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900">Message</h3>
                            <p class="mt-1 text-sm text-gray-500">This message will be sent only to students with unpaid tuition charges.</p>
                            <textarea
                                name="message"
                                rows="3"
                                placeholder="Optional message for students..."
                                class="mt-3 block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >{{ old('message') }}</textarea>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                            <button
                                type="submit"
                                name="scope"
                                value="selected_students"
                                @disabled($reminderRows->isEmpty())
                                class="inline-flex w-full justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-400 sm:w-auto"
                            >
                                Send to Checked Students
                            </button>
                            <button
                                type="submit"
                                name="scope"
                                value="filtered"
                                @disabled($reminderRows->isEmpty())
                                class="inline-flex w-full justify-center rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                            >
                                Send to Filtered Students
                            </button>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-4 sm:px-5">
                        <h3 class="text-base font-semibold text-gray-900">Students With Unpaid Tuition</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($reminderRows as $row)
                            @php
                                $student = $row['student'];
                                $oldestDueDate = $row['oldestDueDate'] ? \Illuminate\Support\Carbon::parse($row['oldestDueDate'])->format('Y-m-d') : 'No due date';
                            @endphp
                            <label class="flex min-w-0 flex-col gap-3 px-4 py-4 hover:bg-gray-50 sm:flex-row sm:items-start sm:px-5">
                                <input
                                    type="checkbox"
                                    name="student_ids[]"
                                    value="{{ $student->id }}"
                                    @checked(in_array((string) $student->id, old('student_ids', []), true))
                                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                                <span class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-gray-900">{{ $student->full_name }}</span>
                                        <span class="mt-1 block break-words text-sm text-gray-500">{{ $student->student_id }} / {{ $student->email }}</span>
                                        <span class="mt-1 block break-words text-xs text-gray-500">{{ $student->department->name ?? 'No department' }} / {{ $student->phone ?? 'No phone' }}</span>
                                    </span>
                                    <span class="text-left sm:text-right">
                                        <span class="block text-sm font-semibold text-gray-900">{{ $row['balanceText'] }}</span>
                                        <span class="mt-1 block text-xs text-gray-500">Oldest due: {{ $oldestDueDate }}</span>
                                    </span>
                                </span>
                            </label>
                        @empty
                            <div class="px-4 py-8 text-center text-sm text-gray-500 sm:px-5">No unpaid tuition charges match these filters.</div>
                        @endforelse
                    </div>
                    @if($reminderPaginator->hasPages())
                        <div class="border-t border-gray-100 px-4 py-4 sm:px-5">
                            {{ $reminderPaginator->links() }}
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
