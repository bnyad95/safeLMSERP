<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Student Records</h2>
                <p class="text-sm text-gray-600">Student directory, profiles, admissions, and academic placement.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($abilities['archive'])
                    <a href="{{ route('students.archived') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Archived ({{ $archivedCount }})</a>
                @endif
                @if($abilities['create'])
                    <a href="{{ route('students.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add Student</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <section aria-label="Student totals" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"><p class="text-sm text-gray-500">Matching students</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $stats['total'] }}</p></div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-sm text-emerald-800">Active</p><p class="mt-2 text-2xl font-semibold text-emerald-950">{{ $stats['active'] }}</p></div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="text-sm text-amber-800">Inactive</p><p class="mt-2 text-2xl font-semibold text-amber-950">{{ $stats['inactive'] }}</p></div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4"><p class="text-sm text-blue-800">Graduated</p><p class="mt-2 text-2xl font-semibold text-blue-950">{{ $stats['graduated'] }}</p></div>
            </section>

            <form method="GET" action="{{ route('students.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="md:col-span-2 xl:col-span-2">
                        <label for="student-search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input id="student-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, student ID, or email" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="student-college" class="block text-sm font-medium text-gray-700">College</label>
                        <select id="student-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)<option value="{{ $college->id }}" @selected((string) $filters['college_id'] === (string) $college->id)>{{ $college->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="student-department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="student-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}{{ $department->college ? ' / '.$department->college->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="student-grade" class="block text-sm font-medium text-gray-700">Stage</label>
                        <select id="student-grade" name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($gradeOptions as $grade)<option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="student-status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="student-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All statuses</option>
                            @foreach(['Active', 'Inactive', 'Graduated'] as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ route('students.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="classification-title">
                <div class="border-b border-gray-200 px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 id="classification-title" class="font-semibold text-gray-900">Academic classification</h3>
                            <p class="mt-1 text-sm text-gray-500">Matching students grouped by college, department, and current stage.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $stats['total'] }} records</span>
                    </div>
                </div>
                <div class="space-y-4 bg-gray-50 p-4 sm:p-5">
                    @forelse($classificationGroups as $collegeGroup)
                        <details class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" @if($classificationGroups->count() === 1) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 hover:bg-gray-50 sm:px-5">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ $collegeGroup['college'] }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">{{ $collegeGroup['departments']->count() }} departments</span>
                                </span>
                                <span class="shrink-0 rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $collegeGroup['count'] }} students</span>
                            </summary>
                            <div class="border-t border-gray-100 p-4 sm:p-5">
                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    @foreach($collegeGroup['departments'] as $departmentGroup)
                                        <div class="min-w-0 rounded-md border border-gray-200 bg-gray-50 p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $departmentGroup['department'] }}</p>
                                                    <p class="mt-1 text-xs text-gray-500">{{ $departmentGroup['count'] }} students</p>
                                                </div>
                                                <span class="shrink-0 rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ $departmentGroup['grades']->count() }} stages</span>
                                            </div>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                @foreach($departmentGroup['grades'] as $gradeGroup)
                                                    <span class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                                                        <span class="font-medium">{{ $gradeGroup['grade'] }}</span>
                                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-600">{{ $gradeGroup['count'] }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-gray-500">No students match the selected filters.</p>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="directory-title">
                <div class="border-b border-gray-200 px-5 py-4"><h3 id="directory-title" class="font-semibold text-gray-900">Student directory</h3><p class="mt-1 text-sm text-gray-500">{{ $students->total() }} records</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">College / Department</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Stage</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('students.show', $student) }}" class="text-sm font-semibold text-blue-700 hover:underline">{{ $student->full_name }}</a><p class="mt-1 text-xs text-gray-500">{{ $student->student_id }} - {{ $student->email }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600"><p>{{ $student->department->college->name ?? 'No college' }}</p><p class="mt-1 text-xs text-gray-500">{{ $student->department->name ?? 'No department' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{{ $student->grade_labels->isNotEmpty() ? $student->grade_labels->join(', ') : 'No stage' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4"><span class="rounded-md px-2 py-1 text-xs font-semibold {{ $student->status === 'Active' ? 'bg-emerald-50 text-emerald-700' : ($student->status === 'Graduated' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-800') }}">{{ $student->status }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium">
                                        <a href="{{ route('students.show', $student) }}" class="text-blue-700 hover:underline">View</a>
                                        @if($abilities['update'])<a href="{{ route('students.edit', $student) }}" class="ml-3 text-gray-700 hover:underline">Edit</a>@endif
                                        @if($abilities['transcript'])<a href="{{ route('transcripts.show', $student) }}" class="ml-3 text-violet-700 hover:underline">Transcript</a>@endif
                                        @if($abilities['archive'])
                                            <form action="{{ route('students.destroy', $student) }}" method="POST" class="ml-3 inline-block">@csrf @method('DELETE')<button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Archive this student?')">Archive</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">No students found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($students->hasPages())<div class="border-t border-gray-200 px-5 py-4">{{ $students->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
