<div
    x-data="{
        open: false,
        settingsOpen: false,
        darkMode: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.darkMode = ! this.darkMode;
            document.documentElement.classList.toggle('dark', this.darkMode);
            document.documentElement.style.colorScheme = this.darkMode ? 'dark' : 'light';
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
        },
        fontSize: localStorage.getItem('fontSize') || 'base',
        setFontSize(size) {
            this.fontSize = size;
            document.documentElement.style.fontSize = ({ sm: '14px', base: '16px', lg: '18px' })[size] || '16px';
            localStorage.setItem('fontSize', size);
        },
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
>
    <button
        type="button"
        @click="open = ! open"
        class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-gray-800"
        :aria-expanded="open.toString()"
        aria-haspopup="true"
    >
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </span>
        <span class="hidden text-left sm:block">
            <span class="block max-w-[10rem] truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ Auth::user()->name }}</span>
        </span>
        <svg class="hidden h-4 w-4 shrink-0 text-gray-400 transition sm:block" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute right-0 z-50 mt-2 w-64 origin-top-right rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ Auth::user()->name }}</p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p>
        </div>

        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800">
            {{ Auth::user()?->hasRole('student') ? __('Account Settings') : __('Profile') }}
        </a>

        <div class="border-t border-gray-100 dark:border-gray-800">
            <button
                type="button"
                @click="settingsOpen = ! settingsOpen"
                class="flex w-full items-center justify-between px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                :aria-expanded="settingsOpen.toString()"
            >
                <span>{{ __('Settings') }}</span>
                <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="settingsOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <div x-show="settingsOpen" x-transition x-cloak class="space-y-2 bg-gray-50 px-4 py-3 dark:bg-gray-950/40">
                <form method="POST" action="{{ route('locale.update') }}" class="flex items-center justify-between rounded-md py-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                    @csrf
                    <span>{{ __('Language') }}</span>
                    <div class="flex items-center gap-1">
                        @foreach(config('localization.available') as $code => $meta)
                            <button type="submit" name="locale" value="{{ $code }}" class="{{ app()->getLocale() === $code ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }} rounded px-2 py-1 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                {{ $meta['native'] }}
                            </button>
                        @endforeach
                    </div>
                </form>

                <div class="flex items-center justify-between rounded-md py-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                    <span>{{ __('Font size') }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="setFontSize('sm')" :class="fontSize === 'sm' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'" class="rounded px-2 py-1 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="{{ __('Small font size') }}">A</button>
                        <button type="button" @click="setFontSize('base')" :class="fontSize === 'base' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'" class="rounded px-2 py-1 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="{{ __('Default font size') }}">A</button>
                        <button type="button" @click="setFontSize('lg')" :class="fontSize === 'lg' ? 'bg-gray-900 text-white dark:bg-indigo-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'" class="rounded px-2 py-1 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="{{ __('Large font size') }}">A</button>
                    </div>
                </div>

                <button
                    type="button"
                    @click="toggleTheme()"
                    class="flex w-full items-center justify-between rounded-md py-1 text-xs font-medium text-gray-500 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:text-gray-200"
                    :aria-pressed="darkMode.toString()"
                    :aria-label="darkMode ? '{{ __('Dark mode') }}' : '{{ __('Light mode') }}'"
                >
                    <span class="flex items-center gap-1.5">
                        <svg x-show="!darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                        <svg x-show="darkMode" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        <span x-text="darkMode ? '{{ __('Dark mode') }}' : '{{ __('Light mode') }}'">{{ __('Theme') }}</span>
                    </span>
                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-gray-300 transition dark:bg-indigo-600">
                        <span
                            class="inline-block h-4 w-4 rounded-full bg-white shadow transition"
                            :class="darkMode ? 'translate-x-4' : 'translate-x-0.5'"
                        ></span>
                    </span>
                </button>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-gray-800">
            @csrf
            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</div>
