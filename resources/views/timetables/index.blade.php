<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $isStudentTimetable ? 'My Timetable' : 'Timetable' }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $isStudentTimetable ? 'Your weekly schedule for registered course groups.' : 'Schedule course modules into rooms and time slots with conflict checks.' }}</p>
            </div>
            @unless($isStudentTimetable)
                <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Dashboard</a>
            @endunless
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($isStudentTimetable)
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">My Timetable</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $student?->full_name ?? 'No linked student profile' }} / {{ $student?->student_id ?? 'No student ID' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if(!$student)
                        <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            No student profile is linked to your account email yet.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Day</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Time</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Course</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Group</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Room</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Teacher</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Type</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse($studentTimetableEntries as $entry)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $entry->day_of_week }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                <div class="font-medium">{{ $entry->course->code ?? 'Course' }}</div>
                                                <div class="text-gray-500 dark:text-gray-400">{{ $entry->course->name ?? 'No course' }}</div>
                                            </td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                {{ $entry->courseSection->grade_level ?? 'No stage' }} / Group {{ $entry->courseSection->section_code ?? '-' }}
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->courseSection->semester->name ?? 'No semester' }} {{ $entry->courseSection->semester->academic_year ?? '' }}</div>
                                            </td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->teacher->full_name ?? 'No teacher' }}</td>
                                            <td class="px-5 py-3">
                                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ ucfirst($entry->type) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No timetable entries are available for your registered course groups yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @elseif($isTeacherTimetable)
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">My Teaching Timetable</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $teacher?->full_name ?? 'No linked teacher profile' }} - assigned classes only</p>
                    </div>

                    @if(!$teacher)
                        <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No teacher profile is linked to your account email.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Day</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Time</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Course</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Class</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Room</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Type</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse($teacherTimetableEntries as $entry)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $entry->day_of_week }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</td>
                                            <td class="px-5 py-3 text-sm"><p class="font-semibold text-gray-900 dark:text-gray-100">{{ $entry->course->code ?? 'Course' }}</p><p class="text-gray-500 dark:text-gray-400">{{ $entry->course->name ?? 'No course' }}</p></td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->courseSection->grade_level ?? 'No stage' }} / Group {{ $entry->courseSection->section_code ?? '-' }}</td>
                                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }}</td>
                                            <td class="px-5 py-3 text-sm capitalize text-gray-600 dark:text-gray-400">{{ $entry->type }}</td>
                                            <td class="px-5 py-3 text-sm capitalize {{ $entry->status === 'scheduled' ? 'text-green-700 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $entry->status }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No timetable entries are assigned to you yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @else
            @php
                $hasAdvancedTimetableFilters = (bool) ($filters['department_id'] || $filters['grade_level'] !== '' || $filters['teacher_id'] || $filters['classroom_id'] || $filters['day_of_week'] !== '' || $filters['type'] !== '');
            @endphp
            <form method="GET" action="{{ route('timetables.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" x-data="{ showMore: {{ $hasAdvancedTimetableFilters ? 'true' : 'false' }} }">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All semesters</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group</label>
                        <select name="group" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All groups</option>
                            @foreach($groupOptions as $group)
                                <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All statuses</option>
                            @foreach(['scheduled' => 'Scheduled', 'cancelled' => 'Cancelled'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            Filter
                        </button>
                        <a href="{{ route('timetables.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                            Clear
                        </a>
                    </div>
                </div>

                <button type="button" x-on:click="showMore = ! showMore" class="mt-4 flex items-center gap-1 text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                    <span x-text="showMore ? 'Fewer filters' : 'More filters'"></span>
                    <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': showMore }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="showMore" x-cloak class="mt-4 grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-2 xl:grid-cols-5 dark:border-gray-800">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                        <select name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All stages</option>
                            @foreach($gradeOptions as $grade)
                                <option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teacher</label>
                        <select name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All teachers</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Room</label>
                        <select name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All rooms</option>
                            @foreach($classrooms as $room)
                                <option value="{{ $room->id }}" @selected($filters['classroom_id'] === $room->id)>{{ $room->name }} / {{ ucfirst($room->status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Day</label>
                        <select name="day_of_week" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All days</option>
                            @foreach($days as $day)
                                <option value="{{ $day }}" @selected($filters['day_of_week'] === $day)>{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <select name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All types</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <div class="space-y-6">
                @if($canManageTimetable)
                    <section class="space-y-6" x-data="{ tab: 'schedule' }">
                        <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" x-on:click="tab = 'schedule'" x-bind:class="tab === 'schedule' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Schedule</button>
                                <button type="button" x-on:click="tab = 'rooms'" x-bind:class="tab === 'rooms' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Rooms</button>
                            </div>
                        </div>

                        <div x-show="tab === 'schedule'" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Schedule Module</h3>
                            <form method="POST" action="{{ route('timetables.store') }}" class="mt-5 grid gap-4">
                                @csrf

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                            <option value="">Select department</option>
                                            @foreach($departments as $department)
                                                <option value="{{ $department->id }}" @selected(old('department_id', $filters['department_id']) == $department->id)>{{ $department->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                                        <select name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                            <option value="">Select stage</option>
                                            @foreach($gradeOptions as $grade)
                                                <option value="{{ $grade }}" @selected(old('grade_level', $filters['grade_level']) === $grade)>{{ $grade }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Course Module</label>
                                    <select name="course_section_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        <option value="">Select module</option>
                                        @foreach($sections as $section)
                                            <option value="{{ $section->id }}" @selected(old('course_section_id') == $section->id)>
                                                {{ $section->course->department->name ?? 'Department' }} / {{ $section->grade_level ?? 'No stage' }} / {{ $section->semester->name ?? 'Semester' }} / Group {{ $section->section_code }} / {{ $section->course->code ?? 'Course' }} / {{ $section->teacher->full_name ?? 'No teacher' }} / {{ $section->enrolled_count }} students
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Day</label>
                                    <select name="day_of_week" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        @foreach($days as $day)
                                            <option value="{{ $day }}" @selected(old('day_of_week') === $day)>{{ $day }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Room</label>
                                    <select name="classroom_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        <option value="">Select room</option>
                                        @foreach($availableClassrooms as $room)
                                            <option value="{{ $room->id }}" @selected(old('classroom_id') == $room->id)>
                                                {{ $room->name }} / {{ ucfirst($room->type) }} / {{ $room->capacity }} seats
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Start</label>
                                    <input type="time" name="start_time" value="{{ old('start_time') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">End</label>
                                    <input type="time" name="end_time" value="{{ old('end_time') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Class Type</label>
                                    <select name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        @foreach($types as $type)
                                            <option value="{{ $type }}" @selected(old('type', 'lecture') === $type)>{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        <option value="scheduled" @selected(old('status', 'scheduled') === 'scheduled')>Scheduled</option>
                                        <option value="cancelled" @selected(old('status') === 'cancelled')>Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
                                    Add Timetable Entry
                                </button>
                            </div>
                        </form>
                        </div>

                        <div x-show="tab === 'rooms'" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Add Room</h3>
                        <form method="POST" action="{{ route('classrooms.store') }}" class="mt-5 grid gap-4">
                            @csrf
                            @if($universities->count() > 1 || !auth()->user()->university_id)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label>
                                    <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        <option value="">Select university</option>
                                        @foreach($universities as $university)
                                            <option value="{{ $university->id }}" @selected(old('university_id', auth()->user()->university_id) == $university->id)>{{ $university->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Room Name</label>
                                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Lab 201" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Building</label>
                                    <input type="text" name="building" value="{{ old('building') }}" placeholder="Science Block" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Capacity</label>
                                    <input type="number" name="capacity" value="{{ old('capacity', 40) }}" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                    <select name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        @foreach(['classroom', 'lab', 'hall', 'online'] as $roomType)
                                            <option value="{{ $roomType }}" @selected(old('type', 'classroom') === $roomType)>{{ ucfirst($roomType) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                        @foreach(['available', 'maintenance', 'inactive'] as $roomStatus)
                                            <option value="{{ $roomStatus }}" @selected(old('status', 'available') === $roomStatus)>{{ ucfirst($roomStatus) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                                Save Room
                            </button>
                        </form>
                        </div>

                    </section>
                @endif

                <section class="space-y-4" x-data="{ view: 'grid' }">
                    <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="view = 'grid'" x-bind:class="view === 'grid' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Weekly Grid</button>
                            <button type="button" x-on:click="view = 'classified'" x-bind:class="view === 'classified' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Classified Timetable</button>
                        </div>
                    </div>

                    <div x-show="view === 'grid'" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Weekly Grid</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-fixed divide-y divide-gray-100 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="w-28 px-3 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Time</th>
                                        @foreach($days as $day)
                                            <th class="min-w-40 px-3 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $day }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse($weeklyGrid as $slot)
                                        <tr class="align-top">
                                            <td class="px-3 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $slot['slot'] }}</td>
                                            @foreach($days as $day)
                                                <td class="space-y-2 px-3 py-3">
                                                    @foreach(($slot['days'][$day] ?? collect()) as $entry)
                                                        <div class="rounded-md border border-gray-200 bg-white p-2 text-xs shadow-sm dark:border-gray-700 dark:bg-gray-950">
                                                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $entry->course->code ?? 'Course' }} / Group {{ $entry->courseSection?->section_code ?? '-' }}</div>
                                                            <div class="mt-1 text-gray-500 dark:text-gray-400">{{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }}</div>
                                                            <div class="mt-1 text-gray-500 dark:text-gray-400">{{ $entry->teacher->full_name ?? 'No teacher' }}</div>
                                                            <span class="mt-2 inline-flex rounded-md {{ $entry->status === 'scheduled' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }} px-2 py-1 font-semibold">{{ ucfirst($entry->status) }}</span>
                                                        </div>
                                                    @endforeach
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($days) + 1 }}" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No timetable entries match the current filters.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="view === 'classified'" x-cloak class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Classified Timetable</h3>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($classifiedTimetables as $departmentGroup)
                                <details class="group">
                                    <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $departmentGroup['department'] }}</span>
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $departmentGroup['count'] }} modules</span>
                                    </summary>

                                    <div class="space-y-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                                        @foreach($departmentGroup['grades'] as $gradeGroup)
                                            <details class="rounded-md border border-gray-200 dark:border-gray-800">
                                                <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
                                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $gradeGroup['grade'] }}</span>
                                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $gradeGroup['count'] }} modules</span>
                                                </summary>

                                                <div class="space-y-3 border-t border-gray-100 p-4 dark:border-gray-800">
                                                    @foreach($gradeGroup['semesters'] as $semesterGroup)
                                                        <details class="rounded-md bg-gray-50 dark:bg-gray-800/50">
                                                            <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3">
                                                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $semesterGroup['semester'] }}</span>
                                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $semesterGroup['count'] }} modules</span>
                                                            </summary>

                                                            <div class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
                                                                @foreach($semesterGroup['groups'] as $group)
                                                                    <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                                                        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                                                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Group {{ $group['group'] }}</h4>
                                                                        </div>
                                                                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                                                            @foreach($days as $day)
                                                                                @foreach(($group['days'][$day] ?? collect()) as $entry)
                                                                                    <div class="flex flex-col gap-3 px-4 py-3 md:flex-row md:items-start md:justify-between">
                                                                                        <div>
                                                                                            <div class="flex flex-wrap items-center gap-2">
                                                                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $day }} {{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</p>
                                                                                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ ucfirst($entry->type) }}</span>
                                                                                                <span class="rounded-md {{ $entry->status === 'scheduled' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }} px-2 py-1 text-xs font-semibold">{{ ucfirst($entry->status) }}</span>
                                                                                            </div>
                                                                                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $entry->course->code ?? 'Course' }} / {{ $entry->course->name ?? 'No course' }}</p>
                                                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $entry->teacher->full_name ?? 'No teacher' }}</p>
                                                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                                                                {{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }} / {{ $entry->classroom->building ?? 'No building' }} / {{ $entry->courseSection?->activeEnrollments->count() ?? 0 }} students
                                                                                            </p>
                                                                                        </div>
                                                                                        @if($canManageTimetable)
                                                                                            <div class="flex flex-wrap gap-2">
                                                                                                <details class="rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                                                                                    <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Edit</summary>
                                                                                                    <form method="POST" action="{{ route('timetables.update', $entry) }}" class="grid w-full min-w-72 gap-3 border-t border-gray-100 p-3 md:w-96 dark:border-gray-800">
                                                                                                        @csrf
                                                                                                        @method('PATCH')
                                                                                                        <div class="grid grid-cols-2 gap-2">
                                                                                                            <select name="department_id" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                @foreach($departments as $department)
                                                                                                                    <option value="{{ $department->id }}" @selected(($entry->courseSection?->course?->department_id ?: $filters['department_id']) === $department->id)>{{ $department->name }}</option>
                                                                                                                @endforeach
                                                                                                            </select>
                                                                                                            <select name="grade_level" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                @foreach($gradeOptions as $grade)
                                                                                                                    <option value="{{ $grade }}" @selected(($entry->courseSection?->grade_level ?: $filters['grade_level']) === $grade)>{{ $grade }}</option>
                                                                                                                @endforeach
                                                                                                            </select>
                                                                                                        </div>
                                                                                                        <select name="course_section_id" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                            @if(!$entry->courseSection)
                                                                                                                <option value="">Select module</option>
                                                                                                            @endif
                                                                                                            @foreach($sections as $section)
                                                                                                                <option value="{{ $section->id }}" @selected($entry->course_section_id === $section->id)>{{ $section->course->code ?? 'Course' }} / Group {{ $section->section_code }} / {{ $section->teacher->full_name ?? 'No teacher' }}</option>
                                                                                                            @endforeach
                                                                                                            @if($entry->courseSection && !$sections->contains('id', $entry->courseSection->id))
                                                                                                                <option value="{{ $entry->courseSection->id }}" selected>{{ $entry->course->code ?? 'Course' }} / Group {{ $entry->courseSection->section_code }}</option>
                                                                                                            @endif
                                                                                                        </select>
                                                                                                        <div class="grid grid-cols-2 gap-2">
                                                                                                            <select name="day_of_week" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                @foreach($days as $dayOption)
                                                                                                                    <option value="{{ $dayOption }}" @selected($entry->day_of_week === $dayOption)>{{ $dayOption }}</option>
                                                                                                                @endforeach
                                                                                                            </select>
                                                                                                            <select name="classroom_id" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                @foreach($availableClassrooms as $room)
                                                                                                                    <option value="{{ $room->id }}" @selected($entry->classroom_id === $room->id)>{{ $room->name }} / {{ $room->capacity }} seats</option>
                                                                                                                @endforeach
                                                                                                                @if($entry->classroom && !$availableClassrooms->contains('id', $entry->classroom->id))
                                                                                                                    <option value="{{ $entry->classroom->id }}" selected>{{ $entry->classroom->name }} / {{ ucfirst($entry->classroom->status) }}</option>
                                                                                                                @endif
                                                                                                            </select>
                                                                                                        </div>
                                                                                                        <div class="grid grid-cols-2 gap-2">
                                                                                                            <input type="time" name="start_time" value="{{ substr((string) $entry->start_time, 0, 5) }}" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                                                                                            <input type="time" name="end_time" value="{{ substr((string) $entry->end_time, 0, 5) }}" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                                                                                        </div>
                                                                                                        <div class="grid grid-cols-2 gap-2">
                                                                                                            <select name="type" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                @foreach($types as $type)
                                                                                                                    <option value="{{ $type }}" @selected($entry->type === $type)>{{ ucfirst($type) }}</option>
                                                                                                                @endforeach
                                                                                                            </select>
                                                                                                            <select name="status" class="rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                                                                                                                <option value="scheduled" @selected($entry->status === 'scheduled')>Scheduled</option>
                                                                                                                <option value="cancelled" @selected($entry->status === 'cancelled')>Cancelled</option>
                                                                                                            </select>
                                                                                                        </div>
                                                                                                        <button class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Save</button>
                                                                                                    </form>
                                                                                                </details>
                                                                                                <form method="POST" action="{{ route('timetables.destroy', $entry) }}">
                                                                                                    @csrf
                                                                                                    @method('DELETE')
                                                                                                    <button type="submit" class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20" onclick="return confirm('Remove this timetable entry?')">
                                                                                                        Remove
                                                                                                    </button>
                                                                                                </form>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endforeach
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </details>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No modules match this classification.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
