<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ $section->course->code }} - {{ $section->course->name }}</h2>
                <p class="text-sm text-gray-600">{{ $section->semester->name }} {{ $section->semester->academic_year }} / Group {{ $section->section_code }} / {{ $section->grade_level ?: 'No stage' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('enrollments.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back</a>
                <a href="{{ route('course-sections.export-roster', $section) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Export Roster</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-5">
                @foreach([
                    ['label' => 'Enrolled', 'value' => $section->enrolled_count],
                    ['label' => 'Capacity', 'value' => $section->capacity],
                    ['label' => 'Waitlist', 'value' => $section->waitlisted_count],
                    ['label' => 'Assessments', 'value' => $section->assessment_items_count],
                    ['label' => 'Timetable', 'value' => $section->timetables_count],
                ] as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @if($abilities['manage'])
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h3 class="font-semibold text-gray-900">Add Students</h3>
                            </div>
                            <div class="space-y-5 p-5">
                                <form method="GET" action="{{ route('course-sections.show', $section) }}" class="flex flex-col gap-3 md:flex-row">
                                    <input name="student_q" value="{{ $studentSearch }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Search name, student ID, or email">
                                    <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Search</button>
                                </form>

                                @if($studentSearch !== '')
                                    <form method="POST" action="{{ route('course-sections.bulk-enroll', $section) }}" class="space-y-4">
                                        @csrf
                                        <input type="hidden" name="enrolled_at" value="{{ now()->toDateString() }}">
                                        <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200">
                                            @forelse($studentCandidates as $student)
                                                <label class="flex items-center gap-3 px-4 py-3">
                                                    <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span>
                                                        <span class="block text-sm font-medium text-gray-900">{{ $student->full_name }}</span>
                                                        <span class="block text-xs text-gray-500">{{ $student->student_id }} / {{ $student->email }} / {{ $student->department->name ?? 'No department' }}</span>
                                                    </span>
                                                </label>
                                            @empty
                                                <div class="px-4 py-6 text-center text-sm text-gray-500">No active students found.</div>
                                            @endforelse
                                        </div>
                                        @if($studentCandidates->isNotEmpty())
                                            <div class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
                                                <input name="notes" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Notes">
                                                <button name="action" value="waitlist" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Waitlist</button>
                                                <button name="action" value="enroll" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Enroll</button>
                                            </div>
                                        @endif
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('course-sections.import-roster', $section) }}" enctype="multipart/form-data" class="grid gap-3 border-t border-gray-100 pt-5 md:grid-cols-[1fr_auto_auto]">
                                    @csrf
                                    <input type="hidden" name="enrolled_at" value="{{ now()->toDateString() }}">
                                    <input type="file" name="csv_file" accept=".csv,.txt" class="block w-full rounded-md border border-gray-300 text-sm file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700" required>
                                    <select name="action" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="enroll">Enroll</option>
                                        <option value="waitlist">Waitlist</option>
                                    </select>
                                    <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Import CSV</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="font-semibold text-gray-900">Roster</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Enrolled</th>
                                        @if($abilities['manage'])
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Transfer</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Remove</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($activeEnrollments as $enrollment)
                                        <tr>
                                            <td class="px-5 py-4">
                                                <div class="font-medium text-gray-900">{{ $enrollment->student->full_name }}</div>
                                                <div class="text-sm text-gray-500">{{ $enrollment->student->student_id }} / {{ $enrollment->student->email }}</div>
                                            </td>
                                            <td class="px-5 py-4 text-sm text-gray-600">{{ $enrollment->enrolled_at?->format('Y-m-d') }}</td>
                                            @if($abilities['manage'])
                                                <td class="px-5 py-4">
                                                    @if($transferTargets->isNotEmpty())
                                                        <form method="POST" action="{{ route('enrollments.transfer', $enrollment) }}" class="flex min-w-72 gap-2">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select name="target_section_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                                                <option value="">Target group</option>
                                                                @foreach($transferTargets as $target)
                                                                    <option value="{{ $target->id }}">{{ $target->section_code }} / {{ $target->enrolled_count }} of {{ $target->capacity }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="hidden" name="reason" value="Admin transfer">
                                                            <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Move</button>
                                                        </form>
                                                    @else
                                                        <span class="text-sm text-gray-400">No target</span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-4">
                                                    <form method="POST" action="{{ route('enrollments.drop', $enrollment) }}" class="flex min-w-72 gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input name="drop_reason" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Reason" required>
                                                        <button class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Remove from Module</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $abilities['manage'] ? 4 : 2 }}" class="px-5 py-8 text-center text-sm text-gray-500">No enrolled students yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 px-5 py-4">{{ $activeEnrollments->links() }}</div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="font-semibold text-gray-900">Waitlist</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($waitlist as $enrollment)
                                <div class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $enrollment->student->full_name }}</div>
                                        <div class="text-sm text-gray-500">{{ $enrollment->student->student_id }} / {{ $enrollment->waitlisted_at?->format('Y-m-d H:i') }}</div>
                                    </div>
                                    @if($abilities['manage'])
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('enrollments.promote', $enrollment) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Promote</button>
                                            </form>
                                            <form method="POST" action="{{ route('enrollments.drop', $enrollment) }}" class="flex gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="drop_reason" value="Removed from waitlist">
                                                <button class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Remove</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500">No students on the waitlist.</div>
                            @endforelse
                        </div>
                        <div class="border-t border-gray-100 px-5 py-4">{{ $waitlist->links() }}</div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="font-semibold text-gray-900">Module Details</h3>
                        </div>
                        <div class="space-y-4 p-5 text-sm">
                            <div>
                                <div class="text-gray-500">College</div>
                                <div class="font-medium text-gray-900">{{ $section->course->department->college->name ?? 'No college' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Department</div>
                                <div class="font-medium text-gray-900">{{ $section->course->department->name ?? 'No department' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Teacher</div>
                                <div class="font-medium text-gray-900">{{ $section->teacher->full_name ?? 'Unassigned' }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Status</div>
                                <div class="font-medium text-gray-900">{{ ucfirst($section->status) }}</div>
                            </div>
                            @if($section->notes)
                                <div>
                                    <div class="text-gray-500">Notes</div>
                                    <div class="font-medium text-gray-900">{{ $section->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($section->timetables->isNotEmpty())
                        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h3 class="font-semibold text-gray-900">Timetable</h3>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach($section->timetables as $entry)
                                    <div class="px-5 py-4 text-sm">
                                        <div class="font-medium text-gray-900">{{ $entry->day_of_week }} / {{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</div>
                                        <div class="text-gray-500">{{ ucfirst($entry->type) }} / {{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($abilities['manage'])
                        <form method="POST" action="{{ route('course-sections.update', $section) }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            @csrf
                            @method('PATCH')
                            <h3 class="font-semibold text-gray-900">Settings</h3>
                            <div class="mt-4 space-y-4">
                                @if($abilities['assign_teacher'])
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Teacher ID</label>
                                        <input type="number" name="teacher_id" value="{{ old('teacher_id', $section->teacher_id) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                @endif
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Stage</label>
                                    <input name="grade_level" value="{{ old('grade_level', $section->grade_level) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Capacity</label>
                                        <input type="number" min="1" max="500" name="capacity" value="{{ old('capacity', $section->capacity) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                            @foreach(['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('status', $section->status) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $section->notes) }}</textarea>
                                </div>
                                <button class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Save Settings</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('course-sections.destroy', $section) }}" class="rounded-lg border border-red-200 bg-red-50 p-5">
                            @csrf
                            @method('DELETE')
                            <h3 class="font-semibold text-red-900">Archive Module</h3>
                            <p class="mt-1 text-sm text-red-700">Only closed modules with no active roster or waitlist can be archived.</p>
                            <button class="mt-4 rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Archive</button>
                        </form>
                    @endif

                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="font-semibold text-gray-900">History</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($history as $event)
                                <div class="px-5 py-4 text-sm">
                                    <div class="font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($event->action)) }}</div>
                                    <div class="text-gray-500">{{ $event->student->full_name ?? 'Student' }} / {{ $event->actor->name ?? 'System' }} / {{ $event->occurred_at?->format('Y-m-d H:i') }}</div>
                                    @if($event->notes)
                                        <div class="mt-1 text-gray-600">{{ $event->notes }}</div>
                                    @endif
                                </div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500">No enrollment history yet.</div>
                            @endforelse
                        </div>
                        <div class="border-t border-gray-100 px-5 py-4">{{ $history->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
