<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Add Academic Year</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Create a new academic year record for one or more institutions. Semesters are managed separately from the Semesters page.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Bologna Definition
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-semibold">Please check the academic year details.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">New Academic Year</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This creates only the academic year record. Define semester entries from the Semesters card.</p>
                </div>
                <form method="POST" action="{{ route('academic-years.store') }}" class="space-y-5 p-5">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-1">
                        <div>
                            <label for="academic_year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label>
                            <input
                                id="academic_year"
                                name="academic_year"
                                type="text"
                                value="{{ old('academic_year') }}"
                                placeholder="2027/2028"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            >
                        </div>
                    </div>

                    <div>
                        <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">Universities</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @forelse($universities as $university)
                                <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-200">
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
                                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Create a university before opening an academic year.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:flex-row sm:justify-end">
                        <a href="{{ route('academic-years.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500" @disabled($universities->isEmpty())>
                            Create Academic Year
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
