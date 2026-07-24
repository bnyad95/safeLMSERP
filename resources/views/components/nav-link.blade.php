@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex w-full items-center rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-100 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-400/30'
            : 'flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
