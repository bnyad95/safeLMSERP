<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bologna Definition</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Student Rankings</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">All passed students ranked by college, department, stage, and academic year.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Back to Bologna Definition
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ranking Directory</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open a college, department, stage, and year to see every student who passed all published modules.</p>
                        </div>
                        <span class="w-fit rounded bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">All passed students</span>
                    </div>
                </div>

                <form method="GET" class="grid gap-3 border-b border-gray-200 p-5 sm:grid-cols-2 xl:grid-cols-6 dark:border-gray-800">
                    <div class="sm:col-span-2"><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Student</label><input name="q" value="{{ $rankingFilters['q'] }}" placeholder="Name or student ID" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></div>
                    <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">College</label><select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">All colleges</option>@foreach($rankingOptions['colleges'] as $option)<option value="{{ $option->college_id }}" @selected($rankingFilters['college_id'] === (int) $option->college_id)>{{ $option->college }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Department</label><select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">All departments</option>@foreach($rankingOptions['departments'] as $option)<option value="{{ $option->department_id }}" @selected($rankingFilters['department_id'] === (int) $option->department_id)>{{ $option->department }}</option>@endforeach</select></div>
                    <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label><select name="stage_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">All stages</option>@foreach($rankingOptions['stages'] as $option)@if((int) $option->stage_id > 0)<option value="{{ $option->stage_id }}" @selected($rankingFilters['stage_id'] === (int) $option->stage_id)>{{ $option->stage }}</option>@endif @endforeach</select></div>
                    <div><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label><select name="academic_year" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">All years</option>@foreach($rankingOptions['academicYears'] as $year)<option value="{{ $year }}" @selected($rankingFilters['academic_year'] === $year)>{{ $year }}</option>@endforeach</select></div>
                    <div class="flex gap-2 sm:col-span-2 xl:col-span-6 xl:justify-end"><a href="{{ route('bologna-definition.student-rankings') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Reset</a><button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-600">Apply</button></div>
                </form>

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($studentRankingHierarchy as $college)
                        <details class="group" @if($studentRankingHierarchy->count() === 1) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $college['university'] }} / {{ $college['college'] }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $college['students'] }} passed</span>
                            </summary>
                            <div class="space-y-3 border-t border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                                @foreach($college['departments'] as $department)
                                    <details class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $department['department'] }}</span>
                                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $department['students'] }} passed</span>
                                        </summary>
                                        <div class="space-y-3 border-t border-gray-100 p-4 dark:border-gray-800">
                                            @foreach($department['stages'] as $stage)
                                                <details class="rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 hover:bg-white dark:hover:bg-gray-900">
                                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $stage['stage'] }}</span>
                                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stage['students'] }} passed</span>
                                                    </summary>
                                                    <div class="space-y-3 border-t border-gray-100 p-4 dark:border-gray-800">
                                                        @foreach($stage['years'] as $year)
                                                            <details class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $year['academic_year'] }}</span>
                                                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $year['students']->count() }} ranked</span>
                                                                </summary>
                                                                <div class="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
                                                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                                                        <thead class="bg-gray-50 dark:bg-gray-950">
                                                                            <tr>
                                                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Rank</th>
                                                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Student</th>
                                                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Average</th>
                                                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Marks</th>
                                                                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Pass Rate</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                                            @foreach($year['students'] as $student)
                                                                                <tr>
                                                                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $student['rank'] }}</td>
                                                                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $student['name'] }}</span>
                                                                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $student['student_number'] }}</span>
                                                                                    </td>
                                                                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($student['average'], 1) }}</td>
                                                                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $student['marks'] }}</td>
                                                                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $student['pass_rate'] }}%</td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </details>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">No passed students available</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Students who pass all published modules will appear here after results are released.</p>
                        </div>
                    @endforelse
                </div>
            </section>
            <div class="mt-5">{{ $rankings->links() }}</div>
        </div>
    </div>
</x-app-layout>
