<x-guest-layout>
    <div class="py-6 text-center">
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">404</p>
        <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Page Not Found') }}</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __("The page you're looking for doesn't exist or has moved.") }}</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="mt-6 inline-flex justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">
            {{ auth()->check() ? __('Back to Dashboard') : __('Log in') }}
        </a>
    </div>
</x-guest-layout>
