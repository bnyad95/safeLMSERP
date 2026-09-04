<x-guest-layout>
    <div class="py-6 text-center">
        <p class="text-sm font-semibold text-red-600 dark:text-red-400">500</p>
        <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Something Went Wrong') }}</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('An unexpected error occurred. Please try again in a moment.') }}</p>
        <a href="{{ url('/') }}" class="mt-6 inline-flex justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
            {{ __('Back to Dashboard') }}
        </a>
    </div>
</x-guest-layout>
