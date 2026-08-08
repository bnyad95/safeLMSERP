<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Teachers</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Staff directory, organization placement, status, and teaching workload.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($abilities['archive'])
                    <a href="{{ route('teachers.archived') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        Archived ({{ number_format($archivedCount) }})
                    </a>
                @endif
                @if($abilities['create'])
                    <a href="{{ route('teachers.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">Add Teacher</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section aria-label="Teacher totals" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['label' => 'Matching teachers', 'value' => $stats['total'], 'tone' => 'gray'],
                    ['label' => 'Active', 'value' => $stats['active'], 'tone' => 'emerald'],
                    ['label' => 'Inactive', 'value' => $stats['inactive'], 'tone' => 'amber'],
                    ['label' => 'Retired', 'value' => $stats['retired'], 'tone' => 'blue'],
                    ['label' => 'Active classes', 'value' => $stats['active_classes'], 'tone' => 'violet'],
                ] as $stat)
                    <div class="rounded-lg border p-4 shadow-sm
                        {{ $stat['tone'] === 'gray' ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' : '' }}
                        {{ $stat['tone'] === 'emerald' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20' : '' }}
                        {{ $stat['tone'] === 'amber' ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20' : '' }}
                        {{ $stat['tone'] === 'blue' ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20' : '' }}
                        {{ $stat['tone'] === 'violet' ? 'border-violet-200 bg-violet-50 dark:border-violet-800 dark:bg-violet-900/20' : '' }}">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stat['value']) }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('teachers.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="md:col-span-2">
                        <label for="teacher-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input id="teacher-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, staff ID, email, or title" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label for="teacher-university" class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label>
                        <select id="teacher-university" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All universities</option>
                            @foreach($universities as $university)
                                <option value="{{ $university->id }}" @selected((string) $filters['university_id'] === (string) $university->id)>{{ $university->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="teacher-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select id="teacher-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected((string) $filters['college_id'] === (string) $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="teacher-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select id="teacher-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="teacher-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="teacher-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All statuses</option>
                            @foreach(['Active', 'Inactive', 'Retired'] as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <a href="{{ route('teachers.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="teacher-classification-title">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 id="teacher-classification-title" class="font-semibold text-gray-900 dark:text-gray-100">Academic Classification</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Teacher population and active workload by college and department.</p>
                </div>
                <div class="grid gap-4 p-5 lg:grid-cols-2">
                    @forelse($classificationGroups as $collegeGroup)
                        <article class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $collegeGroup['college'] }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($collegeGroup['count']) }} teachers</p>
                                </div>
                                <span class="rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm dark:bg-gray-900 dark:text-gray-200">{{ number_format($collegeGroup['active_classes']) }} active classes</span>
                            </div>
                            <div class="mt-4 grid gap-2">
                                @foreach($collegeGroup['departments'] as $departmentGroup)
                                    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $departmentGroup['department'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($departmentGroup['active_classes']) }} active classes</p>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ number_format($departmentGroup['count']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <p class="col-span-full rounded-lg border border-dashed border-gray-300 px-5 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No teachers match the selected filters.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="teacher-directory-title">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 id="teacher-directory-title" class="font-semibold text-gray-900 dark:text-gray-100">Teacher Directory</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ number_format($teachers->total()) }} records</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Teacher</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Organization</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Workload</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($teachers as $teacher)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-5 py-4">
                                        <a href="{{ route('teachers.show', $teacher) }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">{{ $teacher->full_name }}</a>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $teacher->staff_id }}{{ $teacher->title ? ' - '.$teacher->title : '' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $teacher->email }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        <p>{{ $teacher->department->college->name ?? 'No college' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $teacher->department->name ?? 'No department' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $teacher->status === 'Active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : ($teacher->status === 'Retired' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200') }}">{{ $teacher->status }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ number_format($teacher->active_sections_count) }} active <span class="text-gray-400">/</span> {{ number_format($teacher->course_sections_count) }} total</td>
                                    <td class="px-5 py-4 text-right text-sm font-medium">
                                        <a href="{{ route('teachers.show', $teacher) }}" class="text-blue-700 hover:underline dark:text-blue-300">View</a>
                                        @if($abilities['update'])
                                            <a href="{{ route('teachers.edit', $teacher) }}" class="ml-3 text-gray-700 hover:underline dark:text-gray-200">Edit</a>
                                        @endif
                                        @if($abilities['archive'])
                                            <form action="{{ route('teachers.destroy', $teacher) }}" method="POST" class="ml-3 inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-700 hover:underline dark:text-red-300" onclick="return confirm('Archive this teacher?')">Archive</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No teachers found.</td></tr>
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
