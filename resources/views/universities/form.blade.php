@php
    $isEdit = isset($university);
    $selectedType = old('institution_type', $university->institution_type ?? \App\Models\University::TYPE_UNIVERSITY);
    $selectedStructure = \App\Models\University::structureForType($selectedType);
    $availableStructures = collect([\App\Models\University::TYPE_UNIVERSITY, \App\Models\University::TYPE_INSTITUTE])
        ->mapWithKeys(function ($type) {
            $structure = \App\Models\University::structureForType($type);

            return [$type => [
                'stages' => $structure['expected_stage_count'],
                'semesters' => $structure['expected_semesters_per_year'],
            ]];
        });
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization name</label>
            <input type="text" name="name" value="{{ old('name', $university->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
            <input type="text" name="code" value="{{ old('code', $university->code ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Institution type</label>
            <select id="institution-type" name="institution_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <option value="university" @selected($selectedType === 'university')>University</option>
                <option value="institute" @selected($selectedType === 'institute')>Institute</option>
            </select>
        </div>

        <div class="md:col-span-2 border-y border-gray-200 py-4 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Automatic academic structure</p>
            <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3 sm:divide-x sm:divide-gray-200 dark:sm:divide-gray-800">
                <div><span id="structure-stage-count" class="block text-xl font-semibold text-gray-900 dark:text-white">{{ $selectedStructure['expected_stage_count'] }}</span><span class="text-gray-500 dark:text-gray-400">Program stages</span></div>
                <div class="sm:pl-4"><span id="structure-year-semesters" class="block text-xl font-semibold text-gray-900 dark:text-white">{{ $selectedStructure['expected_semesters_per_year'] }}</span><span class="text-gray-500 dark:text-gray-400">Regular semesters per year</span></div>
                <div class="sm:pl-4"><span id="structure-program-semesters" class="block text-xl font-semibold text-gray-900 dark:text-white">{{ $selectedStructure['expected_stage_count'] * $selectedStructure['expected_semesters_per_year'] }}</span><span class="text-gray-500 dark:text-gray-400">Standard program semesters</span></div>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">The standard structure is fixed by institution type. Students may repeat a stage or use summer and later-year retake periods without changing the program numbering.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
            <input type="email" name="email" value="{{ old('email', $university->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $university->phone ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('universities.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update Organization' : 'Create Organization' }}</button>
    </div>
</div>

<script>
    (function () {
        const type = document.getElementById('institution-type');
        const stageCount = document.getElementById('structure-stage-count');
        const yearSemesters = document.getElementById('structure-year-semesters');
        const programSemesters = document.getElementById('structure-program-semesters');
        if (!type || !stageCount || !yearSemesters || !programSemesters) return;

        const structures = @json($availableStructures);
        const refresh = () => {
            const structure = structures[type.value] || structures.university;
            stageCount.textContent = structure.stages;
            yearSemesters.textContent = structure.semesters;
            programSemesters.textContent = structure.stages * structure.semesters;
        };

        type.addEventListener('change', refresh);
        refresh();
    })();
</script>
