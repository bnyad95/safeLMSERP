<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ __('Student Portal') }}</h2>
                <p class="text-sm text-gray-600">{{ __('Your personal academic summary, results, and curriculum references.') }}</p>
            </div>
            <x-dashboard-clock />
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (!$student)
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('No student profile is linked to your account yet. Ask administration to connect your login to a student record.') }}
                </div>
            @else

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @foreach($stats as $stat)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
                            <h3 class="mt-2 text-xl font-semibold text-gray-900">{{ $stat['value'] }}</h3>
                            @if(!empty($stat['detail']))
                                <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Today') }}</h3>
                            <p class="text-sm text-gray-500">{{ now('Asia/Baghdad')->format('l, M j') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-5 lg:grid-cols-3">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('Classes') }}</h4>
                            <div class="mt-3 space-y-2">
                                @forelse($todayClasses as $entry)
                                    <div class="rounded-md border border-gray-100 bg-gray-50 p-3 text-sm">
                                        <p class="font-medium text-gray-900">{{ $entry->course->name ?? __('Course') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ substr((string) $entry->start_time, 0, 5) }} - {{ substr((string) $entry->end_time, 0, 5) }} / {{ $entry->room_number ?: __('No room') }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('No classes scheduled today.') }}</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('Due Today') }}</h4>
                            <div class="mt-3 space-y-2">
                                @forelse($dueTodayAssessments as $assessment)
                                    <div class="rounded-md border border-amber-100 bg-amber-50 p-3 text-sm">
                                        <p class="font-medium text-gray-900">{{ $assessment->title }}</p>
                                        <p class="mt-1 text-xs text-amber-700">{{ __(':course / due :time', ['course' => $assessment->courseSection->course->name ?? __('Course'), 'time' => $assessment->due_at->timezone('Asia/Baghdad')->format('H:i')]) }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('No assignments due today.') }}</p>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('Unread Messages') }}</h4>
                            <div class="mt-3 space-y-2">
                                @forelse($unreadMessages as $message)
                                    <div class="rounded-md border border-blue-100 bg-blue-50 p-3 text-sm">
                                        <p class="font-medium text-gray-900">{{ $message->sender->name ?? __('Teacher') }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-blue-700">{{ $message->courseSection->course->name ?? __('Class') }} / {{ $message->body }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">{{ __('No unread class messages.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('My Classes') }}</h3>
                        <span class="text-sm text-gray-500">{{ __(':count enrolled', ['count' => $enrolledSections->count()]) }}</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($enrolledSections as $section)
                            <a href="{{ route('class-stream.show', $section) }}" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                <div class="min-h-32 bg-blue-700 p-5 text-white">
                                    <p class="text-base font-semibold">{{ $section->course->name ?? __('Course') }}</p>
                                    <p class="mt-1 text-sm text-blue-100">{{ __(':code - Group :section', ['code' => $section->course->code ?? __('Course'), 'section' => $section->section_code]) }}</p>
                                    <p class="mt-4 truncate text-xs text-blue-100">{{ $section->teacher->full_name ?? __('No teacher assigned') }}</p>
                                </div>
                                <div class="border-b border-gray-100 px-4 py-3">
                                    @if($section->next_assessment)
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $section->next_assessment->title }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Due :date', ['date' => $section->next_assessment->due_at->timezone('Asia/Baghdad')->format('M j, H:i')]) }}</p>
                                    @else
                                        <p class="text-sm text-gray-500">{{ __('No upcoming deadline') }}</p>
                                    @endif
                                </div>
                                <div class="grid grid-cols-3 divide-x divide-gray-100 px-4 py-3 text-center">
                                    <div><p class="text-xs text-gray-500">{{ __('Posts') }}</p><p class="mt-1 text-sm font-semibold text-gray-900">{{ $section->stream_posts_count }}</p></div>
                                    <div><p class="text-xs text-gray-500">{{ __('Open work') }}</p><p class="mt-1 text-sm font-semibold text-gray-900">{{ $section->open_work_count }}</p></div>
                                    <div><p class="text-xs text-gray-500">{{ __('Unread') }}</p><p class="mt-1 text-sm font-semibold {{ $section->unread_messages_count ? 'text-blue-700' : 'text-gray-900' }}">{{ $section->unread_messages_count }}</p></div>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
                                    <span>{{ $section->semester->name ?? __('Semester') }} {{ $section->semester->academic_year ?? '' }}</span>
                                    <span>{{ $section->today_classes_count ? __(':count today', ['count' => $section->today_classes_count]) : __('No class today') }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-500">{{ __('You are not enrolled in a class yet.') }}</div>
                        @endforelse
                    </div>
                </section>

                <div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Latest Results') }}</h3>
                            <a href="{{ route('transcripts.show', $student->id) }}"
                               class="inline-flex justify-center rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200 dark:hover:bg-blue-900/50">{{ __('View Transcript') }}</a>
                        </div>
                        <div class="mt-4 divide-y divide-gray-100">
                            @forelse ($marks as $mark)
                                <div class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $mark->course->name ?? __('Course') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $mark->course->code ?? __('N/A') }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-900">{{ __('Final: :mark', ['mark' => $mark->final_mark]) }}</span>
                                        <span class="rounded-md bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $mark->status === 'Published' ? __('Published') : $mark->status }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No published marks available yet.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
