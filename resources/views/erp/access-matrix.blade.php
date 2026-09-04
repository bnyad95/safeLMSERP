<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('Role Access Matrix') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Review assigned permissions, route compatibility, organization scope, and user-level overrides.') }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ __('Back to Dashboard') }}
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
                    <p class="font-semibold">{{ __('The permission change was not saved.') }}</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Access statistics') }}">
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
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Role') }}</span>
                        <select name="role_id" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">{{ __('All roles') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) $filters['role_id'] === $role->id)>{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Module') }}</span>
                        <select name="module" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all">{{ __('All modules') }}</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module['key'] }}" @selected($filters['module'] === $module['key'])>{{ $module['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Assignment') }}</span>
                        <select name="access" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all" @selected($filters['access'] === 'all')>{{ __('All permissions') }}</option>
                            <option value="granted" @selected($filters['access'] === 'granted')>{{ __('Assigned to selected role') }}</option>
                            <option value="missing" @selected($filters['access'] === 'missing')>{{ __('Not assigned to selected role') }}</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Risk') }}</span>
                        <select name="risk" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="all" @selected($filters['risk'] === 'all')>{{ __('All risk levels') }}</option>
                            <option value="critical" @selected($filters['risk'] === 'critical')>{{ __('Critical') }}</option>
                            <option value="high" @selected($filters['risk'] === 'high')>{{ __('High risk') }}</option>
                            <option value="standard" @selected($filters['risk'] === 'standard')>{{ __('Standard') }}</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Mode') }}</span>
                        <select name="mode" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="view" @selected($filters['mode'] === 'view')>{{ __('Review') }}</option>
                            <option value="edit" @selected($filters['mode'] === 'edit')>{{ __('Edit role') }}</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Search') }}</span>
                        <input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Permission name') }}" class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __(':count permissions in view.', ['count' => $permissions->count()]) }}
                        @if ($selectedRole)
                            {{ __('Selected role:') }} <span class="font-medium text-gray-700 dark:text-gray-200">{{ $selectedRole->display_name }}</span>
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <a href="{{ route('access-matrix') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Reset') }}</a>
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('Apply') }}</button>
                    </div>
                </div>
            </form>

            @if ($permissionGroups->isNotEmpty())
                <nav class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-label="{{ __('Jump to module') }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Jump to module') }}</p>
                        <div class="flex gap-2">
                            <button type="button" onclick="document.querySelectorAll('details[id^=module-]').forEach(d => d.open = true)" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Expand all') }}</button>
                            <button type="button" onclick="document.querySelectorAll('details[id^=module-]').forEach(d => d.open = false)" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('Collapse all') }}</button>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($permissionGroups as $moduleLabel => $groupPermissions)
                            <a href="#module-{{ Str::slug($moduleLabel) }}" onclick="const d = document.getElementById('module-{{ Str::slug($moduleLabel) }}'); if (d) d.open = true;" class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">{{ $moduleLabel }}</a>
                        @endforeach
                    </div>
                </nav>
            @endif

            @if ($selectedRoleImpact)
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="role-impact-title">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 id="role-impact-title" class="font-semibold text-gray-900 dark:text-gray-100">{{ __(':role impact', ['role' => $selectedRole->display_name]) }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Role changes affect every active user assigned to this role. Direct user overrides remain unchanged.') }}</p>
                        </div>
                        <a href="{{ route('users.index', ['role_id' => $selectedRole->id]) }}" class="text-sm font-semibold text-blue-700 hover:underline dark:text-blue-300">{{ __('View assigned users') }}</a>
                    </div>
                    <div class="grid divide-y divide-gray-100 dark:divide-gray-800 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Active users') }}</p><p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($selectedRoleImpact['users']) }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Organization scope') }}</p><p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $selectedRoleImpact['scope']['label'] }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $selectedRoleImpact['scope']['detail'] }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Direct grants') }}</p><p class="mt-2 text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ number_format($selectedRoleImpact['direct_grants']) }}</p></div>
                        <div class="p-4"><p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ __('Direct denies') }}</p><p class="mt-2 text-xl font-semibold text-red-700 dark:text-red-300">{{ number_format($selectedRoleImpact['direct_denies']) }}</p></div>
                    </div>
                </section>
            @endif

            @if ($filters['mode'] === 'edit')
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100">
                    {{ __('Checkboxes change the role template. Compatibility labels explain when an additional route role or direct user grant is required.') }}
                </div>

                @if (! $selectedRole)
                    <div class="rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Choose a role to edit') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Select a role above and apply edit mode.') }}</p>
                    </div>
                @elseif ($selectedRole->name === 'super_administrator')
                    <div class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100">
                        {{ __('Super Administrator has implicit full access and cannot be edited from this screen.') }}
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
                            <details id="module-{{ Str::slug($moduleLabel) }}" class="group scroll-mt-24 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" @if($permissionGroups->count() === 1) open @endif>
                                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $moduleLabel }}</h3>
                                    <span class="flex items-center gap-2">
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ __(':count permissions', ['count' => $groupPermissions->count()]) }}</span>
                                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </summary>
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
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Overrides: +:grant / -:deny', ['grant' => $overrides['grant'], 'deny' => $overrides['deny']]) }}</span>
                                                @endif
                                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->name, $selectedPermissionNames, true)) class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950">
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @empty
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-100">{{ __('No permissions match the current filters.') }}</div>
                        @endforelse

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="confirm_permission_change" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950">
                                <span>{{ __('I understand this updates access for :count active users assigned to :role, preserves direct user overrides, and creates an audit log.', ['count' => number_format($selectedRoleImpact['users']), 'role' => $selectedRole->display_name]) }}</span>
                            </label>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400">{{ __('Save Permissions') }}</button>
                            </div>
                        </div>
                    </form>
                @endif
            @else
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-100">
                    {{ __('Review mode separates role assignment from effective route compatibility. Direct grants and denies apply to individual users after role permissions.') }}
                </div>

                @forelse ($permissionGroups as $moduleLabel => $groupPermissions)
                    <details id="module-{{ Str::slug($moduleLabel) }}" class="group scroll-mt-24 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" @if($permissionGroups->count() === 1) open @endif>
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-5 py-4 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $moduleLabel }}</h3>
                            <span class="flex items-center gap-2">
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ __(':count permissions', ['count' => $groupPermissions->count()]) }}</span>
                                <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </summary>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Permission') }}</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ $selectedRole ? __('Role assignment') : __('Assigned / implicit roles') }}</th>
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Compatibility') }}</th>
                                        @if($selectedRole)<th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Direct overrides') }}</th>@endif
                                        <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Risk') }}</th>
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
                                                        <span class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">{{ __('Implicit') }}</span>
                                                    @elseif(in_array($permission->name, $selectedPermissionNames, true))
                                                        <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">{{ __('Assigned') }}</span>
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">{{ __('Not assigned') }}</span>
                                                    @endif
                                                @else
                                                    <div class="flex max-w-md flex-wrap gap-1.5">
                                                        @forelse($displayRoles as $role)
                                                            @php $access = $roleAccess[$permission->id][$role->id]; @endphp
                                                            <span class="rounded-md px-2 py-1 text-xs font-medium {{ $access['status'] === 'conditional' ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' : ($access['status'] === 'unenforced' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200') }}" title="{{ $access['reason'] }}">{{ $role->display_name }}</span>
                                                        @empty
                                                            <span class="text-gray-400 dark:text-gray-500">{{ __('No role assignment') }}</span>
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
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Green roles are compatible, amber roles have another route gate, and red permissions are not currently enforced.') }}</span>
                                                @endif
                                            </td>
                                            @if($selectedRole)
                                                <td class="px-5 py-4 align-top text-xs">
                                                    <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ __('+:count grants', ['count' => $overrides['grant']]) }}</span>
                                                    <span class="ml-2 font-semibold text-red-700 dark:text-red-300">{{ __('-:count denies', ['count' => $overrides['deny']]) }}</span>
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
                    </details>
                @empty
                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-5 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-100">{{ __('No permissions match the current filters.') }}</div>
                @endforelse
            @endif
        </div>
    </div>
</x-app-layout>
