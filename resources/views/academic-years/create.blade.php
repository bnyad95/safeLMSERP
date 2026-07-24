<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Add Academic Year</h2>
                <p class="text-sm text-gray-600">Open a new year by creating its semesters while reusing existing universities, colleges, departments, and course names.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Bologna Definition
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please check the academic year details.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">New Academic Year</h3>
                    <p class="mt-1 text-sm text-gray-500">This creates only semester periods. The existing catalog and structure remain available for the new year.</p>
                </div>
                <form method="POST" action="{{ route('academic-years.store') }}" class="space-y-5 p-5">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year</label>
                            <input
                                id="academic_year"
                                name="academic_year"
                                type="text"
                                value="{{ old('academic_year') }}"
                                placeholder="2027/2028"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>
                        <div>
                            <label for="first_semester_start_date" class="block text-sm font-medium text-gray-700">First Semester Start Date</label>
                            <input
                                id="first_semester_start_date"
                                name="first_semester_start_date"
                                type="date"
                                value="{{ old('first_semester_start_date') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="semester_length_months" class="block text-sm font-medium text-gray-700">Semester Length In Months</label>
                        <input
                            id="semester_length_months"
                            name="semester_length_months"
                            type="number"
                            min="1"
                            max="12"
                            value="{{ old('semester_length_months', 5) }}"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-1 text-xs text-gray-500">Leave the start date empty if dates are not ready yet.</p>
                    </div>

                    <div>
                        <label for="semester_names" class="block text-sm font-medium text-gray-700">Semester Names</label>
                        <textarea
                            id="semester_names"
                            name="semester_names"
                            rows="4"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >{{ old('semester_names', $defaultSemesterNames) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Add one semester per line, or separate names with commas.</p>
                    </div>

                    <div>
                        <p class="block text-sm font-medium text-gray-700">Universities</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @forelse($universities as $university)
                                <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="university_ids[]"
                                        value="{{ $university->id }}"
                                        @checked(collect(old('university_ids', $universities->count() === 1 ? [$university->id] : []))->contains($university->id))
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                    <span>{{ $university->name }}</span>
                                </label>
                            @empty
                                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">Create a university before opening an academic year.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" @disabled($universities->isEmpty())>
                            Create Academic Year
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
