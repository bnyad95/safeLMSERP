<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Attendance History</h2>
                <p class="text-sm text-gray-600">{{ $course->name }}{{ $section ? ' - Group '.$section->section_code : '' }}</p>
            </div>
            <button type="button" onclick="window.print()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Print</button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5">
                @if($section)<input type="hidden" name="section_id" value="{{ $section->id }}">@endif
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-gray-500">From</span>
                    <input type="date" name="from" value="{{ $from }}" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-gray-500">To</span>
                    <input type="date" name="to" value="{{ $to }}" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-gray-500">Status</span>
                    <select name="status" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Attendance::getStatusOptions() as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-gray-500">Student</span>
                    <input type="search" name="student" value="{{ $studentSearch }}" placeholder="Name, email, or ID" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Filter</button>
                    <button type="submit" name="export" value="csv" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">CSV</button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($attendances as $record)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $record->date?->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-sm font-medium">{{ $record->student->full_name ?? '-' }}</td>
                                <td class="px-6 py-3">
                                    @php
                                        $colors = ['present'=>'bg-green-100 text-green-700','absent'=>'bg-red-100 text-red-700','late'=>'bg-yellow-100 text-yellow-700','excused'=>'bg-blue-100 text-blue-700'];
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $colors[$record->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $record->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No records in this date range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t px-4 py-3">{{ $attendances->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
