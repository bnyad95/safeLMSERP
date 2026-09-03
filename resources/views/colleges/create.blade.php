<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ __('Add College') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form action="{{ route('colleges.store') }}" method="POST">
                @csrf
                @include('colleges.form')
            </form>
        </div>
    </div>
</x-app-layout>
