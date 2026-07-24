<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Role Access Matrix</h2>
                <p class="text-sm text-gray-600">Review and manage role permissions with audit-safe controls.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please review the permission change.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            <form method="GET" action="{{ route('access-matrix') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-5">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700">Role</span>
                        <select name="role_id" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) $filters['role_id'] === $role->id)>
                                    {{ $role->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700">Module</span>
                        <select name="module" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All modules</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module['key'] }}" @selected($filters['module'] === $module['key'])>
                                    {{ $module['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700">Access</span>
                        <select name="access" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" @selected($filters['access'] === 'all')>All permissions</option>
                            <option value="granted" @selected($filters['access'] === 'granted')>Granted to selected role</option>
                            <option value="missing" @selected($filters['access'] === 'missing')>Missing from selected role</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700">Mode</span>
                        <select name="mode" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="view" @selected($filters['mode'] === 'view')>Review</option>
                            <option value="edit" @selected($filters['mode'] === 'edit')>Edit role</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700">Search</span>
                        <input name="q" value="{{ $filters['q'] }}" placeholder="Permission name" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">
                        {{ $permissions->count() }} permissions in view.
                        @if ($selectedRole)
                            Selected role: <span class="font-medium text-gray-700">{{ $selectedRole->display_name }}</span>
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <a href="{{ route('access-matrix') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</a>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                    </div>
                </div>
            </form>

            @if ($filters['mode'] === 'edit')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Edit mode changes live permissions and writes an audit log. Super Administrator is read-only.
                </div>

                @if (! $selectedRole)
                    <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Choose a role to edit</h3>
                        <p class="mt-2 text-sm text-gray-500">Use the role filter above, then apply edit mode.</p>
                    </div>
                @elseif ($selectedRole->name === 'super_administrator')
                    <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800">
                        Super Administrator permissions cannot be changed from this screen.
                    </div>
                @else
                    @php
                        $visiblePermissionIds = $permissions->pluck('id')->all();
                        $hiddenGrantedPermissionIds = $allPermissions
                            ->whereIn('name', $selectedPermissionNames)
                            ->whereNotIn('id', $visiblePermissionIds)
                            ->pluck('id');
                    @endphp

                    <form method="POST" action="{{ route('access-matrix.roles.permissions.update', $selectedRole) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        @foreach ($hiddenGrantedPermissionIds as $permissionId)
                            <input type="hidden" name="permission_ids[]" value="{{ $permissionId }}">
                        @endforeach

                        @forelse ($permissionGroups as $moduleLabel => $groupPermissions)
                            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $moduleLabel }}</h3>
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">{{ $groupPermissions->count() }} permissions</span>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    @foreach ($groupPermissions as $permission)
                                        <label class="flex flex-col gap-3 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between">
                                            <span>
                                                <span class="font-medium text-gray-900">{{ $permission->display_name ?: $permission->name }}</span>
                                                <span class="mt-1 block text-xs text-gray-500">{{ $permission->name }}</span>
                                            </span>
                                            <span class="flex items-center gap-3">
                                                @if ($permission->getAttribute('is_high_risk'))
                                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">High risk</span>
                                                @endif
                                                <input
                                                    type="checkbox"
                                                    name="permission_ids[]"
                                                    value="{{ $permission->id }}"
                                                    @checked(in_array($permission->name, $selectedPermissionNames, true))
                                                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                >
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @empty
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800">
                                No permissions match the current filters.
                            </div>
                        @endforelse

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                <input type="checkbox" name="confirm_permission_change" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>I understand this will update live permissions for {{ $selectedRole->display_name }} and create an audit log.</span>
                            </label>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Save Permissions
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            @else
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Review mode is read-only. Use edit mode with a selected role when permission changes are required.
                </div>

                @forelse ($permissionGroups as $moduleLabel => $groupPermissions)
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $moduleLabel }}</h3>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">{{ $groupPermissions->count() }} permissions</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700">Permission</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700">{{ $selectedRole ? 'Selected role access' : 'Granted roles' }}</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700">Risk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @foreach ($groupPermissions as $permission)
                                        @php
                                            $grantedRoles = $selectedRole
                                                ? collect([$selectedRole])->filter(fn ($role) => $role->permissions->contains('name', $permission->name))
                                                : $roles->filter(fn ($role) => $role->permissions->contains('name', $permission->name));
                                        @endphp
                                        <tr>
                                            <td class="px-5 py-4 align-top">
                                                <p class="font-medium text-gray-900">{{ $permission->display_name ?: $permission->name }}</p>
                                                <p class="text-xs text-gray-500">{{ $permission->name }}</p>
                                            </td>
                                            <td class="px-5 py-4 align-top">
                                                @if ($grantedRoles->isEmpty())
                                                    <span class="text-sm text-gray-400">{{ $selectedRole ? 'Not granted to selected role' : 'No roles granted' }}</span>
                                                @else
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach ($grantedRoles as $role)
                                                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">{{ $role->display_name }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 align-top">
                                                @if ($permission->getAttribute('is_high_risk'))
                                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">High risk</span>
                                                @else
                                                    <span class="text-sm text-gray-400">Standard</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800">
                        No permissions match the current filters.
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</x-app-layout>
