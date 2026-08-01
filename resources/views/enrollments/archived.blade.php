<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Archived Modules</h2>
                <p class="text-sm text-gray-600">Closed enrollment modules that were removed from the active directory.</p>
            </div>
            <a href="{{ route('module-offerings.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('course-sections.archived') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row">
                    <input name="q" value="{{ $search }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Search archived modules">
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Search</button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Module</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Teacher</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Archived</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($sections as $section)
                            <tr>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">{{ $section->course->code ?? 'Course' }} - {{ $section->course->name ?? 'Archived course' }}</div>
                                    <div class="text-sm text-gray-500">{{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }} / Group {{ $section->section_code }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600">{{ $section->teacher->full_name ?? 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-sm text-gray-600">{{ $section->deleted_at?->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-right">
                                    <form method="POST" action="{{ route('course-sections.restore', $section->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Restore</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No archived modules found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sections->links() }}
        </div>
    </div>
</x-app-layout>
