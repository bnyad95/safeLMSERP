<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Learning &amp; Results</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900">Examination Committee Dashboard</h2>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Review submitted marks, request corrections, approve valid results, and track what is waiting for publication.
                </p>
            </div>
            <x-dashboard-clock />
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    @php
                        $tone = [
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-800',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
                        ][$stat['tone']] ?? 'border-gray-200 bg-gray-50 text-gray-800';
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                                <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                            </div>
                            <span class="rounded-md border px-2.5 py-1 text-xs font-semibold {{ $tone }}">Marks</span>
                        </div>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Review Work</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($reviewItems as $item)
                            <a href="{{ $item['href'] }}" class="flex items-start justify-between gap-4 px-5 py-4 transition hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $item['hint'] }}</p>
                                </div>
                                <span class="text-lg font-semibold text-gray-900">{{ $item['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Recent Mark Activity</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $canPublish ? 'Approved marks can be published from the queue.' : 'Publishing is reserved for the examination administrator.' }}</p>
                        </div>
                        <a href="{{ route('marks.submission-queue') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Open Queue</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Course</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Mark</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($recentMarks as $mark)
                                    <tr>
                                        <td class="px-5 py-4">
                                            <p class="text-sm font-semibold text-gray-900">{{ $mark->student?->full_name ?? 'Unknown student' }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $mark->student?->student_id ?? 'No ID' }}</p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <p class="text-sm text-gray-900">{{ trim(($mark->course?->code ? $mark->course->code.' - ' : '').($mark->course?->name ?? 'No course')) }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $mark->courseSection ? 'Group '.$mark->courseSection->section_code : 'No class' }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ is_null($mark->final_mark) ? 'N/A' : number_format((float) $mark->final_mark, 1) }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ ucfirst(str_replace('_', ' ', $mark->submission_status)) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500">No mark activity is available for your scope yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
