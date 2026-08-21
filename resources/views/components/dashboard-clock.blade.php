@props([
    'date' => now(),
])

@php
    $timezone = config('app.timezone', 'Asia/Baghdad');
    $clockDate = $date->copy()->timezone($timezone);
@endphp

<div
    x-data="{
        now: new Date('{{ $clockDate->toIso8601String() }}'),
        init() {
            window.setInterval(() => {
                this.now = new Date(this.now.getTime() + 1000);
            }, 1000);
        },
        dateText() {
            return new Intl.DateTimeFormat('en', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                timeZone: '{{ $timezone }}',
            }).format(this.now);
        },
        timeText() {
            return new Intl.DateTimeFormat('en', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZone: '{{ $timezone }}',
            }).format(this.now);
        },
    }"
    class="text-sm sm:text-right"
>
    <time datetime="{{ $clockDate->toIso8601String() }}" class="block font-semibold text-gray-900 dark:text-gray-100" x-text="dateText()">
        {{ $clockDate->format('l, F j, Y') }}
    </time>
    <p class="mt-0.5 text-gray-500 dark:text-gray-400" x-text="timeText()">{{ $clockDate->format('h:i:s A') }}</p>
</div>
