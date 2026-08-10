<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Module Offerings</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Open catalog courses for an academic year, stage, semester, group, and teacher.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('course-records.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Course Catalog</a>
                @if($abilities['manage'])
                    <a href="{{ route('course-sections.archived') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Archived {{ $archivedCount ? '('.$archivedCount.')' : '' }}</a>
                    <a href="{{ route('course-sections.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">Add Module Offering</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-4">
                @foreach([
                    ['label' => 'Modules', 'value' => $stats['sections'], 'tone' => 'bg-white dark:bg-gray-900'],
                    ['label' => 'Active', 'value' => $stats['active'], 'tone' => 'bg-green-50 dark:bg-green-950/30'],
                    ['label' => 'Enrolled Students', 'value' => $stats['students'], 'tone' => 'bg-blue-50 dark:bg-blue-950/30'],
                    ['label' => 'Waitlisted', 'value' => $stats['waitlisted'], 'tone' => 'bg-amber-50 dark:bg-amber-950/30'],
                ] as $stat)
                    <div class="{{ $stat['tone'] }} rounded-lg border border-gray-200 p-5 shadow-sm dark:border-gray-700">
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $stat['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>

            @php
                $hasAdvancedOfferingFilters = (bool) ($filters['college_id'] || $filters['department_id'] || $filters['grade_level'] !== '' || $filters['group'] !== '' || $filters['teacher_id']);
            @endphp
            <form method="GET" action="{{ route('module-offerings.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900" x-data="{ showMore: {{ $hasAdvancedOfferingFilters ? 'true' : 'false' }} }">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Course code, name, or group">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All semesters</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All statuses</option>
                            @foreach(['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="button" x-on:click="showMore = ! showMore" class="mt-4 flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline dark:text-blue-400">
                    <span x-text="showMore ? 'Fewer filters' : 'More filters'"></span>
                    <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': showMore }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div x-show="showMore" x-cloak class="mt-4 grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-4 dark:border-gray-800">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                        <select name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All stages</option>
                            @foreach($gradeOptions as $grade)
                                <option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group</label>
                        <select name="group" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All groups</option>
                            @foreach($groupOptions as $group)
                                <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teacher</label>
                        <select name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">All teachers</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('module-offerings.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">Apply</button>
                </div>
            </form>

            @if($classificationGroups->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Classification</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($classificationGroups as $college)
                            <div class="p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $college['college'] }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $college['count'] }} modules, {{ $college['students'] }} enrolled</div>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    @foreach($college['departments'] as $department)
                                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $department['department'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $department['count'] }} modules, {{ $department['students'] }} students</div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($department['grades'] as $grade)
                                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $grade['grade'] }}: {{ $grade['count'] }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid gap-4 lg:grid-cols-3">
                @forelse($sections as $section)
                    @php
                        $capacity = max(1, (int) $section->capacity);
                        $fill = min(100, round(($section->enrolled_count / $capacity) * 100));
                    @endphp
                    <a href="{{ route('course-sections.show', $section) }}" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $section->semester->name }} {{ $section->semester->academic_year }} / {{ $section->programSemesterLabel() }} / Group {{ $section->section_code }}</div>
                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $section->course->code }} - {{ $section->course->name }}</div>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $section->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-200' : ($section->status === 'closed' ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200') }}">{{ ucfirst($section->status) }}</span>
                        </div>
                        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">College</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $section->course->department->college->name ?? 'No college' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Department</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $section->course->department->name ?? 'No department' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Stage</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $section->grade_level ?: 'No stage' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Program Semester</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $section->programSemesterLabel() }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Teacher</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $section->teacher->full_name ?? 'Unassigned' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-5">
                            <div class="flex justify-between text-xs font-medium text-gray-600 dark:text-gray-300">
                                <span>{{ $section->enrolled_count }} / {{ $section->capacity }} enrolled</span>
                                <span>{{ $section->waitlisted_count }} waitlisted</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full bg-blue-600" style="width: {{ $fill }}%"></div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 lg:col-span-3">
                        No modules match the current filters.
                    </div>
                @endforelse
            </div>

            <div>
                {{ $sections->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
