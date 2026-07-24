<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Add Teacher</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create a new teacher profile and link the teacher login account.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form action="{{ route('teachers.store') }}" method="POST">
                @csrf
                @include('teachers.form')
            </form>
        </div>
    </div>
</x-app-layout>
