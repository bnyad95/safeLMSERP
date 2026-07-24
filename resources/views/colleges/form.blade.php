@php $isEdit = isset($college); @endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">College name</label>
            <input type="text" name="name" value="{{ old('name', $college->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Code</label>
            <input type="text" name="code" value="{{ old('code', $college->code ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">University</label>
            <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Select university</option>
                @foreach($universities as $university)
                    <option value="{{ $university->id }}" @selected(old('university_id', $college->university_id ?? '') == $university->id)>{{ $university->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('colleges.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update College' : 'Create College' }}</button>
    </div>
</div>
