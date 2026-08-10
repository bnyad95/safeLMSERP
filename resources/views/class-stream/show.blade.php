<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Classroom</h2>
            <p class="text-sm text-gray-600">{{ $section->course->name ?? 'Course' }} - Group {{ $section->section_code }}</p>
        </div>
    </x-slot>

    @php
        $tabs = [
            'stream' => 'Stream',
            'classwork' => 'Classwork',
            'people' => 'People',
            'grades' => 'Grades',
            'timetable' => 'Timetable',
        ];
        $tabUrl = fn (string $tab) => route('class-stream.show', ['courseSection' => $section, 'tab' => $tab]);
        $canManageAssessments = false;
        $canGrade = false;
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <a href="{{ route('student-portal') }}" class="mb-4 inline-flex text-sm font-semibold text-blue-700 hover:underline">Back to classes</a>

            <section class="overflow-hidden rounded-lg bg-blue-700 text-white shadow-sm">
                <div class="min-h-44 p-6 sm:p-8">
                    <p class="text-xs font-semibold uppercase text-blue-100">Student Classroom</p>
                    <h3 class="mt-3 text-2xl font-semibold sm:text-3xl">{{ $section->course->name ?? 'Course' }}</h3>
                    <p class="mt-2 text-blue-100">{{ $section->course->code ?? 'Course' }} - Group {{ $section->section_code }}</p>
                    <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-blue-50">
                        <span>{{ $section->grade_level ?? 'Class' }}</span>
                        <span>{{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }}</span>
                        <span>{{ $section->teacher->full_name ?? 'Teacher not assigned' }}</span>
                    </div>
                </div>
            </section>

            <nav class="mt-4 flex overflow-x-auto border-b border-gray-200 bg-white" aria-label="Student classroom tabs">
                @foreach($tabs as $tab => $label)
                    <a href="{{ $tabUrl($tab) }}" class="whitespace-nowrap border-b-2 px-5 py-4 text-sm font-semibold {{ $activeTab === $tab ? 'border-blue-700 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('class-messages.index', $section) }}" class="whitespace-nowrap border-b-2 border-transparent px-5 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900">Messages</a>
            </nav>

            <div class="py-6">
                @if($activeTab === 'stream')
                    <div class="mx-auto max-w-3xl">
                        @include('class-stream._feed', ['section' => $section, 'streamPosts' => $streamPosts, 'canCreatePost' => $canCreatePost])
                    </div>
                @elseif($activeTab === 'classwork')
                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,0.65fr)]">
                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h4 class="font-semibold text-gray-900">Assessments</h4>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @forelse($assessments as $item)
                                    @include('assessments.partials.item', ['item' => $item])
                                @empty
                                    <p class="px-5 py-8 text-center text-sm text-gray-500">No published assessments for this class.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="h-fit overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h4 class="font-semibold text-gray-900">Learning materials</h4>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @forelse($materials as $material)
                                    <div class="px-5 py-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-gray-900">{{ $material->title }}</p>
                                                @if($material->description)<p class="mt-1 text-sm text-gray-500">{{ $material->description }}</p>@endif
                                                <p class="mt-2 text-xs font-semibold uppercase text-gray-400">{{ $material->file_type }}</p>
                                            </div>
                                            @if($material->file_path)
                                                <a href="{{ route('materials.download', ['course' => $section->course_id, 'material' => $material, 'section_id' => $section->id]) }}" class="shrink-0 text-sm font-semibold text-blue-700 hover:underline">Download</a>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-5 py-8 text-center text-sm text-gray-500">No published materials for this class.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @elseif($activeTab === 'people')
                    <section class="mx-auto max-w-3xl overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h4 class="font-semibold text-gray-900">Teacher</h4></div>
                        <div class="flex items-center gap-4 px-5 py-5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 text-base font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ strtoupper(substr($section->teacher->full_name ?? 'T', 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900">{{ $section->teacher->full_name ?? 'Teacher not assigned' }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ $section->teacher->title ?? 'Course teacher' }}</p>
                            </div>
                        </div>
                    </section>
                @elseif($activeTab === 'grades')
                    <div class="mx-auto max-w-4xl space-y-6">
                        <section class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <p class="text-sm text-gray-500">Prefinal mark</p>
                                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $mark?->prefinal_mark ?? 'Not published' }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                <p class="text-sm text-gray-500">Final mark</p>
                                <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $mark?->final_mark ?? 'Not published' }}</p>
                            </div>
                        </section>

                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4"><h4 class="font-semibold text-gray-900">Assessment results</h4></div>
                            <div class="divide-y divide-gray-100">
                                @forelse($assessments as $assessment)
                                    @php $submission = $assessment->submissions->first(); @endphp
                                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $assessment->title }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ ucfirst($assessment->type) }} - {{ $assessment->weight_percent }}%</p>
                                        </div>
                                        <div class="shrink-0 text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ $submission?->score !== null ? $submission->score.' / '.$assessment->max_score : 'Not graded' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $submission ? ucfirst($submission->status) : 'Not submitted' }}</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="px-5 py-8 text-center text-sm text-gray-500">No assessment results for this class.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @elseif($activeTab === 'timetable')
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4"><h4 class="font-semibold text-gray-900">Class timetable</h4></div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Day</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Time</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Room</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($timetableEntries as $entry)
                                        <tr><td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $entry->day_of_week }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ $entry->classroom->name ?? $entry->room_number ?? 'Room not set' }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ ucfirst($entry->type) }}</td></tr>
                                    @empty
                                        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No timetable entries for this class.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
