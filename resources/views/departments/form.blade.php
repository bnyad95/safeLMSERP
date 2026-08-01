@php $isEdit = isset($department); @endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department name</label>
            <input type="text" name="name" value="{{ old('name', $department->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
            <input type="text" name="code" value="{{ old('code', $department->code ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label>
            <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="">Select university</option>
                @foreach($universities as $university)
                    <option value="{{ $university->id }}" @selected(old('university_id', $department->university_id ?? '') == $university->id)>{{ $university->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
            <select name="college_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                <option value="">Select college</option>
                @foreach($colleges as $college)
                    <option value="{{ $college->id }}" @selected(old('college_id', $department->college_id ?? '') == $college->id)>{{ $college->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('departments.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update Department' : 'Create Department' }}</button>
    </div>
</div>
