@php
    $isEdit = isset($semester);
    $currentSemester = $semester ?? null;
    $selectedUniversity = old('university_id', $currentSemester?->university_id ?? ($selectedUniversityId ?? ''));
    $selectedAcademicYear = old('academic_year_id', $currentSemester?->academic_year_id ?? ($selectedAcademicYearId ?? ''));
    $selectedType = old('term_type', $currentSemester?->term_type ?? 'regular');
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label for="semester-university-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('University') }}</label>
            <select id="semester-university-id" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="">{{ __('Select university') }}</option>
                @foreach($universities as $university)
                    <option value="{{ $university->id }}" data-institution-type="{{ $university->institution_type ?? 'university' }}" data-expected-semesters="{{ $university->expectedSemesterCount() }}" @selected($selectedUniversity == $university->id)>{{ $university->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="semester-academic-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic year') }}</label>
            <select id="semester-academic-year" name="academic_year_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required @disabled($academicYears->isEmpty())>
                <option value="">{{ __('Select academic year') }}</option>
                @foreach($academicYears as $academicYear)
                    <option value="{{ $academicYear->id }}" data-university-id="{{ $academicYear->university_id }}" data-status="{{ $academicYear->status }}" @selected($selectedAcademicYear == $academicYear->id)>{{ $academicYear->name }} / {{ match($academicYear->status) { 'active' => __('Active'), 'upcoming' => __('Upcoming'), 'closed' => __('Closed'), 'archived' => __('Archived'), default => ucfirst($academicYear->status) } }}</option>
                @endforeach
            </select>
            @if($academicYears->isEmpty())
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ __('Create an academic year first.') }} <a href="{{ route('academic-years.create') }}" class="font-semibold underline">{{ __('Add Academic Year') }}</a></p>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester name') }}</label>
            <input type="text" name="name" value="{{ old('name', $currentSemester?->name ?? '') }}" placeholder="Semester 1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Type') }}</label>
            <select id="semester-term-type" name="term_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="regular" @selected($selectedType === 'regular')>{{ __('Regular semester') }}</option>
                <option value="summer" @selected($selectedType === 'summer')>{{ __('Summer semester') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Sequence') }}</label>
            <input type="number" name="sequence" min="1" max="20" value="{{ old('sequence', $currentSemester?->sequence ?? '') }}" placeholder="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
            <p id="semester-cap-hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></p>
        </div>

        <div></div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Start date') }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', $currentSemester?->start_date?->format('Y-m-d') ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('End date') }}</label>
            <input type="date" name="end_date" value="{{ old('end_date', $currentSemester?->end_date?->format('Y-m-d') ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $selectedAcademicYear ? route('academic-years.show', $selectedAcademicYear) : route('semesters.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @disabled(! $isEdit && $academicYears->isEmpty())>{{ $isEdit ? __('Update Semester') : __('Create Semester') }}</button>
    </div>
</div>

<script>
    (function () {
        const universitySelect = document.getElementById('semester-university-id');
        const academicYearSelect = document.getElementById('semester-academic-year');
        const semesterCapHint = document.getElementById('semester-cap-hint');
        if (!universitySelect || !academicYearSelect || !semesterCapHint) return;

        const refresh = () => {
            const universityId = universitySelect.value;
            const selectedUniversity = universitySelect.options[universitySelect.selectedIndex];
            const expected = selectedUniversity?.dataset.expectedSemesters || '2';
            semesterCapHint.textContent = `${expected} regular semesters, followed by one optional summer semester.`;

            let selectedVisible = false;
            Array.from(academicYearSelect.options).forEach((option) => {
                if (!option.value) return;
                const visible = option.dataset.universityId === universityId && !['closed', 'archived'].includes(option.dataset.status);
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible && option.selected) selectedVisible = true;
            });
            if (!selectedVisible) academicYearSelect.value = '';
        };

        universitySelect.addEventListener('change', refresh);
        refresh();
    })();
</script>
