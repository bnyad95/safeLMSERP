<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Activity Log') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('System-wide record of finance, enrollment, tuition, mark, and account changes.') }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('activity-log') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="log-search">{{ __('Search') }}</label>
                        <input id="log-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Description') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="log_name">{{ __('Event type') }}</label>
                        <select id="log_name" name="log_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">{{ __('All types') }}</option>
                            @foreach($logNames as $logName)
                                <option value="{{ $logName }}" @selected($filters['log_name'] === $logName)>{{ str(class_basename($logName))->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="causer_id">{{ __('Actor') }}</label>
                        <select id="causer_id" name="causer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">{{ __('All actors') }}</option>
                            @foreach($actors as $actor)
                                <option value="{{ $actor->id }}" @selected((int) $filters['causer_id'] === $actor->id)>{{ $actor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="date_from">{{ __('From') }}</label>
                        <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="date_to">{{ __('To') }}</label>
                            <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('Apply') }}</button>
                    <a href="{{ route('activity-log') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Reset') }}</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Events') }}
                        <span class="ml-2 rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $logs->total() }}</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Timestamp') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Actor') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Event') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Subject') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($logs as $log)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $log->created_at?->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $log->causer?->name ?? __('System') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $log->causer?->email ?? '-' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-md bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ str(class_basename($log->log_name ?? 'event'))->replace('_', ' ')->title() }}</span>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ str($log->description ?? '')->replace('_', ' ')->ucfirst() }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                        @if($log->subject_type)
                                            {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        @if(is_array($log->properties) && count($log->properties) > 0)
                                            <ul class="space-y-0.5">
                                                @foreach($log->properties as $key => $value)
                                                    @if(! is_array($value))
                                                        <li><span class="font-semibold text-gray-600 dark:text-gray-300">{{ str($key)->replace('_', ' ') }}:</span> {{ $value }}</li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No activity recorded for the current filters.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                    <div class="border-t px-4 py-3 dark:border-gray-800">{{ $logs->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
