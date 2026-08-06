<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Role Access Matrix</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Review assigned permissions, route compatibility, organization scope, and user-level overrides.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100">
                    <p class="font-semibold">The permission change was not saved.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Access statistics">
                @foreach ($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('access-matrix') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Role</span>
                        <select name="role_id" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) $filters['role_id'] === $role->id)>{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Module</span>
                        <select name="module" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all">All modules</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module['key'] }}" @selected($filters['module'] === $module['key'])>{{ $module['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Assignment</span>
                        <select name="access" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all" @selected($filters['access'] === 'all')>All permissions</option>
                            <option value="granted" @selected($filters['access'] === 'granted')>Assigned to selected role</option>
                            <option value="missing" @selected($filters['access'] === 'missing')>Not assigned to selected role</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Risk</span>
                        <select name="risk" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all" @selected($filters['risk'] === 'all')>All risk levels</option>
                            <option value="critical" @selected($filters['risk'] === 'critical')>Critical</option>
                            <option value="high" @selected($filters['risk'] === 'high')>High risk</option>
                            <option value="standard" @selected($filters['risk'] === 'standard')>Standard</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Mode</span>
                        <select name="mode" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="view" @selected($filters['mode'] === 'view')>Review</option>
                            <option value="edit" @selected($filters['mode'] === 'edit')>Edit role</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Search</span>
                        <input name="q" value="{{ $filters['q'] }}" placeholder="Permission name" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $permissions->count() }} permissions in view.
                        @if ($selectedRole)
                            Selected role: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $selectedRole->display_name }}</span>
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <a href="{{ route('access-matrix') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                    </div>
                </div>
            </form>

            @if ($selectedRoleImpact)
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="role-impact-title">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 id="role-impact-title" class="font-semibold text-gray-900 dark:text-gray-100">{{ $selectedRole->display_name }} impact</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Role changes affect every active user assigned to this role. Direct user overrides remain unchanged.</p>
                        </div>
                        <a href="{{ route('users.index', ['role_id' => $selectedRole->id]) }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">View assigned users</a>
                    </div>
                    <div class="grid divide-y divide-gray-100 dark:divide-gray-800 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Active users</p><p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($selectedRoleImpact['users']) }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Organization scope</p><p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $selectedRoleImpact['scope']['label'] }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $selectedRoleImpact['scope']['detail'] }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Direct grants</p><p class="mt-2 text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($selectedRoleImpact['direct_grants']) }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Direct denies</p><p class="mt-2 text-xl font-semibold text-red-700 dark:text-red-300">{{ number_format($selectedRoleImpact['direct_denies']) }}</p></div>
                    </div>
                </section>
            @endif

            @if ($filters['mode'] === 'edit')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100">
                    Checkboxes change the role template. Compatibility labels explain when an additional route role or direct user grant is required.
                </div>

                @if (! $selectedRole)
                    <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Choose a role to edit</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Select a role above and apply edit mode.</p>
                    </div>
                @elseif ($selectedRole->name === 'super_administrator')
                    <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100">
                        Super Administrator has implicit full access and cannot be edited from this screen.
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
                        <input type="hidden" name="permission_signature" value="{{ $permissionSignature }}">

                        @foreach ($hiddenGrantedPermissionIds as $permissionId)
                            <input type="hidden" name="permission_ids[]" value="{{ $permissionId }}">
                        @endforeach

                        @forelse ($permissionGroups as $moduleLabel => $groupPermissions)
                            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $moduleLabel }}</h3>
                                    <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $groupPermissions->count() }} permissions</span>
                                </div>
                                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($groupPermissions as $permission)
                                        @php
                                            $access = $roleAccess[$permission->id][$selectedRole->id];
                                            $overrides = $overrideCounts[$permission->id] ?? ['grant' => 0, 'deny' => 0];
                                        @endphp
                                        <label class="flex flex-col gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/60 lg:flex-row lg:items-center lg:justify-between">
                                            <span class="min-w-0">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $permission->display_name ?: $permission->name }}</span>
                                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $permission->name }}</span>
                                                <span class="mt-2 block text-xs text-gray-600 dark:text-gray-300">{{ $access['reason'] }}</span>
                                            </span>
                                            <span class="flex shrink-0 flex-wrap items-center gap-2">
                                                <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $access['status'] === 'effective' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : ($access['status'] === 'conditional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : ($access['status'] === 'unenforced' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300')) }}">{{ $access['label'] }}</span>
                                                <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $permission->risk_level === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200' : ($permission->risk_level === 'high' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300') }}" title="{{ $permission->risk_reason }}">{{ $permission->risk_label }}</span>
                                                @if($overrides['grant'] || $overrides['deny'])
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">Overrides: +{{ $overrides['grant'] }} / -{{ $overrides['deny'] }}</span>
                                                @endif
                                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->name, $selectedPermissionNames, true)) class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950">
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @empty
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-100">No permissions match the current filters.</div>
                        @endforelse

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="confirm_permission_change" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950">
                                <span>I understand this updates access for {{ number_format($selectedRoleImpact['users']) }} active users assigned to {{ $selectedRole->display_name }}, preserves direct user overrides, and creates an audit log.</span>
                            </label>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">Save Permissions</button>
                            </div>
                        </div>
                    </form>
                @endif
            @else
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-100">
                    Review mode separates role assignment from effective route compatibility. Direct grants and denies apply to individual users after role permissions.
                </div>

                @forelse ($permissionGroups as $moduleLabel => $groupPermissions)
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $moduleLabel }}</h3>
                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $groupPermissions->count() }} permissions</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Permission</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ $selectedRole ? 'Role assignment' : 'Assigned / implicit roles' }}</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Compatibility</th>
                                        @if($selectedRole)<th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Direct overrides</th>@endif
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Risk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                    @foreach ($groupPermissions as $permission)
                                        @php
                                            $displayRoles = $selectedRole
                                                ? collect([$selectedRole])
                                                : $roles->filter(fn ($role) => $role->name === 'super_administrator' || $role->permissions->contains('name', $permission->name));
                                            $overrides = $overrideCounts[$permission->id] ?? ['grant' => 0, 'deny' => 0];
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            <td class="px-5 py-4 align-top">
                                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $permission->display_name ?: $permission->name }}</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $permission->name }}</p>
                                            </td>
                                            <td class="px-5 py-4 align-top">
                                                @if ($selectedRole)
                                                    @if($selectedRole->name === 'super_administrator')
                                                        <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">Implicit</span>
                                                    @elseif(in_array($permission->name, $selectedPermissionNames, true))
                                                        <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">Assigned</span>
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">Not assigned</span>
                                                    @endif
                                                @else
                                                    <div class="flex max-w-md flex-wrap gap-1.5">
                                                        @forelse($displayRoles as $role)
                                                            @php $access = $roleAccess[$permission->id][$role->id]; @endphp
                                                            <span class="rounded-md px-2 py-1 text-xs font-medium {{ $access['status'] === 'conditional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : ($access['status'] === 'unenforced' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200') }}" title="{{ $access['reason'] }}">{{ $role->display_name }}</span>
                                                        @empty
                                                            <span class="text-gray-400 dark:text-gray-500">No role assignment</span>
                                                        @endforelse
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-5 py-4 align-top">
                                                @if($selectedRole)
                                                    @php $access = $roleAccess[$permission->id][$selectedRole->id]; @endphp
                                                    <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ in_array($access['status'], ['effective', 'implicit'], true) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : ($access['status'] === 'conditional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : ($access['status'] === 'unenforced' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300')) }}">{{ $access['label'] }}</span>
                                                    <p class="mt-2 max-w-sm text-xs text-gray-500 dark:text-gray-400">{{ $access['reason'] }}</p>
                                                @else
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">Green roles are compatible, amber roles have another route gate, and red permissions are not currently enforced.</span>
                                                @endif
                                            </td>
                                            @if($selectedRole)
                                                <td class="px-5 py-4 align-top text-xs">
                                                    <span class="font-semibold text-emerald-700 dark:text-emerald-300">+{{ $overrides['grant'] }} grants</span>
                                                    <span class="ml-2 font-semibold text-red-700 dark:text-red-300">-{{ $overrides['deny'] }} denies</span>
                                                </td>
                                            @endif
                                            <td class="px-5 py-4 align-top">
                                                <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $permission->risk_level === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200' : ($permission->risk_level === 'high' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300') }}">{{ $permission->risk_label }}</span>
                                                <p class="mt-2 max-w-xs text-xs text-gray-500 dark:text-gray-400">{{ $permission->risk_reason }}</p>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-100">No permissions match the current filters.</div>
                @endforelse
            @endif
        </div>
    </div>
</x-app-layout>
