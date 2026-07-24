<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Notifications</h2>
                <p class="text-sm text-gray-600">
                    {{ auth()->user()->hasRole('teacher')
                        ? 'Class submissions, student messages, deadlines, and teaching alerts.'
                        : 'Alerts and calendar events for deadlines, payments, attendance, and published marks.' }}
                </p>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Mark all read</button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Inbox</h3>
                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700">{{ $unreadCount }} unread</span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse($notifications as $notification)
                        <div class="p-5 {{ $notification->read_at ? 'bg-white' : 'bg-blue-50/60' }}">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-semibold text-gray-900">{{ $notification->title }}</h4>
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-700">{{ str_replace('_', ' ', $notification->type) }}</span>
                                        <span class="rounded-md px-2 py-1 text-xs font-semibold uppercase {{ $notification->severity === 'danger' ? 'bg-red-100 text-red-700' : ($notification->severity === 'warning' ? 'bg-amber-100 text-amber-700' : ($notification->severity === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700')) }}">{{ $notification->severity }}</span>
                                    </div>
                                    @if($notification->body)
                                        <p class="mt-2 text-sm text-gray-600">{{ $notification->body }}</p>
                                    @endif
                                    <p class="mt-2 text-xs text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if($notification->action_url)
                                        <a href="{{ $notification->action_url }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Open</a>
                                    @endif
                                    @unless($notification->read_at)
                                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">Read</button>
                                        </form>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No notifications yet.</div>
                    @endforelse
                </div>

                @if($notifications->hasPages())
                    <div class="border-t border-gray-200 px-6 py-4">{{ $notifications->links() }}</div>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Calendar</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($events as $event)
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">{{ $event->title }}</h4>
                                    @if($event->description)
                                        <p class="mt-1 text-sm text-gray-600">{{ $event->description }}</p>
                                    @endif
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $event->category) }}</p>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <p>{{ $event->starts_at->format('M d') }}</p>
                                    <p>{{ $event->starts_at->format('H:i') }}</p>
                                </div>
                            </div>
                            @if($event->action_url)
                                <a href="{{ $event->action_url }}" class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:underline">Open related item</a>
                            @endif
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-gray-500">No upcoming calendar events.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
