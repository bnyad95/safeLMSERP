<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('ERP Overview') }}</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Welcome back, :name', ['name' => explode(' ', Auth::user()->name)[0]]) }}</h2>
            </div>
            <x-dashboard-clock />
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    @php
                        $tone = [
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200',
                            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-200',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200',
                        ][$stat['tone']] ?? 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300';
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                                <p class="mt-3 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                            </div>
                            <span class="rounded-md border px-2.5 py-1 text-xs font-semibold {{ $tone }}">Live</span>
                        </div>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Review Status</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($reviewItems as $item)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item['label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
                                </div>
                                <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Academic Structure</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-3 p-5">
                            @foreach($structure as $item)
                                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50">
                                    <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $item['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
            </div>
        </div>
    </div>
</x-app-layout>
