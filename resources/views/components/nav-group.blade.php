@props(['label', 'storageKey', 'active' => false])

<div
    x-data="{ open: {{ $active ? 'true' : 'false' }} }"
    x-init="open = open || JSON.parse(localStorage.getItem('nav-open-{{ $storageKey }}') ?? 'true')"
>
    <button
        type="button"
        @click="open = ! open; localStorage.setItem('nav-open-{{ $storageKey }}', JSON.stringify(open))"
        class="flex w-full items-center justify-between px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        :aria-expanded="open.toString()"
    >
        <span>{{ $label }}</span>
        <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="{ 'rotate-90': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
    </button>
    <div x-show="open" x-transition class="space-y-1">
        {{ $slot }}
    </div>
</div>
