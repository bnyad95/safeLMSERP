<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Academic Year') }}</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $academicYear->name }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $academicYear->university?->name }} / {{ $academicYear->starts_on?->format('M j, Y') }} - {{ $academicYear->ends_on?->format('M j, Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('academic-years.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('All Academic Years') }}</a>
                @if($canManageAcademicSetup && !$academicYear->isLocked())
                    <a href="{{ route('semesters.create', ['university_id' => $academicYear->university_id, 'academic_year_id' => $academicYear->id]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Add Semester') }}</a>
                    <a href="{{ route('academic-years.edit', $academicYear) }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('Edit Year') }}</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Status') }}</p>
                    <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ match($academicYear->status) { 'active' => __('Active'), 'upcoming' => __('Upcoming'), 'closed' => __('Closed'), 'archived' => __('Archived'), default => ucfirst($academicYear->status) } }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Semesters') }}</p>
                    <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $semesters->count() }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Modules') }}</p>
                    <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($modules->total()) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Date Range') }}</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $academicYear->starts_on?->format('Y-m-d') }} - {{ $academicYear->ends_on?->format('Y-m-d') }}</p>
                </div>
            </section>

            @if($academicYear->isLocked())
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ __('This academic year is closed. Its semesters, modules, enrollments, and timetable are read-only.') }}</div>
            @endif

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Semesters') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Ordered academic terms inside this year.') }}</p>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($semesters as $semester)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $semester->sequence }}. {{ $semester->name }}</p>
                                    <p class="mt-1 text-xs uppercase text-gray-500 dark:text-gray-400">{{ $semester->term_type === 'summer' ? __('Summer') : __('Regular') }}</p>
                                </div>
                                <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ __(':count modules', ['count' => $semester->course_sections_count]) }}</span>
                            </div>
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ $semester->start_date?->format('M j, Y') }} - {{ $semester->end_date?->format('M j, Y') }}</p>
                            @if($canManageAcademicSetup && !$academicYear->isLocked())
                                <a href="{{ route('semesters.edit', $semester) }}" class="mt-4 inline-block text-sm font-semibold text-blue-700 dark:text-indigo-300">{{ __('Edit semester') }}</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 md:col-span-2 xl:col-span-3">{{ __('No semesters have been defined for this academic year.') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Module Offerings') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Modules opened inside this academic year.') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Module') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Semester') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Department / Stage') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Teacher') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Students') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($modules as $module)
                                <tr>
                                    <td class="px-5 py-4 text-sm"><a href="{{ route('course-sections.show', $module) }}" class="font-semibold text-blue-700 dark:text-indigo-300">{{ $module->course?->code }} - {{ $module->course?->name }}</a><p class="mt-1 text-xs text-gray-500">{{ __('Group :code', ['code' => $module->section_code]) }}</p></td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module->semester?->name }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module->course?->department?->name ?? __('No department') }} / {{ $module->stage?->name ?? ($module->grade_level ?: __('No stage')) }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module->teacher?->full_name ?? __('Unassigned') }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $module->active_enrollments_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No modules have been opened for this year.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($modules->hasPages())<div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $modules->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
