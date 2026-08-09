<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Mark Submission Queue</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">Review submitted and under-review marks, approve or reject them, then publish approved marks to students.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($canManagePrefinalWindow)
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Pre-final Mark Entry</h3>
                            @if($prefinalWindowUniversity)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $prefinalWindowUniversity->name }} is currently
                                    <span class="font-semibold {{ $prefinalWindowUniversity->prefinal_marks_open ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">{{ $prefinalWindowUniversity->prefinal_marks_open ? 'enabled' : 'disabled' }}</span>
                                    @if($prefinalWindowUniversity->prefinal_marks_open && $prefinalWindowUniversity->prefinalMarksOpener)
                                        (enabled by {{ $prefinalWindowUniversity->prefinalMarksOpener->name }}{{ $prefinalWindowUniversity->prefinal_marks_opened_at ? ' on '.$prefinalWindowUniversity->prefinal_marks_opened_at->format('Y-m-d') : '' }})
                                    @endif
                                </p>
                            @else
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No university is associated with your account.</p>
                            @endif
                        </div>
                        @if($prefinalWindowUniversity)
                            <form method="POST" action="{{ route('marks.prefinal-window.toggle') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="open" value="{{ $prefinalWindowUniversity->prefinal_marks_open ? '0' : '1' }}">
                                <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold {{ $prefinalWindowUniversity->prefinal_marks_open ? 'border border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                    {{ $prefinalWindowUniversity->prefinal_marks_open ? 'Disable pre-final entry' : 'Enable pre-final entry' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @php
                $hasAdvancedMarkFilters = (bool) ($filters['college_id'] || $filters['department_id'] || $filters['stage'] !== '' || $filters['semester_id'] || $filters['course_id'] || $filters['teacher_id']);
            @endphp
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <form method="GET" action="{{ route('marks.submission-queue') }}" class="grid gap-4 lg:grid-cols-6" x-data="{ showMore: {{ $hasAdvancedMarkFilters ? 'true' : 'false' }} }">
                    <div class="lg:col-span-3">
                        <label for="mark-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input id="mark-search" name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="Student, ID, course, teacher">
                    </div>

                    <div class="lg:col-span-2">
                        <label for="mark-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Queue status</label>
                        <select id="mark-status" name="submission_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All queue statuses</option>
                            @foreach(['submitted' => 'Submitted', 'under_review' => 'Under Review', 'approved' => 'Approved'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['submission_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                        <a href="{{ route('marks.submission-queue') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                    </div>

                    <div class="lg:col-span-6">
                        <button type="button" x-on:click="showMore = ! showMore" class="flex items-center gap-1 text-sm font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                            <span x-text="showMore ? 'Fewer filters' : 'More filters'"></span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': showMore }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="showMore" x-cloak class="grid gap-4 border-t border-gray-100 pt-4 md:grid-cols-2 lg:col-span-6 lg:grid-cols-6 dark:border-gray-800">
                        <div>
                            <label for="mark-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                            <select id="mark-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All colleges</option>
                                @foreach($filterOptions['colleges'] as $college)
                                    <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="mark-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                            <select id="mark-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All departments</option>
                                @foreach($filterOptions['departments'] as $department)
                                    <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="mark-stage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                            <select id="mark-stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All stages</option>
                                @foreach($filterOptions['stages'] as $stage)
                                    <option value="{{ $stage['key'] }}" @selected((string) $filters['stage'] === (string) $stage['key'])>{{ $stage['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="mark-semester" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                            <select id="mark-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All semesters</option>
                                @foreach($filterOptions['semesters'] as $semester)
                                    <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="mark-course" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Course</label>
                            <select id="mark-course" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All courses</option>
                                @foreach($filterOptions['courses'] as $course)
                                    <option value="{{ $course->id }}" @selected($filters['course_id'] === $course->id)>{{ $course->code }} - {{ $course->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="mark-teacher" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teacher</label>
                            <select id="mark-teacher" name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                <option value="">All teachers</option>
                                @foreach($filterOptions['teachers'] as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <p class="text-sm text-blue-800 dark:text-blue-200">Submitted</p>
                    <p class="mt-2 text-2xl font-semibold text-blue-950 dark:text-blue-100">{{ $queueStats['pending'] }}</p>
                    <p class="mt-1 text-xs text-blue-800 dark:text-blue-300">Waiting for review</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <p class="text-sm text-amber-800 dark:text-amber-200">Under review</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-950 dark:text-amber-100">{{ $queueStats['under_review'] }}</p>
                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">Can be approved or rejected</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                    <p class="text-sm text-emerald-800 dark:text-emerald-200">Ready to publish</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950 dark:text-emerald-100">{{ $queueStats['approved'] }}</p>
                    <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-300">Approved but not visible</p>
                </div>
            </section>

            @if($canEnterFinalExam)
                <section class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-blue-950 dark:text-blue-100">Final Exam Entry</h3>
                            <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">Enter first-trial and eligible second-trial final exam marks from a scoped academic hierarchy.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-blue-900 shadow-sm dark:bg-gray-900 dark:text-blue-200">{{ $finalExamDraftCount }} waiting</span>
                            <a href="{{ route('marks.final-exam.index', request()->only(['q', 'academic_year', 'college_id', 'department_id', 'stage', 'semester_id', 'course_id'])) }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500">Open Final Exam Entry</a>
                        </div>
                    </div>
                </section>
            @endif

            <div x-data="{ queueView: 'pending' }">
                <div class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" x-on:click="queueView = 'pending'" x-bind:class="queueView === 'pending' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Pending Review ({{ $pendingSubmissions->total() }})</button>
                        <button type="button" x-on:click="queueView = 'approved'" x-bind:class="queueView === 'approved' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="rounded-md px-3 py-2 text-sm font-semibold">Approved - Ready to Publish ({{ $approvedMarks->total() }})</button>
                    </div>
                </div>

            <section x-show="queueView === 'pending'" class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Pending Review
                        <span class="ml-2 rounded-md bg-orange-100 px-2 py-0.5 text-xs text-orange-700 dark:bg-orange-900/30 dark:text-orange-200">{{ $pendingSubmissions->total() }}</span>
                    </h3>
                </div>

                <div x-data="{ selected: [] }">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    @if($canApprove || $canReject)
                                        <th class="w-10 px-4 py-3">
                                            <input type="checkbox" @change="selected = $event.target.checked ? {{ json_encode($pendingSubmissions->pluck('id')) }} : []" class="rounded">
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Course / Class</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Scope</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Mark</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Notes</th>
                                    @if($canApprove || $canReject)<th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($pendingSubmissions as $mark)
                                    @php
                                        $department = $mark->courseSection?->course?->department ?? $mark->course?->department ?? $mark->student?->department;
                                        $college = $department?->college;
                                        $section = $mark->courseSection;
                                        $course = $mark->course;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                        @if($canApprove || $canReject)
                                            <td class="px-4 py-4">
                                                <input type="checkbox" :value="{{ $mark->id }}" x-model="selected" class="rounded">
                                            </td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $mark->student->full_name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mark->student->student_id ?? 'No ID' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course?->code ?? 'No code' }} - {{ $course?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section ? 'Group '.$section->section_code.' / '.($section->semester?->name ?? 'Semester') : 'No class linked' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teacher: {{ $section?->teacher?->full_name ?? 'Not assigned' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $college?->name ?? 'No college' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $department?->name ?? 'No department' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section?->grade_level ?: 'Stage not specified' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ is_null($mark->final_mark) ? '-' : number_format((float) $mark->final_mark, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Prefinal {{ $mark->prefinal_mark ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">First trial {{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Second trial {{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 1) }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            @php
                                                $colors = ['submitted' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200', 'under_review' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-200'];
                                            @endphp
                                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $colors[$mark->submission_status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                                {{ ucfirst(str_replace('_', ' ', $mark->submission_status)) }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $mark->submitted_at?->diffForHumans() ?? 'No submit date' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $mark->reviewer_notes ?: '-' }}</td>
                                        @if($canApprove || $canReject)
                                            <td class="px-4 py-4">
                                                <div class="flex min-w-56 flex-col items-end gap-2">
                                                    @if($canApprove)
                                                        <form method="POST" action="{{ route('marks.approve') }}">
                                                            @csrf
                                                            <input type="hidden" name="mark_ids" value='@json([$mark->id])'>
                                                            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">Approve</button>
                                                        </form>
                                                    @endif
                                                    @if($canReject)
                                                        <form method="POST" action="{{ route('marks.reject') }}" class="flex gap-2">
                                                            @csrf
                                                            <input type="hidden" name="mark_ids" value='@json([$mark->id])'>
                                                            <input type="text" name="notes" required placeholder="Reason" class="w-32 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                                            <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Reject</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($canApprove || $canReject) ? 8 : 7 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No pending marks match the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canApprove || $canReject)
                        <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800" x-show="selected.length > 0">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="selected.length + ' selected'"></span>

                            @if($canApprove)
                                <form method="POST" action="{{ route('marks.approve') }}" @submit.prevent="submitWithIds($event, selected)" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="mark_ids" :value="JSON.stringify(selected)">
                                    <input type="text" name="notes" placeholder="Reviewer notes (optional)" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Approve selected</button>
                                </form>
                            @endif

                            @if($canReject)
                                <form method="POST" action="{{ route('marks.reject') }}" @submit.prevent="submitWithIds($event, selected)" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="mark_ids" :value="JSON.stringify(selected)">
                                    <input type="text" name="notes" placeholder="Rejection reason" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Reject selected</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                @if($pendingSubmissions->hasPages())
                    <div class="border-t px-4 py-3 dark:border-gray-800">{{ $pendingSubmissions->links() }}</div>
                @endif
            </section>

            <section x-show="queueView === 'approved'" x-cloak class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        Approved - Ready to Publish
                        <span class="ml-2 rounded-md bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-200">{{ $approvedMarks->total() }}</span>
                    </h3>
                </div>

                <div x-data="{ selectedApproved: [] }">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    @if($canPublish)
                                        <th class="w-10 px-4 py-3">
                                            <input type="checkbox" @change="selectedApproved = $event.target.checked ? {{ json_encode($approvedMarks->pluck('id')) }} : []" class="rounded">
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Course / Class</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Scope</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Final Mark</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Approved By</th>
                                    @if($canPublish)<th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Action</th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($approvedMarks as $mark)
                                    @php
                                        $department = $mark->courseSection?->course?->department ?? $mark->course?->department ?? $mark->student?->department;
                                        $college = $department?->college;
                                        $section = $mark->courseSection;
                                        $course = $mark->course;
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                        @if($canPublish)
                                            <td class="px-4 py-4">
                                                <input type="checkbox" :value="{{ $mark->id }}" x-model="selectedApproved" class="rounded">
                                            </td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $mark->student->full_name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mark->student->student_id ?? 'No ID' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $course?->code ?? 'No code' }} - {{ $course?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section ? 'Group '.$section->section_code.' / '.($section->semester?->name ?? 'Semester') : 'No class linked' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teacher: {{ $section?->teacher?->full_name ?? 'Not assigned' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $college?->name ?? 'No college' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $department?->name ?? 'No department' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section?->grade_level ?: 'Stage not specified' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ is_null($mark->final_mark) ? '-' : number_format((float) $mark->final_mark, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">First trial {{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Second trial {{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 1) }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $mark->reviewer->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mark->reviewer_notes ?: 'No notes' }}</p>
                                        </td>
                                        @if($canPublish)
                                            <td class="px-4 py-4 text-right">
                                                <form method="POST" action="{{ route('marks.publish') }}" onsubmit="return confirm('Publish this mark to the student?')">
                                                    @csrf
                                                    <input type="hidden" name="mark_ids" value='@json([$mark->id])'>
                                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Publish</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canPublish ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No approved marks are ready to publish for the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canPublish)
                        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 dark:border-gray-800" x-show="selectedApproved.length > 0">
                            <span class="text-sm text-gray-600 dark:text-gray-400" x-text="selectedApproved.length + ' selected'"></span>
                            <form method="POST" action="{{ route('marks.publish') }}" @submit.prevent="if (confirm('Publish selected marks to students?')) submitWithIds($event, selectedApproved)">
                                @csrf
                                <input type="hidden" name="mark_ids" :value="JSON.stringify(selectedApproved)">
                                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    Publish selected
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($approvedMarks->hasPages())
                    <div class="border-t px-4 py-3 dark:border-gray-800">{{ $approvedMarks->links() }}</div>
                @endif
            </section>
            </div>
        </div>
    </div>

    <script>
        function submitWithIds(event, ids) {
            const form = event.target;
            const input = form.querySelector('[name="mark_ids"]');
            input.value = JSON.stringify(ids);
            form.submit();
        }
    </script>
</x-app-layout>
