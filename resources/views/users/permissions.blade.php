<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">User Permissions</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $user->name }} / {{ $user->email }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.edit', $user) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to User</a>
                <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">All Users</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">Assigned Roles</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($user->roles as $role)
                                <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ $role->display_name }}</span>
                            @empty
                                <span class="text-sm text-gray-500">No role assigned</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">Effective Permissions</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ count($effectivePermissionIds) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-500">Overrides</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $overrideEffects->count() }}</p>
                    </div>
                </div>
            </section>

            <form method="POST" action="{{ route('users.permissions.update', $user) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                @foreach($permissionGroups as $module => $permissions)
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">{{ $module }}</h3>
                        </div>
                        <div class="grid gap-0 divide-y divide-gray-100">
                            @foreach($permissions as $permission)
                                @php
                                    $isEffective = in_array($permission->id, $effectivePermissionIds, true);
                                    $fromRole = $rolePermissionIds->contains($permission->id);
                                    $override = $overrideEffects[$permission->id] ?? null;
                                @endphp
                                <label class="flex flex-col gap-3 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-start sm:justify-between">
                                    <span class="flex min-w-0 items-start gap-3">
                                        <input
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $permission->id }}"
                                            class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            @checked($isEffective)
                                        >
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900">{{ $permission->display_name }}</span>
                                            <span class="mt-1 block text-xs text-gray-500">{{ $permission->name }}</span>
                                        </span>
                                    </span>
                                    <span class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
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

                <div class="sticky bottom-0 flex justify-end border-t border-gray-200 bg-gray-50/95 px-4 py-4 backdrop-blur">
                    <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
