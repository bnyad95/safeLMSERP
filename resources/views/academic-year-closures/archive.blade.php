<x-app-layout>
    <x-slot name="header">
        <div>
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Academic Year Archive') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Closed academic years and the archived modules kept for historical access.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Closed Years') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $years->count() }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Archived Modules') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($years->sum('archived_modules')) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Students In Snapshots') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($years->sum('student_count')) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Open Invoices At Closure') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($years->sum('open_finance_invoices')) }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Academic Year Archive') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Each year stays visible after closure so students, teachers, and administrators can review old class records.') }}</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($years as $year)
                        <a href="{{ route('academic-year-closures.archive.show', ['academic_year' => $year['academic_year']]) }}" class="grid gap-4 px-5 py-5 hover:bg-blue-50 dark:hover:bg-gray-800 lg:grid-cols-[minmax(220px,1fr)_repeat(5,minmax(110px,auto))] lg:items-center">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $year['academic_year'] }}</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $year['universities']->join(', ') ?: __('Institution scope') }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                    {{ __('Closed :date', ['date' => $year['closed_at'] ? $year['closed_at']->format('Y-m-d H:i') : __('date not recorded')]) }}
                                    @if($year['closed_by']->isNotEmpty())
                                        {{ __('by :names', ['names' => $year['closed_by']->join(', ')]) }}
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Semesters') }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($year['semester_count']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Students') }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($year['student_count']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Enrollments') }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($year['enrollment_count']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Archived Modules') }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($year['archived_modules']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Closed Not Archived') }}</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($year['visible_closed_modules']) }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No closed academic years yet. Closed years will appear here after the academic year closing workflow is completed.') }}
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
