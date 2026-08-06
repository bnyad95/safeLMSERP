<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Manage User</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Review account ownership, access, and password security.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-100">{{ session('success') }}</div>
            @endif

            @if($abilities['permissions'] && ! $user->hasRole('super_administrator'))
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-800 dark:bg-indigo-900/20">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-indigo-950 dark:text-indigo-100">User Permissions</h3>
                            <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-200">Review effective permissions, direct grants, and direct denies for this user.</p>
                        </div>
                        <a href="{{ route('users.permissions.edit', $user) }}" class="inline-flex justify-center rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800">Manage Permissions</a>
                    </div>
                </div>
            @endif

            @if($profileManaged)
                <section class="rounded-lg border border-blue-200 bg-blue-50 p-5 dark:border-blue-800 dark:bg-blue-900/20">
                    <h3 class="text-base font-semibold text-blue-950 dark:text-blue-100">Profile-managed account</h3>
                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">This login belongs to a Student or Teacher profile. Update its name, email, organization, and role from that dedicated profile workspace so both records remain synchronized.</p>
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
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Reset Password</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set a temporary password, revoke the current session, and require a replacement at the next login.</p>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">New temporary password</label>
                        <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm temporary password</label>
                        <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Reset Password</button>
                </div>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>
