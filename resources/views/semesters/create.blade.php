<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ __('Add Semester') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Add an ordered regular or summer semester inside an open academic year.') }}</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form action="{{ route('semesters.store') }}" method="POST">
                @csrf
                @include('semesters.form')
            </form>
        </div>
    </div>
</x-app-layout>
