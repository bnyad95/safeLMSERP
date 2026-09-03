<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold text-gray-900">{{ $title }}</h2>
                    <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $badge }}</span>
                </div>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ $description }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($actions as $action)
                    <a
                        href="{{ route($action['route']) }}"
                        class="{{ ($action['style'] ?? 'secondary') === 'primary' ? 'bg-gray-900 text-white hover:bg-gray-800' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }} rounded-md px-3 py-2 text-sm font-semibold"
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    {{ __('Dashboard') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.45fr_0.85fr]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ $itemsTitle }}</h3>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse($items as $item)
                            <div class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $item['title'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $item['meta'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $item['status'] }}</span>
                                    @isset($item['markRoute'])
                                        <a href="{{ $item['markRoute'] }}" class="rounded-md bg-gray-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-gray-800">{{ __('Mark') }}</a>
                                    @endisset
                                    @isset($item['reportRoute'])
                                        <a href="{{ $item['reportRoute'] }}" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">{{ __('Report') }}</a>
                                    @endisset
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-gray-500">{{ $emptyText }}</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ $workflowTitle }}</h3>
                    </div>

                    <div class="space-y-3 p-5">
                        @foreach($workflow as $step)
                            <div class="rounded-lg bg-gray-50 p-4">
                                <p class="text-sm font-semibold text-gray-900">{{ $step['label'] }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $step['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
