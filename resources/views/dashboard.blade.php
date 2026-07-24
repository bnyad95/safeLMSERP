<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">ERP Overview</p>
                <h2 class="mt-1 text-2xl font-semibold text-gray-900">{{ config('app.name', 'SafeLMS ERP') }}</h2>
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
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
                        ][$stat['tone']] ?? 'border-gray-200 bg-gray-50 text-gray-700';
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                                <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                            </div>
                            <span class="rounded-md border px-2.5 py-1 text-xs font-semibold {{ $tone }}">Live</span>
                        </div>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Review Status</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($reviewItems as $item)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $item['label'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $item['hint'] }}</p>
                                </div>
                                <span class="text-lg font-semibold text-gray-900">{{ $item['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">Academic Structure</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-3 p-5">
                            @foreach($structure as $item)
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <p class="text-xs font-medium uppercase text-gray-500">{{ $item['label'] }}</p>
                                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $item['value'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
            </div>
        </div>
    </div>
</x-app-layout>
