@props(['label', 'storageKey', 'active' => false])

<div
    x-data="{
        open: {{ $active ? 'true' : 'false' }},
        hoverOpen: false,
        hoverTimeout: null,
        canHover: window.matchMedia('(hover: hover) and (pointer: fine)').matches,
        toggle() {
            this.open = ! this.open;
            localStorage.setItem('nav-open-{{ $storageKey }}', JSON.stringify(this.open));
        },
        onEnter() {
            if (! this.canHover || this.open) return;
            clearTimeout(this.hoverTimeout);
            this.hoverTimeout = setTimeout(() => { this.hoverOpen = true; }, 150);
        },
        onLeave() {
            if (! this.canHover) return;
            clearTimeout(this.hoverTimeout);
            this.hoverTimeout = setTimeout(() => { this.hoverOpen = false; }, 250);
        },
    }"
    x-init="open = open || JSON.parse(localStorage.getItem('nav-open-{{ $storageKey }}') ?? 'true')"
    @mouseenter="onEnter()"
    @mouseleave="onLeave()"
>
    <button
        type="button"
        @click="toggle()"
        class="flex w-full items-center justify-between rounded-md px-3 pb-1 pt-4 text-xs font-semibold uppercase text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
        :aria-expanded="(open || hoverOpen).toString()"
    >
        <span>{{ __($label) }}</span>
        <svg class="h-3.5 w-3.5 shrink-0 transition-transform" :class="{ 'rotate-90': open || hoverOpen }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
        </svg>
    </button>
    <div x-show="open || hoverOpen" x-transition class="space-y-1">
        {{ $slot }}
    </div>
</div>
