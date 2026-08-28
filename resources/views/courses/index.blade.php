<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Course Catalog</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Reusable academic catalog organized by college and department.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($abilities['archive'])
                    <a href="{{ route('course-records.archived') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Archived ({{ $archivedCount }})</a>
                @endif
                @if($abilities['create'])
                    <a href="{{ route('course-records.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">Add Course</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section aria-label="Course totals" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"><p class="text-sm text-gray-500 dark:text-gray-400">Matching courses</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</p></div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/40"><p class="text-sm text-emerald-800 dark:text-emerald-300">Active catalog</p><p class="mt-2 text-2xl font-semibold text-emerald-950 dark:text-emerald-100">{{ $stats['active'] }}</p></div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"><p class="text-sm text-amber-800 dark:text-amber-300">Inactive catalog</p><p class="mt-2 text-2xl font-semibold text-amber-950 dark:text-amber-100">{{ $stats['inactive'] }}</p></div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/40"><p class="text-sm text-blue-800 dark:text-blue-300">Open modules</p><p class="mt-2 text-2xl font-semibold text-blue-950 dark:text-blue-100">{{ $stats['open_sections'] }}</p></div>
            </section>

            <form method="GET" action="{{ route('course-records.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="md:col-span-2 xl:col-span-2">
                        <label for="course-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input id="course-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Course code or name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="course-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select id="course-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)<option value="{{ $college->id }}" @selected((string) $filters['college_id'] === (string) $college->id)>{{ $college->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="course-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select id="course-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}{{ $department->college ? ' / '.$department->college->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="course-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="course-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All statuses</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="course-credits" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Credits</label>
                        <select id="course-credits" name="credits" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All credits</option>
                            @foreach($creditOptions as $credits)<option value="{{ $credits }}" @selected((string) $filters['credits'] === (string) $credits)>{{ $credits }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('course-records.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="course-classification-title">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 id="course-classification-title" class="font-semibold text-gray-900 dark:text-gray-100">Academic classification</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Matching catalog grouped by college and department.</p>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($classificationGroups as $collegeGroup)
                        <details class="group" @if($classificationGroups->count() === 1) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $collegeGroup['college'] }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $collegeGroup['count'] }} {{ Str::plural('course', $collegeGroup['count']) }} / {{ $collegeGroup['open_sections'] }} open {{ Str::plural('module', $collegeGroup['open_sections']) }}</span>
                            </summary>
                            <div class="border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-gray-800 dark:bg-gray-950">
                                @foreach($collegeGroup['departments'] as $departmentGroup)
                                    <div class="flex flex-col gap-1 border-b border-gray-200 py-3 last:border-0 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $departmentGroup['department'] }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $departmentGroup['count'] }} {{ Str::plural('course', $departmentGroup['count']) }} / {{ $departmentGroup['open_sections'] }} open {{ Str::plural('module', $departmentGroup['open_sections']) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-gray-500">No courses match the selected filters.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="course-directory-title">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h3 id="course-directory-title" class="font-semibold text-gray-900 dark:text-gray-100">Course Catalog</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $courses->total() }} records</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950"><tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Course</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">College / Department</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Credits</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Sections</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($courses as $course)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/70">
                                    <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('course-records.show', $course) }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-indigo-300">{{ $course->code }}</a><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $course->name }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300"><p>{{ $course->department->college->name ?? 'No college' }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $course->department->name ?? 'No department' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $course->credits }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $course->open_sections_count }} open / {{ $course->sections_count }} total</td>
                                    <td class="whitespace-nowrap px-5 py-4"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $course->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ ucfirst($course->status) }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium">
                                        <a href="{{ route('course-records.show', $course) }}" class="text-blue-700 hover:underline">View</a>
                                        @if($abilities['update'])<a href="{{ route('course-records.edit', $course) }}" class="ml-3 text-gray-700 hover:underline">Edit</a>@endif
                                        @if($abilities['archive'])
                                            <form action="{{ route('course-records.destroy', $course) }}" method="POST" class="ml-3 inline-block">@csrf @method('DELETE')<button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Archive this course?')">Archive</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500">No courses found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($courses->hasPages())<div class="border-t border-gray-200 px-5 py-4">{{ $courses->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
