<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ $title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
                        <div class="mt-2 flex items-end justify-between">
                            <h3 class="text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</h3>
                            <span class="text-sm font-medium text-emerald-600">{{ $stat['change'] ?? 'Live' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Recent activity</h3>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">Live ERP view</span>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach($items as $item)
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <h4 class="font-semibold text-gray-900">{{ $item['title'] }}</h4>
                            <p class="mt-2 text-sm text-gray-600">{{ $item['meta'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
