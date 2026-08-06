<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Add User</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create a staff or support account and assign its organization scope.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                @include('users.form')
            </form>
        </div>
    </div>
</x-app-layout>
