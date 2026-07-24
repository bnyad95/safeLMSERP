<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Add Extra Semester</h2>
            <p class="text-sm text-gray-600">Use this only for special periods after the academic year is already created.</p>
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
