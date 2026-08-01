<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Semesters</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Define semester terms and academic periods.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('bologna-definition') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Bologna Hub</a>
                @if($canManageSemesters)
                    <a href="{{ route('academic-years.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add Academic Year</a>
                    <a href="{{ route('semesters.create') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Add Semester</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('semesters.index') }}" class="grid gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 md:grid-cols-3">
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label><select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"><option value="">All universities</option>@foreach($universities as $university)<option value="{{ $university->id }}" @selected($filters['university_id'] === $university->id)>{{ $university->name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label><select name="academic_year_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected($filters['academic_year_id'] === $year->id)>{{ $year->name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label><select name="term_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"><option value="">All types</option><option value="regular" @selected($filters['term_type'] === 'regular')>Regular</option><option value="summer" @selected($filters['term_type'] === 'summer')>Summer</option></select></div>
                <div class="flex gap-2 md:col-span-3 md:justify-end"><a href="{{ route('semesters.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Reset</a><button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-600">Apply</button></div>
            </form>
            <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    <thead class="bg-gray-50 dark:bg-gray-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Semester</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Academic Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">University</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Modules</th>
                            @if($canManageSemesters)<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($semesters as $semester)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $semester->sequence }}. {{ $semester->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($semester->term_type) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><a href="{{ $semester->academicYear ? route('academic-years.show', $semester->academicYear) : '#' }}" class="font-semibold text-blue-700 dark:text-indigo-300">{{ $semester->academic_year }}</a></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $semester->university->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $semester->start_date ? \Illuminate\Support\Carbon::parse($semester->start_date)->format('Y-m-d') : 'TBD' }} -
                                    {{ $semester->end_date ? \Illuminate\Support\Carbon::parse($semester->end_date)->format('Y-m-d') : 'TBD' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $semester->course_sections_count }}</td>
                                @if($canManageSemesters)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if(!$semester->academicYear?->isLocked())
                                            <a href="{{ route('semesters.edit', $semester) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                                            <form action="{{ route('semesters.destroy', $semester) }}" method="POST" class="ml-3 inline-block">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this semester?')">Delete</button></form>
                                        @else
                                            <span class="text-gray-400">Locked</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageSemesters ? 7 : 6 }}" class="px-6 py-8 text-center text-sm text-gray-500">No semesters defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">{{ $semesters->links() }}</div>
        </div>
    </div>
</x-app-layout>
