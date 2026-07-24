<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 lg:flex dark:bg-gray-950">
            @include('layouts.navigation')

            <div class="min-w-0 flex-1 lg:pl-72">
                @if (session('success') || session('error'))
                    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                        <div class="rounded-xl border p-4 text-sm shadow-sm sm:p-5 {{ session('success') ? 'bg-green-50 border-green-200 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-100' : 'bg-red-50 border-red-200 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100' }}">
                            {{ session('success') ?? session('error') }}
                        </div>
                    </div>
                @endif

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow dark:bg-gray-900 dark:shadow-gray-950/40">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
