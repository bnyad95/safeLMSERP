<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Bologna Definition') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Academic structure, stages, semesters, modules, credits, and curriculum readiness.') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                    {{ __('Back to Dashboard') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                @foreach($structureStats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Academic Setup') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Use these records as the base before opening modules, enrollment, timetable, attendance, and results.') }}</p>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($setupCards as $card)
                        <a
                            @if($card['enabled']) href="{{ $card['route'] }}" @else aria-disabled="true" @endif
                            class="flex min-h-36 flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950 {{ $card['enabled'] ? 'hover:border-blue-300 hover:bg-blue-50 dark:hover:border-indigo-500 dark:hover:bg-gray-900' : 'cursor-not-allowed opacity-70' }}"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $card['title'] }}</h4>
                                    <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $card['count'] }}</span>
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                            </div>
                            <span class="mt-4 text-sm font-semibold {{ $card['enabled'] ? 'text-blue-700 dark:text-indigo-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $card['enabled'] ? __('Open') : __('No access') }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <a
                    href="{{ route('bologna-definition.student-rankings') }}"
                    class="flex min-h-36 flex-col justify-between rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300 hover:bg-blue-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-500 dark:hover:bg-gray-800"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Student Rankings') }}</h3>
                            <span class="rounded bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                {{ __('Passed students') }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Open published final mark rankings by college, department, stage, and academic year.') }}</p>
                    </div>
                    <span class="mt-4 text-sm font-semibold text-blue-700 dark:text-indigo-300">{{ __('Open rankings') }}</span>
                </a>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.8fr)]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Stages And Credits') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Modules grouped by Bologna stage, with enrolled students and total catalog credits.') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('College / Department') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Stage') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Modules') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Catalog Courses') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Semesters') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Students') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Credits') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse($stageSummaries as $stage)
                                    <tr>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $stage['department'] }}</span>
                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $stage['college'] }} · {{ $stage['university'] }}</span>
                                        </td>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $stage['stage'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage['modules'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage['courses'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage['semesters'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage['students'] }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage['credits'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No modules have been defined yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Curriculum Checks') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Items to review before the structure is ready for daily operations.') }}</p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($curriculumSignals as $signal)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $signal['label'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $signal['detail'] }}</p>
                                </div>
                                <span class="rounded-md {{ $signal['value'] > 0 ? 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200' : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' }} px-3 py-1 text-sm font-semibold">
                                    {{ $signal['value'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Recent Modules') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Course modules currently connected to stages, semesters, teachers, credits, and active students.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Module') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Course') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Stage') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Semester') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Teacher') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Students') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Credits') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            @forelse($moduleSummaries as $module)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $module['module'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['course'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['stage'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['semester'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['teacher'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['students'] }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module['credits'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No modules have been defined yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
