<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Bologna Definition</h2>
                <p class="text-sm text-gray-600">Academic structure, stages, semesters, modules, credits, and curriculum readiness.</p>
            </div>
            <div class="flex gap-2">
                @if($canManageAcademicSetup)
                    <a href="{{ route('academic-years.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Add Academic Year
                    </a>
                @endif
                <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $setupCards = [
            ['title' => 'Universities', 'description' => 'Institution records and official codes.', 'route' => route('universities.index'), 'count' => $universities->count()],
            ['title' => 'Colleges', 'description' => 'College definitions under each university.', 'route' => route('colleges.index'), 'count' => $colleges->count()],
            ['title' => 'Departments', 'description' => 'Departments mapped to colleges and universities.', 'route' => route('departments.index'), 'count' => $departments->count()],
            ['title' => 'Semesters', 'description' => 'Academic periods with year and date range.', 'route' => route('semesters.index'), 'count' => $semesters->count()],
            ['title' => 'Course Names', 'description' => 'Catalog definitions, credits, and status.', 'route' => route('course-records.index'), 'count' => $courses->count()],
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                @foreach($structureStats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Academic Setup</h3>
                    <p class="mt-1 text-sm text-gray-500">Use these records as the base before opening modules, enrollment, timetable, attendance, and results.</p>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($setupCards as $card)
                        <a href="{{ $card['route'] }}" class="flex min-h-36 flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300 hover:bg-blue-50">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900">{{ $card['title'] }}</h4>
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ $card['count'] }}</span>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">{{ $card['description'] }}</p>
                            </div>
                            <span class="mt-4 text-sm font-semibold text-blue-700">Open</span>
                        </a>
                    @endforeach
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.8fr)]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Stages And Credits</h3>
                        <p class="mt-1 text-sm text-gray-500">Modules grouped by Bologna stage, with enrolled students and total catalog credits.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Modules</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Courses</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Semesters</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Students</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Credits</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse($stageSummaries as $stage)
                                    <tr>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $stage['stage'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600">{{ $stage['modules'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600">{{ $stage['courses'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600">{{ $stage['semesters'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600">{{ $stage['students'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600">{{ $stage['credits'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">No modules have been defined yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Curriculum Checks</h3>
                        <p class="mt-1 text-sm text-gray-500">Items to review before the structure is ready for daily operations.</p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($curriculumSignals as $signal)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $signal['label'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $signal['detail'] }}</p>
                                </div>
                                <span class="rounded-md {{ $signal['value'] > 0 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800' }} px-3 py-1 text-sm font-semibold">
                                    {{ $signal['value'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Recent Modules</h3>
                    <p class="mt-1 text-sm text-gray-500">Course modules currently connected to stages, semesters, teachers, credits, and active students.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Module</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Course</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Semester</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teacher</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Students</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Credits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($moduleSummaries as $module)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $module['module'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['course'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['stage'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['semester'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['teacher'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['students'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $module['credits'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">No modules have been defined yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
