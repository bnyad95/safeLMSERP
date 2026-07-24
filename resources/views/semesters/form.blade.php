@php
    $isEdit = isset($semester);
    $selectedAcademicYear = old('academic_year', $semester->academic_year ?? ($academicYears->first() ?? ''));
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Semester name</label>
            <input type="text" name="name" value="{{ old('name', $semester->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Academic year</label>
            <select name="academic_year" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required @disabled($academicYears->isEmpty())>
                <option value="">Select academic year</option>
                @foreach($academicYears as $academicYear)
                    <option value="{{ $academicYear }}" @selected($selectedAcademicYear === $academicYear)>{{ $academicYear }}</option>
                @endforeach
            </select>
            @if($academicYears->isEmpty())
                <p class="mt-2 text-sm text-amber-700">
                    Create an academic year first, then return here for extra/summer semesters.
                    <a href="{{ route('academic-years.create') }}" class="font-semibold underline">Add Academic Year</a>
                </p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Start date</label>
            <input type="date" name="start_date" value="{{ old('start_date', isset($semester) && $semester->start_date ? \Illuminate\Support\Carbon::parse($semester->start_date)->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">End date</label>
            <input type="date" name="end_date" value="{{ old('end_date', isset($semester) && $semester->end_date ? \Illuminate\Support\Carbon::parse($semester->end_date)->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">University</label>
            <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Select university</option>
                @foreach($universities as $university)
                    <option value="{{ $university->id }}" @selected(old('university_id', $semester->university_id ?? '') == $university->id)>{{ $university->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('semesters.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @disabled(! $isEdit && $academicYears->isEmpty())>{{ $isEdit ? 'Update Semester' : 'Create Extra Semester' }}</button>
    </div>
</div>
