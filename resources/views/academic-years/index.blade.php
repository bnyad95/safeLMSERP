<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bologna Definition</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Academic Years</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Manage academic periods without redefining the organization or Course Catalog.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Bologna Definition</a>
                @if($canManageAcademicSetup)
                    <a href="{{ route('academic-years.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Add Academic Year</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-3 sm:grid-cols-2">
                <a href="{{ route('academic-year-closures.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-500">
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Academic Year Closing</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Validate results and close a completed year.</p>
                </a>
                <a href="{{ route('academic-year-closures.archive') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-500">
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Archive Years</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review closed years and their preserved records.</p>
                </a>
            </div>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Academic Year</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">University</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Dates</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Semesters</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Modules</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($academicYears as $year)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $year->name }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $year->university?->name ?? 'N/A' }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $year->starts_on?->format('Y-m-d') ?? 'TBD' }} - {{ $year->ends_on?->format('Y-m-d') ?? 'TBD' }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $year->semesters_count }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $year->modules_count }}</td>
                                    <td class="px-5 py-4"><span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $year->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">{{ ucfirst($year->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No academic years have been created.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            {{ $academicYears->links() }}
        </div>
    </div>
</x-app-layout>
