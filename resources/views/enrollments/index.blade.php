<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Enrollments</h2>
                <p class="text-sm text-gray-600">Semester modules, rosters, waitlists, transfers, and enrollment history.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('course-records.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Course Catalog</a>
                @if($abilities['manage'])
                    <a href="{{ route('course-sections.archived') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Archived {{ $archivedCount ? '('.$archivedCount.')' : '' }}</a>
                    <a href="{{ route('course-sections.create') }}" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Add Module</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-4">
                @foreach([
                    ['label' => 'Modules', 'value' => $stats['sections'], 'tone' => 'bg-white'],
                    ['label' => 'Active', 'value' => $stats['active'], 'tone' => 'bg-green-50'],
                    ['label' => 'Enrolled Students', 'value' => $stats['students'], 'tone' => 'bg-blue-50'],
                    ['label' => 'Waitlisted', 'value' => $stats['waitlisted'], 'tone' => 'bg-amber-50'],
                ] as $stat)
                    <div class="{{ $stat['tone'] }} rounded-lg border border-gray-200 p-5 shadow-sm">
                        <div class="text-sm font-medium text-gray-600">{{ $stat['label'] }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <form method="GET" action="{{ route('enrollments.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Search</label>
                        <input name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Course code, name, or group">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">College</label>
                        <select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stage</label>
                        <select name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($gradeOptions as $grade)
                                <option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All semesters</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Group</label>
                        <select name="group" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All groups</option>
                            @foreach($groupOptions as $group)
                                <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Teacher</label>
                        <select name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All teachers</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All statuses</option>
                            @foreach(['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('enrollments.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                </div>
            </form>

            @if($classificationGroups->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Classification</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($classificationGroups as $college)
                            <div class="p-5">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $college['college'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $college['count'] }} modules, {{ $college['students'] }} enrolled</div>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-3">
                                    @foreach($college['departments'] as $department)
                                        <div class="rounded-lg border border-gray-200 p-4">
                                            <div class="text-sm font-semibold text-gray-900">{{ $department['department'] }}</div>
                                            <div class="mt-1 text-xs text-gray-500">{{ $department['count'] }} modules, {{ $department['students'] }} students</div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach($department['grades'] as $grade)
                                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">{{ $grade['grade'] }}: {{ $grade['count'] }}</span>
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
                    <a href="{{ route('course-sections.show', $section) }}" class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-gray-300 hover:shadow-md">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-500">{{ $section->semester->name }} {{ $section->semester->academic_year }} / Group {{ $section->section_code }}</div>
                                <div class="mt-1 text-lg font-semibold text-gray-900">{{ $section->course->code }} - {{ $section->course->name }}</div>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $section->status === 'active' ? 'bg-green-100 text-green-700' : ($section->status === 'closed' ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-700') }}">{{ ucfirst($section->status) }}</span>
                        </div>
                        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-gray-500">College</dt>
                                <dd class="font-medium text-gray-900">{{ $section->course->department->college->name ?? 'No college' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Department</dt>
                                <dd class="font-medium text-gray-900">{{ $section->course->department->name ?? 'No department' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Stage</dt>
                                <dd class="font-medium text-gray-900">{{ $section->grade_level ?: 'No stage' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Teacher</dt>
                                <dd class="font-medium text-gray-900">{{ $section->teacher->full_name ?? 'Unassigned' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-5">
                            <div class="flex justify-between text-xs font-medium text-gray-600">
                                <span>{{ $section->enrolled_count }} / {{ $section->capacity }} enrolled</span>
                                <span>{{ $section->waitlisted_count }} waitlisted</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-blue-600" style="width: {{ $fill }}%"></div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 lg:col-span-3">
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
