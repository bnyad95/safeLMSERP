<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Archived Classes</h2>
            <p class="text-sm text-gray-600">
                {{ $mode === 'teacher' ? 'Closed classes from previous academic years remain available here for review.' : 'Your completed and closed classes remain available here after academic year closing.' }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($mode === 'teacher')
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Archived Classes</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $sections->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Students</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($sections->sum('roster_count')) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Assessments</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($sections->sum('assessment_items_count')) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Materials</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($sections->sum('materials_count')) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Marks</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($sections->sum('marks_count')) }}</p>
                    </div>
                </section>

                <section class="grid gap-4 lg:grid-cols-2">
                    @forelse($sections as $section)
                        <article class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $section->semester->academic_year ?? 'Archived year' }}</p>
                                        <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ $section->course->name ?? 'Archived course' }}</h3>
                                        <p class="mt-1 text-sm text-gray-500">{{ $section->course->code ?? 'Course' }} / Group {{ $section->section_code }}</p>
                                    </div>
                                    <span class="rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Read only</span>
                                </div>
                                <p class="mt-3 text-sm text-gray-600">
                                    {{ $section->course->department->college->name ?? 'No college' }} / {{ $section->course->department->name ?? 'No department' }}
                                </p>
                            </div>
                            <dl class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-4 sm:divide-y-0">
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Stage</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $section->grade_level ?? 'N/A' }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Roster</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $section->roster_count }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Posts</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $section->stream_posts_count }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Status</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $section->trashed() ? 'Archived' : ucfirst($section->status) }}</dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 lg:col-span-2">
                            No archived classes yet. Closed academic years will appear here.
                        </div>
                    @endforelse
                </section>
            @else
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Archived Classes</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $enrollments->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Published Results</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $marks->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">Completed</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $enrollments->where('status', 'completed')->count() }}</p>
                    </div>
                </section>

                <section class="grid gap-4 lg:grid-cols-2">
                    @forelse($enrollments as $enrollment)
                        @php
                            $section = $enrollment->courseSection;
                            $mark = $marks->get($enrollment->course_section_id);
                        @endphp
                        <article class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $section->semester->academic_year ?? 'Archived year' }}</p>
                                        <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ $section->course->name ?? 'Archived course' }}</h3>
                                        <p class="mt-1 text-sm text-gray-500">{{ $section->course->code ?? 'Course' }} / Group {{ $section->section_code ?? 'N/A' }}</p>
                                    </div>
                                    <span class="rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Read only</span>
                                </div>
                                <p class="mt-3 text-sm text-gray-600">Teacher: {{ $section->teacher->full_name ?? 'Not assigned' }}</p>
                            </div>
                            <dl class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-4 sm:divide-y-0">
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Stage</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $section->grade_level ?? 'N/A' }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Enrollment</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ ucfirst($enrollment->status) }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Final Mark</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $mark ? number_format((float) $mark->final_mark, 2) : 'N/A' }}</dd>
                                </div>
                                <div class="p-4">
                                    <dt class="text-xs font-semibold uppercase text-gray-500">Result</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $mark ? ucfirst($mark->status) : 'Not published' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 lg:col-span-2">
                            No archived classes yet. Completed classes will appear here after academic year closing.
                        </div>
                    @endforelse
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
