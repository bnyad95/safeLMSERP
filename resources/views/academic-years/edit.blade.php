<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Bologna Definition') }}</p>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Edit Academic Year') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $academicYear->university?->name }} · {{ $academicYear->name }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('academic-years.update', $academicYear) }}" class="space-y-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic year') }}</label>
                        <input name="name" value="{{ old('name', $academicYear->name) }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Status') }}</label>
                        <select name="status" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="upcoming" @selected(old('status', $academicYear->status) === 'upcoming')>{{ __('Upcoming') }}</option>
                            <option value="active" @selected(old('status', $academicYear->status) === 'active')>{{ __('Active') }}</option>
                        </select>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Active years can only be completed through Academic Year Closing.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Start date') }}</label>
                        <input type="date" name="starts_on" value="{{ old('starts_on', $academicYear->starts_on?->format('Y-m-d')) }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('End date') }}</label>
                        <input type="date" name="ends_on" value="{{ old('ends_on', $academicYear->ends_on?->format('Y-m-d')) }}" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <a href="{{ route('academic-years.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">{{ __('Cancel') }}</a>
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Update Academic Year') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
