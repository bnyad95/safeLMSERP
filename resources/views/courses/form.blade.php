@php
    $isEdit = isset($course);
    $selectedDepartmentId = (string) old('department_id', $course->department_id ?? '');
    $selectedDepartment = $departments->firstWhere('id', (int) $selectedDepartmentId);
    $selectedCollegeId = (string) old('college_id', $selectedDepartment?->college_id ?? '');
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Course code') }}</label>
            <input type="text" name="code" value="{{ old('code', $course->code ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Course name') }}</label>
            <input type="text" name="name" value="{{ old('name', $course->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('College') }}</label>
            <select id="course-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="">{{ __('Select college') }}</option>
                @foreach($colleges as $college)
                    <option value="{{ $college->id }}" @selected($selectedCollegeId === (string) $college->id)>{{ $college->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Department') }}</label>
            <select id="course-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="">{{ __('Select department') }}</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" data-college-id="{{ $department->college_id }}" @selected($selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Credits') }}</label>
            <input type="number" min="0.5" max="30" step="0.5" name="credits" value="{{ old('credits', $course->credits ?? 3) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Status') }}</label>
            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="active" @selected(old('status', $course->status ?? 'active') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(old('status', $course->status ?? 'active') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('course-records.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? __('Update Course') : __('Create Course') }}</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const collegeSelect = document.getElementById('course-college');
        const departmentSelect = document.getElementById('course-department');

        if (!collegeSelect || !departmentSelect) {
            return;
        }

        const filterDepartments = () => {
            const selectedCollege = collegeSelect.value;

            Array.from(departmentSelect.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const matchesCollege = option.dataset.collegeId === selectedCollege;
                option.hidden = selectedCollege !== '' && !matchesCollege;

                if (option.selected && option.hidden) {
                    departmentSelect.value = '';
                }
            });
        };

        collegeSelect.addEventListener('change', filterDepartments);
        filterDepartments();
    });
</script>
