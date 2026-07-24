<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Mark Submission Queue</h2>
            <p class="text-sm text-gray-600">Review submitted and under-review marks, approve or reject them, then publish approved marks to students.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('marks.submission-queue') }}" class="grid gap-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label for="mark-search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input id="mark-search" name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Student, ID, course, teacher">
                    </div>

                    <div>
                        <label for="mark-college" class="block text-sm font-medium text-gray-700">College</label>
                        <select id="mark-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($filterOptions['colleges'] as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="mark-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($filterOptions['departments'] as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-stage" class="block text-sm font-medium text-gray-700">Stage</label>
                        <select id="mark-stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($filterOptions['stages'] as $stage)
                                <option value="{{ $stage['key'] }}" @selected((string) $filters['stage'] === (string) $stage['key'])>{{ $stage['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-semester" class="block text-sm font-medium text-gray-700">Semester</label>
                        <select id="mark-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All semesters</option>
                            @foreach($filterOptions['semesters'] as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-course" class="block text-sm font-medium text-gray-700">Course</label>
                        <select id="mark-course" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All courses</option>
                            @foreach($filterOptions['courses'] as $course)
                                <option value="{{ $course->id }}" @selected($filters['course_id'] === $course->id)>{{ $course->code }} - {{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-teacher" class="block text-sm font-medium text-gray-700">Teacher</label>
                        <select id="mark-teacher" name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All teachers</option>
                            @foreach($filterOptions['teachers'] as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="mark-status" class="block text-sm font-medium text-gray-700">Queue status</label>
                        <select id="mark-status" name="submission_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All queue statuses</option>
                            @foreach(['submitted' => 'Submitted', 'under_review' => 'Under Review', 'approved' => 'Approved'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['submission_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-wrap items-end gap-3 lg:col-span-3">
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                        <a href="{{ route('marks.submission-queue') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    </div>
                </form>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-800">Submitted</p>
                    <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $queueStats['pending'] }}</p>
                    <p class="mt-1 text-xs text-blue-800">Waiting for review</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm text-amber-800">Under review</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-950">{{ $queueStats['under_review'] }}</p>
                    <p class="mt-1 text-xs text-amber-800">Can be approved or rejected</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-800">Ready to publish</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ $queueStats['approved'] }}</p>
                    <p class="mt-1 text-xs text-emerald-800">Approved but not visible</p>
                </div>
            </section>

            @if($canEnterFinalExam)
                <section class="rounded-lg border border-blue-200 bg-blue-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-blue-950">Final Exam Entry</h3>
                            <p class="mt-1 text-sm text-blue-800">Enter first-trial and eligible second-trial final exam marks from a scoped academic hierarchy.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-blue-900 shadow-sm">{{ $finalExamDraftCount }} waiting</span>
                            <a href="{{ route('marks.final-exam.index', request()->only(['q', 'academic_year', 'college_id', 'department_id', 'stage', 'semester_id', 'course_id'])) }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Open Final Exam Entry</a>
                        </div>
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">
                        Pending Review
                        <span class="ml-2 rounded-md bg-orange-100 px-2 py-0.5 text-xs text-orange-700">{{ $pendingSubmissions->total() }}</span>
                    </h3>
                </div>

                <div x-data="{ selected: [] }">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    @if($canApprove || $canReject)
                                        <th class="w-10 px-4 py-3">
                                            <input type="checkbox" @change="selected = $event.target.checked ? {{ json_encode($pendingSubmissions->pluck('id')) }} : []" class="rounded">
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Course / Class</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Scope</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mark</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                                    @if($canApprove || $canReject)<th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($pendingSubmissions as $mark)
                                    @php
                                        $department = $mark->courseSection?->course?->department ?? $mark->course?->department ?? $mark->student?->department;
                                        $college = $department?->college;
                                        $section = $mark->courseSection;
                                        $course = $mark->course;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        @if($canApprove || $canReject)
                                            <td class="px-4 py-4">
                                                <input type="checkbox" :value="{{ $mark->id }}" x-model="selected" class="rounded">
                                            </td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900">{{ $mark->student->full_name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $mark->student->student_id ?? 'No ID' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-medium text-gray-900">{{ $course?->code ?? 'No code' }} - {{ $course?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $section ? 'Group '.$section->section_code.' / '.($section->semester?->name ?? 'Semester') : 'No class linked' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Teacher: {{ $section?->teacher?->full_name ?? 'Not assigned' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700">{{ $college?->name ?? 'No college' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $department?->name ?? 'No department' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $section?->grade_level ?: 'Stage not specified' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900">{{ is_null($mark->final_mark) ? '-' : number_format((float) $mark->final_mark, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Prefinal {{ $mark->prefinal_mark ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">First trial {{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Second trial {{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 1) }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            @php
                                                $colors = ['submitted' => 'bg-blue-100 text-blue-700', 'under_review' => 'bg-yellow-100 text-yellow-700'];
                                            @endphp
                                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $colors[$mark->submission_status] ?? 'bg-gray-100 text-gray-700' }}">
                                                {{ ucfirst(str_replace('_', ' ', $mark->submission_status)) }}
                                            </span>
                                            <p class="mt-2 text-xs text-gray-500">{{ $mark->submitted_at?->diffForHumans() ?? 'No submit date' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-600">{{ $mark->reviewer_notes ?: '-' }}</td>
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
                                                            <input type="text" name="notes" required placeholder="Reason" class="w-32 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                            <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Reject</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($canApprove || $canReject) ? 8 : 7 }}" class="px-4 py-10 text-center text-sm text-gray-500">No pending marks match the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canApprove || $canReject)
                        <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 px-4 py-3" x-show="selected.length > 0">
                            <span class="text-sm text-gray-600" x-text="selected.length + ' selected'"></span>

                            @if($canApprove)
                                <form method="POST" action="{{ route('marks.approve') }}" @submit.prevent="submitWithIds($event, selected)" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="mark_ids" :value="JSON.stringify(selected)">
                                    <input type="text" name="notes" placeholder="Reviewer notes (optional)" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Approve selected</button>
                                </form>
                            @endif

                            @if($canReject)
                                <form method="POST" action="{{ route('marks.reject') }}" @submit.prevent="submitWithIds($event, selected)" class="flex gap-2">
                                    @csrf
                                    <input type="hidden" name="mark_ids" :value="JSON.stringify(selected)">
                                    <input type="text" name="notes" placeholder="Rejection reason" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Reject selected</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                @if($pendingSubmissions->hasPages())
                    <div class="border-t px-4 py-3">{{ $pendingSubmissions->links() }}</div>
                @endif
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">
                        Approved - Ready to Publish
                        <span class="ml-2 rounded-md bg-green-100 px-2 py-0.5 text-xs text-green-700">{{ $approvedMarks->total() }}</span>
                    </h3>
                </div>

                <div x-data="{ selectedApproved: [] }">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    @if($canPublish)
                                        <th class="w-10 px-4 py-3">
                                            <input type="checkbox" @change="selectedApproved = $event.target.checked ? {{ json_encode($approvedMarks->pluck('id')) }} : []" class="rounded">
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Course / Class</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Scope</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Final Mark</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Approved By</th>
                                    @if($canPublish)<th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($approvedMarks as $mark)
                                    @php
                                        $department = $mark->courseSection?->course?->department ?? $mark->course?->department ?? $mark->student?->department;
                                        $college = $department?->college;
                                        $section = $mark->courseSection;
                                        $course = $mark->course;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        @if($canPublish)
                                            <td class="px-4 py-4">
                                                <input type="checkbox" :value="{{ $mark->id }}" x-model="selectedApproved" class="rounded">
                                            </td>
                                        @endif
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900">{{ $mark->student->full_name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $mark->student->student_id ?? 'No ID' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-medium text-gray-900">{{ $course?->code ?? 'No code' }} - {{ $course?->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $section ? 'Group '.$section->section_code.' / '.($section->semester?->name ?? 'Semester') : 'No class linked' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Teacher: {{ $section?->teacher?->full_name ?? 'Not assigned' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700">{{ $college?->name ?? 'No college' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $department?->name ?? 'No department' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $section?->grade_level ?: 'Stage not specified' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-semibold text-gray-900">{{ is_null($mark->final_mark) ? '-' : number_format((float) $mark->final_mark, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500">First trial {{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 1) }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Second trial {{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 1) }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-700">{{ $mark->reviewer->name ?? '-' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $mark->reviewer_notes ?: 'No notes' }}</p>
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
                                        <td colspan="{{ $canPublish ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-gray-500">No approved marks are ready to publish for the current filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canPublish)
                        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3" x-show="selectedApproved.length > 0">
                            <span class="text-sm text-gray-600" x-text="selectedApproved.length + ' selected'"></span>
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
                    <div class="border-t px-4 py-3">{{ $approvedMarks->links() }}</div>
                @endif
            </section>
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
