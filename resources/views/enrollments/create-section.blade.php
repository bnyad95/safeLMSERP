<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Add Course Module</h2>
                <p class="text-sm text-gray-600">Create a semester module from an active catalog course.</p>
            </div>
            <a href="{{ route('enrollments.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('course-sections.create') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course</label>
                        <input name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Code or name">
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
                    @if($canAssignTeachers)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Teacher</label>
                            <input name="teacher_q" value="{{ $filters['teacher_q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Name or staff ID">
                        </div>
                    @endif
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('course-sections.create') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Search</button>
                </div>
            </form>

            <form method="POST" action="{{ route('course-sections.store') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                <input type="hidden" name="open_section" value="1">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Course</label>
                        <select name="course_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select active course</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" data-department-id="{{ $course->department_id }}" @selected(old('course_id') == $course->id)>{{ $course->code }} - {{ $course->name }} / {{ $course->department->name ?? 'No department' }}</option>
                            @endforeach
                        </select>
                        @error('course_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select semester</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                        @error('semester_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if($canAssignTeachers)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Teacher</label>
                            <select name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Unassigned</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->full_name }} / {{ $teacher->staff_id }}</option>
                                @endforeach
                            </select>
                            @error('teacher_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Group</label>
                        <input name="section_code" value="{{ old('section_code', 'A') }}" maxlength="50" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @error('section_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stage</label>
                        <select id="module-stage" name="stage_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select a managed stage</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage->id }}" data-department-id="{{ $stage->department_id }}" @selected(old('stage_id') == $stage->id)>{{ $stage->name }}</option>
                            @endforeach
                        </select>
                        @error('stage_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Capacity</label>
                        <input type="number" min="1" max="500" name="capacity" value="{{ old('capacity', 40) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @error('capacity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            @foreach(['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                        @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Module</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const course = document.querySelector('select[name="course_id"]');
            const stage = document.getElementById('module-stage');
            if (!course || !stage) return;
            const filterStages = () => {
                const departmentId = course.selectedOptions[0]?.dataset.departmentId || '';
                Array.from(stage.options).forEach((option) => {
                    if (!option.value) return;
                    option.hidden = departmentId !== '' && option.dataset.departmentId !== departmentId;
                    if (option.selected && option.hidden) stage.value = '';
                });
            };
            course.addEventListener('change', filterStages);
            filterStages();
        });
    </script>
</x-app-layout>
