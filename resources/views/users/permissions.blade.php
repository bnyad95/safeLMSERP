<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">User Permissions</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $user->name }} / {{ $user->email }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.edit', $user) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Back to User</a>
                <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">All Users</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Assigned Roles</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($user->roles as $role)
                                <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $role->display_name }}</span>
                            @empty
                                <span class="text-sm text-gray-500">No role assigned</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Effective Route Access</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $effectiveRouteAccessCount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Overrides</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $overrideEffects->count() }}</p>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('users.permissions.update', $user) }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="permission_signature" value="{{ $permissionSignature }}">

                @foreach($permissionGroups as $module => $permissions)
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $module }}</h3>
                        </div>
                        <div class="grid gap-0 divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($permissions as $permission)
                                @php
                                    $isEffective = in_array($permission->id, $effectivePermissionIds, true);
                                    $fromRole = $rolePermissionIds->contains($permission->id);
                                    $override = $overrideEffects[$permission->id] ?? null;
                                    $access = $accessByPermission[$permission->id];
                                @endphp
                                <label class="flex flex-col gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/60 sm:flex-row sm:items-start sm:justify-between">
                                    <span class="flex min-w-0 items-start gap-3">
                                        <input
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $permission->id }}"
                                            class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            @checked($isEffective)
                                        >
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $permission->display_name }}</span>
                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $permission->name }}</span>
                                        </span>
                                    </span>
                                    <span class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                                        <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $access['status'] === 'effective' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : ($access['status'] === 'conditional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : ($access['status'] === 'unenforced' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300')) }}" title="{{ $access['reason'] }}">{{ $access['label'] }}</span>
                                        <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $permission->risk_level === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200' : ($permission->risk_level === 'high' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300') }}" title="{{ $permission->risk_reason }}">{{ $permission->risk_label }}</span>
                                        @if($fromRole)
                                            <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Role</span>
                                        @endif
                                        @if($override === 'grant')
                                            <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Direct grant</span>
                                        @elseif($override === 'deny')
                                            <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Direct deny</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="sticky bottom-0 flex flex-col gap-4 border-t border-gray-200 bg-gray-50/95 px-4 py-4 backdrop-blur dark:border-gray-800 dark:bg-gray-950/95 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="confirm_permission_change" value="1" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500" required>
                        <span>I understand that direct grants and denies change this user's live access and create an audit record.</span>
                    </label>
                    <button type="submit" class="shrink-0 rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
