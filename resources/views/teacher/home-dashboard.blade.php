<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Teacher Dashboard</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $teacher?->full_name ?? auth()->user()->name }}{{ $teacher?->department ? ' - '.$teacher->department->name : '' }}</p>
            </div>
            <x-dashboard-clock :date="$dashboardDate" />
        </div>
    </x-slot>

    @php
        $assessmentUrl = fn ($assessment) => route('assessments.index', [
            'section_id' => $assessment->course_section_id,
            'assessment_id' => $assessment->id,
        ]).'#assessment-'.$assessment->id;
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Teaching summary">
                @foreach([
                    ['label' => 'Assigned classes', 'value' => $stats['classes'], 'detail' => 'Active classes', 'tone' => 'border-blue-200 bg-blue-50 text-blue-800'],
                    ['label' => 'My students', 'value' => $stats['students'], 'detail' => 'Unique enrollments', 'tone' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
                    ['label' => 'Awaiting grading', 'value' => $stats['pending_submissions'], 'detail' => 'Submitted work', 'tone' => 'border-amber-200 bg-amber-50 text-amber-800'],
                    ['label' => 'Unread messages', 'value' => $stats['unread_messages'], 'detail' => 'Student messages', 'tone' => 'border-rose-200 bg-rose-50 text-rose-800'],
                ] as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <p class="text-3xl font-semibold text-gray-900">{{ number_format($stat['value']) }}</p>
                            <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $stat['tone'] }}">{{ $stat['detail'] }}</span>
                        </div>
                    </div>
                @endforeach
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.75fr)]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">Today's timetable</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ $dashboardDate->format('l') }}</p>
                            </div>
                            <a href="{{ route('timetables.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">View week</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($todayTimetable as $entry)
                                <a href="{{ route('teacher-dashboard', ['section_id' => $entry->course_section_id, 'tab' => 'timetable']) }}" class="grid gap-2 px-5 py-4 hover:bg-gray-50 sm:grid-cols-[120px_minmax(0,1fr)_150px] sm:items-center">
                                    <p class="text-sm font-semibold text-gray-900">{{ substr($entry->start_time, 0, 5) }} - {{ substr($entry->end_time, 0, 5) }}</p>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $entry->course->name ?? 'Course' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Group {{ $entry->courseSection->section_code ?? '-' }} - {{ ucfirst($entry->type) }}</p>
                                    </div>
                                    <p class="text-sm text-gray-600 sm:text-right">{{ $entry->classroom->name ?? $entry->room_number ?? 'Room not set' }}</p>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">No classes are scheduled for today.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Upcoming assessments</h3></div>
                        <div class="divide-y divide-gray-100">
                            @forelse($upcomingAssessments as $assessment)
                                <a href="{{ $assessmentUrl($assessment) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $assessment->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $assessment->courseSection->course->name ?? 'Course' }} - Group {{ $assessment->courseSection->section_code ?? '-' }}</p>
                                    </div>
                                    <div class="shrink-0 text-sm sm:text-right">
                                        <p class="font-semibold text-gray-900">Due {{ $assessment->due_at->format('M j, H:i') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $assessment->submissions_count }} submissions</p>
                                    </div>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">No published assessments are due soon.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Awaiting grading</h3></div>
                        <div class="divide-y divide-gray-100">
                            @forelse($pendingSubmissions as $submission)
                                <a href="{{ $assessmentUrl($submission->assessmentItem) }}" class="block px-5 py-4 hover:bg-gray-50">
                                    <p class="text-sm font-semibold text-gray-900">{{ $submission->student->full_name ?? 'Student' }}</p>
                                    <p class="mt-1 truncate text-xs text-gray-500">{{ $submission->assessmentItem->title }} - {{ $submission->assessmentItem->courseSection->course->code ?? 'Course' }}</p>
                                    <p class="mt-2 text-xs font-medium text-amber-700">Submitted {{ $submission->submitted_at?->diffForHumans() ?? 'recently' }}</p>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">The grading queue is clear.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Unread class messages</h3></div>
                        <div class="divide-y divide-gray-100">
                            @forelse($unreadMessages as $message)
                                <a href="{{ route('class-messages.index', ['courseSection' => $message->course_section_id, 'recipient_id' => $message->sender_id]) }}" class="block px-5 py-4 hover:bg-gray-50">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $message->sender->name ?? 'Student' }}</p>
                                        <span class="shrink-0 text-xs text-gray-500">{{ $message->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs text-gray-500">{{ $message->courseSection->course->code ?? 'Class' }} - {{ $message->body ?: 'File attachment' }}</p>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">No unread class messages.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Attendance alerts</h3></div>
                        <div class="divide-y divide-gray-100">
                            @forelse($attendanceRisks as $risk)
                                <a href="{{ route('teacher-dashboard', ['section_id' => $risk['section_id'], 'tab' => 'analytics']) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $risk['student']->full_name }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $risk['absences'] }} absences in the last 30 days</p>
                                    </div>
                                    <span class="shrink-0 rounded-md border px-2 py-1 text-xs font-semibold {{ $risk['rate'] < 75 ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ number_format($risk['rate'], 1) }}%</span>
                                </a>
                            @empty
                                <p class="px-5 py-8 text-center text-sm text-gray-500">No attendance risks in the last 30 days.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
