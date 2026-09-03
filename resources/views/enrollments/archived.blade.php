<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Archived Modules') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Closed enrollment modules that were removed from the active directory.') }}</p>
            </div>
            <a href="{{ route('module-offerings.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('course-sections.archived') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 md:flex-row">
                    <input name="q" value="{{ $search }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="{{ __('Search archived modules') }}">
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('Search') }}</button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Module') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Teacher') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">{{ __('Archived') }}</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($sections as $section)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $section->course->code ?? __('Course') }} - {{ $section->course->name ?? __('Archived course') }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $section->semester->name ?? __('Semester') }} {{ $section->semester->academic_year ?? '' }} / {{ __('Group :code', ['code' => $section->section_code]) }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $section->teacher->full_name ?? __('Unassigned') }}</td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $section->deleted_at?->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-right">
                                    <form method="POST" action="{{ route('course-sections.restore', $section->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Restore') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No archived modules found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            {{ $sections->links() }}
        </div>
    </div>
</x-app-layout>
