<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Edit Course</h2>
                <p class="text-sm text-gray-600">Update the reusable catalog definition.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form action="{{ route('course-records.update', $course) }}" method="POST">
                @csrf
                @method('PUT')
                @include('courses.form')
            </form>
        </div>
    </div>
</x-app-layout>
