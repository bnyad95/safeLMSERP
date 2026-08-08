<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Archived Teachers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Restore archived staff profiles and linked teacher access.</p>
            </div>
            <a href="{{ route('teachers.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Back to Teachers</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('teachers.archived') }}" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="archived-teacher-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search archived teachers</label>
                    <input id="archived-teacher-search" type="search" name="q" value="{{ $search }}" placeholder="Name, staff ID, or email" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('teachers.archived') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Search</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Teacher</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Archived</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($teachers as $teacher)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $teacher->full_name }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $teacher->staff_id }} - {{ $teacher->email }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $teacher->department->college->name ?? 'No college' }}
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $teacher->department->name ?? 'No department' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $teacher->deleted_at?->format('M j, Y') }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <form action="{{ route('teachers.restore', $teacher->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">Restore</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No archived teachers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($teachers->hasPages())
                    <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $teachers->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
