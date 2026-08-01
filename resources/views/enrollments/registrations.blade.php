<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Enrollments</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Student registrations, waitlists, retakes, and enrollment status.</p>
            </div>
            <a href="{{ route('module-offerings.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Module Offerings
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['label' => 'Matching Records', 'value' => $stats['total'], 'tone' => 'bg-white dark:bg-gray-900'],
                    ['label' => 'Enrolled', 'value' => $stats['enrolled'], 'tone' => 'bg-emerald-50 dark:bg-emerald-950/30'],
                    ['label' => 'Waitlisted', 'value' => $stats['waitlisted'], 'tone' => 'bg-amber-50 dark:bg-amber-950/30'],
                    ['label' => 'Retakes', 'value' => $stats['retakes'], 'tone' => 'bg-blue-50 dark:bg-blue-950/30'],
                ] as $stat)
                    <div class="{{ $stat['tone'] }} rounded-lg border border-gray-200 p-5 shadow-sm dark:border-gray-800">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stat['value']) }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('enrollments.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="md:col-span-2 xl:col-span-2">
                        <label for="enrollment-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input id="enrollment-search" name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="Student, ID, email, or module">
                    </div>
                    <div>
                        <label for="enrollment-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select id="enrollment-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="enrollment-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select id="enrollment-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" data-college-id="{{ $department->college_id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="enrollment-stage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                        <select id="enrollment-stage" name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All stages</option>
                            @foreach($gradeOptions as $grade)
                                <option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="enrollment-semester" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select id="enrollment-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All semesters</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="enrollment-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="enrollment-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All statuses</option>
                            @foreach(['enrolled' => 'Enrolled', 'waitlisted' => 'Waitlisted', 'dropped' => 'Removed'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('enrollments.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Student Registrations</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open a module to enroll students or manage its roster and waitlist.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Student</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Module</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Academic Placement</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($enrollments as $enrollment)
                                @php
                                    $section = $enrollment->courseSection;
                                    $course = $section?->course;
                                    $statusClasses = match($enrollment->status) {
                                        'enrolled' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
                                        'waitlisted' => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-5 py-4 text-sm">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $enrollment->student?->full_name ?? 'Deleted student' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $enrollment->student?->student_id ?? 'No ID' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $course?->code ?? 'Archived' }} - {{ $course?->name ?? 'Module unavailable' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Group {{ $section?->section_code ?? '-' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        <p>{{ $course?->department?->college?->name ?? 'No college' }} / {{ $course?->department?->name ?? 'No department' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section?->stage?->name ?? ($section?->grade_level ?: 'No stage') }} / {{ $section?->semester?->name ?? 'No semester' }} {{ $section?->semester?->academic_year }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $enrollment->enrolled_at?->format('M j, Y') ?? '-' }}</td>
                                    <td class="px-5 py-4 text-sm">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $enrollment->status === 'dropped' ? 'Removed' : ucfirst($enrollment->status) }}</span>
                                            @if($enrollment->is_retake)
                                                <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-200">Retake</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right text-sm">
                                        @if($section)
                                            <a href="{{ route('course-sections.show', $section) }}" class="font-semibold text-blue-700 hover:text-blue-900 dark:text-indigo-300 dark:hover:text-indigo-200">Open roster</a>
                                        @else
                                            <span class="text-gray-400">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No student registrations match these filters. Open a module offering to register students.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($enrollments->hasPages())
                    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $enrollments->links() }}</div>
                @endif
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const college = document.getElementById('enrollment-college');
            const department = document.getElementById('enrollment-department');
            if (!college || !department) return;

            const filterDepartments = () => {
                const selectedCollege = college.value;
                Array.from(department.options).forEach((option, index) => {
                    if (index === 0) return;
                    option.hidden = selectedCollege !== '' && option.dataset.collegeId !== selectedCollege;
                });
                if (department.selectedOptions[0]?.hidden) department.value = '';
            };

            college.addEventListener('change', filterDepartments);
            filterDepartments();
        });
    </script>
</x-app-layout>
