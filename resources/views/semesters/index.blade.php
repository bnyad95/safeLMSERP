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
                    <a href="{{ route('semesters.create') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Add Extra Semester</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    <thead class="bg-gray-50 dark:bg-gray-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Semester</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Academic Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">University</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date Range</th>
                            @if($canManageSemesters)<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($semesters as $semester)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $semester->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $semester->academic_year }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $semester->university->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $semester->start_date ? \Illuminate\Support\Carbon::parse($semester->start_date)->format('Y-m-d') : 'TBD' }} -
                                    {{ $semester->end_date ? \Illuminate\Support\Carbon::parse($semester->end_date)->format('Y-m-d') : 'TBD' }}
                                </td>
                                @if($canManageSemesters)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('semesters.edit', $semester) }}" class="text-blue-600 hover:text-blue-900">Edit</a>
                                        <form action="{{ route('semesters.destroy', $semester) }}" method="POST" class="ml-3 inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this semester?')">Delete</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageSemesters ? 5 : 4 }}" class="px-6 py-8 text-center text-sm text-gray-500">No semesters defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">{{ $semesters->links() }}</div>
        </div>
    </div>
</x-app-layout>
