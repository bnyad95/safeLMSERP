@php
    $selectedDepartmentId = (string) old('department_id', $stage->department_id ?? '');
    $selectedDepartment = $departments->firstWhere('id', (int) $selectedDepartmentId);
    $selectedCollegeId = (string) old('college_id', $selectedDepartment?->college_id ?? '');
@endphp
<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="grid gap-5 md:grid-cols-2">
        <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">College</label><select id="stage-college" name="college_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">Select college</option>@foreach($colleges as $college)<option value="{{ $college->id }}" @selected($selectedCollegeId === (string) $college->id)>{{ $college->name }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Department</label><select id="stage-department" name="department_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-college-id="{{ $department->college_id }}" @selected($selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stage name</label><input name="name" value="{{ old('name', $stage->name ?? '') }}" required maxlength="100" placeholder="Stage 1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></div>
        <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input name="code" value="{{ old('code', $stage->code ?? '') }}" maxlength="50" placeholder="S1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></div>
        <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sequence</label><input type="number" name="sequence" min="1" max="20" value="{{ old('sequence', $stage->sequence ?? 1) }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></div>
    </div>
    <div class="flex justify-end gap-3"><a href="{{ route('stages.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancel</a><button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-600">{{ isset($stage) ? 'Update Stage' : 'Create Stage' }}</button></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const college = document.getElementById('stage-college');
    const department = document.getElementById('stage-department');
    if (!college || !department) return;
    const filter = () => Array.from(department.options).forEach((option) => {
        if (!option.value) return;
        option.hidden = college.value !== '' && option.dataset.collegeId !== college.value;
        if (option.selected && option.hidden) department.value = '';
    });
    college.addEventListener('change', filter);
    filter();
});
</script>
