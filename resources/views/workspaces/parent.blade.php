<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">{{ __('Family Access') }}</p>
            <h2 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('Parent Portal') }}</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ __('View linked student progress, attendance, and tuition status.') }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <div class="space-y-6">
                @forelse($childSummaries as $summary)
                    @php($student = $summary['student'])
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">{{ $student->full_name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $student->student_id }} / {{ $student->department?->name ?? __('No department') }}</p>
                                </div>
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ __($student->status) }}</span>
                            </div>
                        </div>
                        <div class="grid gap-4 p-5 md:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-medium uppercase text-gray-500">{{ __('Classes') }}</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['classes'] }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-medium uppercase text-gray-500">{{ __('Average') }}</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['average'] }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-medium uppercase text-gray-500">{{ __('Attendance') }}</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['attendance'] }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-medium uppercase text-gray-500">{{ __('Finance') }}</p>
                                <p class="mt-2 text-xl font-semibold text-gray-900">{{ $summary['balance'] }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $summary['finance_detail'] }}</p>
                            </div>
                        </div>
                        <div class="grid gap-6 border-t border-gray-100 p-5 lg:grid-cols-2">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ __('Recent Results') }}</h4>
                                <div class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-100">
                                    @forelse($summary['recent_marks'] as $mark)
                                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-900">{{ $mark->course?->name ?? __('No course') }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ $mark->published_at?->format('M j, Y') ?? __('Published') }}</p>
                                            </div>
                                            <span class="text-sm font-semibold text-gray-900">{{ number_format((float) $mark->final_mark, 1) }}</span>
                                        </div>
                                    @empty
                                        <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('No published results yet.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">{{ __('Recent Attendance') }}</h4>
                                <div class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-100">
                                    @forelse($summary['recent_attendance'] as $attendance)
                                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-900">{{ $attendance->course?->name ?? __('No course') }}</p>
                                                <p class="mt-1 text-xs text-gray-500">{{ $attendance->date?->format('M j, Y') }}</p>
                                            </div>
                                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ __(ucfirst($attendance->status)) }}</span>
                                        </div>
                                    @empty
                                        <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('No attendance records yet.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </section>
                @empty
                    <section class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('No linked students') }}</h3>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Your account email must match a guardian email on a student profile.') }}</p>
                    </section>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
