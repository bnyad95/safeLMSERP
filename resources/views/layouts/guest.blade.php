<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('localization.available.'.app()->getLocale().'.rtl') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SafeLMS ERP') }}</title>

        <script>
            (() => {
                const savedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const shouldUseDark = savedTheme ? savedTheme === 'dark' : prefersDark;

                document.documentElement.classList.toggle('dark', shouldUseDark);
                document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light';
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @endif
    </head>
    <body class="font-sans text-gray-900 antialiased dark:text-gray-100">
        <div class="fixed end-4 top-4 z-10 flex items-center gap-1 rounded-md border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @foreach(config('localization.available') as $code => $meta)
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <button type="submit" name="locale" value="{{ $code }}" class="{{ app()->getLocale() === $code ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }} rounded px-2 py-1 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        {{ $meta['native'] }}
                    </button>
                </form>
            @endforeach
        </div>
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-950">
            <div>
                <a href="/">
                    <x-application-logo class="h-32 w-auto max-w-[92vw] sm:h-44" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg dark:bg-gray-900 dark:shadow-gray-950/40">
                {{ $slot }}
            </div>

            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('Developed by Safe Data Co. All rights reserved') }}</p>
        </div>
    </body>
</html>
