<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Attendance Management</h2>
                <p class="mt-1 text-sm text-gray-600">Track attendance by college, department, grade, semester, and module.</p>
            </div>

            <form method="GET" action="{{ route('attendance') }}" class="flex flex-wrap items-end gap-3">
                @foreach($filters as $key => $value)
                    @if($value !== null && $value !== '')
                        <input type="hidden" name="{{ $key === 'grade' ? 'stage' : $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-gray-500">Attendance date</span>
                    <input type="date" name="date" value="{{ $selectedDate }}" class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
                <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                <a href="{{ route('attendance') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach($breadcrumbs as $crumb)
                    <a href="{{ $crumb['href'] }}" class="rounded-md border border-gray-200 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">{{ $crumb['label'] }}</a>
                    @unless($loop->last)
                        <span class="text-gray-400">/</span>
                    @endunless
                @endforeach
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            @if($level !== 'modules')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">
                            @switch($level)
                                @case('colleges') Colleges @break
                                @case('departments') Departments @break
                                @case('grades') Stages @break
                                @case('semesters') Semesters @break
                            @endswitch
                        </h3>
                    </div>

                    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($cards as $card)
                            @if($card['href'])
                                <a href="{{ $card['href'] }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-base font-semibold text-gray-900">{{ $card['title'] }}</p>
                                            <p class="mt-1 text-sm text-gray-500">{{ $card['meta'] }}</p>
                                        </div>
                                        <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $card['count'] }} modules</span>
                                    </div>
                                    <p class="mt-5 text-sm text-gray-600">{{ number_format($card['students']) }} enrolled students</p>
                                </a>
                            @else
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-5">
                                    <p class="text-base font-semibold text-gray-900">{{ $card['title'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $card['meta'] }}</p>
                                    <p class="mt-5 text-sm text-gray-600">{{ $card['count'] }} modules / {{ number_format($card['students']) }} students</p>
                                </div>
                            @endif
                        @empty
                            <div class="col-span-full rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                                No active modules found for this level.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if($modules->isNotEmpty() || $level === 'modules')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $level === 'modules' ? 'Modules' : 'Modules in View' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">Open a module to mark attendance, review history, or print reports.</p>
                        </div>
                        <span class="text-sm font-medium text-gray-500">{{ $modules->count() }} shown</span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse($modules as $module)
                            <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900">{{ $module['title'] }}</p>
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $module['status'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">{{ $module['meta'] }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="rounded-md bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700">{{ $module['recorded'] }} / {{ $module['students'] }} recorded</span>
                                    @if($module['markRoute'])
                                        <a href="{{ $module['markRoute'] }}" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">Mark</a>
                                    @endif
                                    <a href="{{ $module['reportRoute'] }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Report</a>
                                    <a href="{{ $module['historyRoute'] }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">History</a>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-gray-500">No modules found for this selection.</div>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
