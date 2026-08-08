<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $teacher->staff_id }}</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $teacher->full_name }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Teacher profile, organization placement, and assigned workload.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('teachers.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Back</a>
                @if($abilities['update'])
                    <a href="{{ route('teachers.edit', $teacher) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">Edit Profile</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['label' => 'Open classes', 'value' => $workload['classes'], 'tone' => 'gray'],
                    ['label' => 'Active students', 'value' => $workload['students'], 'tone' => 'emerald'],
                    ['label' => 'Assessments', 'value' => $workload['assessments'], 'tone' => 'violet'],
                    ['label' => 'Schedule entries', 'value' => $workload['schedule_entries'], 'tone' => 'blue'],
                ] as $stat)
                    <div class="rounded-lg border p-4 shadow-sm
                        {{ $stat['tone'] === 'gray' ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' : '' }}
                        {{ $stat['tone'] === 'emerald' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20' : '' }}
                        {{ $stat['tone'] === 'violet' ? 'border-violet-200 bg-violet-50 dark:border-violet-800 dark:bg-violet-900/20' : '' }}
                        {{ $stat['tone'] === 'blue' ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20' : '' }}">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stat['value']) }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">Staff Information</h3>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        'Title' => $teacher->title ?: 'Not recorded',
                        'Status' => $teacher->status,
                        'Email' => $teacher->email,
                        'Login account' => $teacher->user ? 'Linked' : 'Not linked',
                        'University' => $teacher->university->name ?? 'Not assigned',
                        'College' => $teacher->department->college->name ?? 'Not assigned',
                        'Department' => $teacher->department->name ?? 'Not assigned',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Assigned Classes</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assignments are managed from Bologna Definition &rarr; Module Offerings.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Semester / Group</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Students</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($teacher->courseSections as $section)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-6 py-4 text-sm">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $section->course->name ?? 'Unavailable course' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section->course->code ?? '' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $section->semester->name ?? 'N/A' }}
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Group {{ $section->section_code }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ number_format($section->active_enrollments_count) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        @forelse($section->timetables as $entry)
                                            <p>{{ $entry->day_of_week }} {{ \Illuminate\Support\Carbon::parse($entry->start_time)->format('g:i A') }}</p>
                                        @empty
                                            <span class="text-gray-400">Not scheduled</span>
                                        @endforelse
                                    </td>
                                    <td class="px-6 py-4 text-sm capitalize text-gray-600 dark:text-gray-300">{{ $section->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No classes assigned.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if($abilities['archive'])
                <section class="flex flex-col gap-4 rounded-lg border border-red-200 bg-red-50 p-5 dark:border-red-800 dark:bg-red-900/20 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-red-900 dark:text-red-100">Archive teacher</h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-200">Planned and active classes must be reassigned or closed first. Teacher-only login access will be suspended.</p>
                    </div>
                    <form action="{{ route('teachers.destroy', $teacher) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" onclick="return confirm('Archive this teacher?')">Archive Teacher</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
