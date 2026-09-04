<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ __('Manage User') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Review account ownership, access, and password security.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($abilities['permissions'] && ! $user->hasRole('super_administrator'))
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-indigo-950 dark:text-indigo-100">{{ __('User Permissions') }}</h3>
                            <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-200">{{ __('Review effective permissions, direct grants, and direct denies for this user.') }}</p>
                        </div>
                        <a href="{{ route('users.permissions.edit', $user) }}" class="inline-flex justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">{{ __('Manage Permissions') }}</a>
                    </div>
                </div>
            @endif

            @if($profileManaged)
                <section class="rounded-lg border border-blue-200 bg-blue-50 p-5 dark:border-blue-800 dark:bg-blue-900/20">
                    <h3 class="text-base font-semibold text-blue-950 dark:text-blue-100">{{ __('Profile-managed account') }}</h3>
                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">{{ __('This login belongs to a Student or Teacher profile. Update its name, email, organization, and role from that dedicated profile workspace so both records remain synchronized.') }}</p>
                </section>
            @elseif($abilities['update'])
                <form action="{{ route('users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('users.form')
                </form>
            @endif

            @if($abilities['reset_password'])
            <form action="{{ route('users.reset-password', $user) }}" method="POST" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Reset Password') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Set a temporary password, revoke the current session, and require a replacement at the next login.') }}</p>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('New temporary password') }}</label>
                        <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Confirm temporary password') }}</label>
                        <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('Reset Password') }}</button>
                </div>
            </form>
            @endif

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Recent Account Activity') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Role, permission, password, and account record changes.') }}</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($activityLogs as $activity)
                        <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ str($activity->description)->replace('_', ' ')->headline() }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('By') }} {{ $activity->causer?->name ?? __('System') }}</p>
                            </div>
                            <time class="text-xs text-gray-500 dark:text-gray-400" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No account activity has been recorded.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
