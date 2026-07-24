<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">User Management</h2>
                <p class="text-sm text-gray-600">Manage accounts, roles, organization scope, password resets, and archived users.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('users.archived') }}" class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Archived Users</a>
                <a href="{{ route('users.create') }}" class="inline-flex justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add User</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('users.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700" for="user-search">Search</label>
                        <input id="user-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name or email" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="role_id">Role</label>
                        <select id="role_id" name="role_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) $filters['role_id'] === $role->id)>{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="status">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="verification">Email</label>
                        <select id="verification" name="verification" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Any</option>
                            <option value="verified" @selected($filters['verification'] === 'verified')>Verified</option>
                            <option value="unverified" @selected($filters['verification'] === 'unverified')>Unverified</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                        <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="university_id">University</label>
                        <select id="university_id" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All universities</option>
                            @foreach($universities as $university)
                                <option value="{{ $university->id }}" @selected((int) $filters['university_id'] === $university->id)>{{ $university->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="college_id">College</label>
                        <select id="college_id" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected((int) $filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">User</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Roles</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Organization</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Security</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Last Activity</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($users as $account)
                                <tr class="{{ $account->trashed() ? 'bg-gray-50' : 'bg-white' }}">
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $account->name }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $account->email }}</p>
                                        @if($account->trashed())
                                            <span class="mt-2 inline-flex rounded-md bg-gray-200 px-2 py-1 text-xs font-semibold text-gray-700">Archived</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex max-w-sm flex-wrap gap-1.5">
                                            @forelse($account->roles as $role)
                                                <span class="rounded-md {{ $role->name === 'super_administrator' ? 'bg-red-50 text-red-700' : 'bg-indigo-50 text-indigo-700' }} px-2 py-1 text-xs font-semibold">{{ $role->display_name }}</span>
                                            @empty
                                                <span class="text-sm text-gray-400">No role assigned</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        {{ $account->department->name ?? $account->college->name ?? $account->university->name ?? 'Global' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        <p>{{ $account->email_verified_at ? 'Email verified' : 'Email not verified' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Created {{ $account->created_at?->format('Y-m-d') }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        @if($account->last_activity)
                                            {{ \Illuminate\Support\Carbon::createFromTimestamp($account->last_activity)->diffForHumans() }}
                                        @else
                                            <span class="text-gray-400">No active session</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right text-sm font-medium">
                                        @if($account->trashed())
                                            <form action="{{ route('users.restore', $account->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-blue-700 hover:underline">Restore</button>
                                            </form>
                                        @else
                                            <a href="{{ route('users.edit', $account) }}" class="text-blue-700 hover:underline">Edit</a>
                                            @if(auth()->user()?->hasRole('super_administrator'))
                                                <a href="{{ route('users.permissions.edit', $account) }}" class="ml-3 text-indigo-700 hover:underline">Permissions</a>
                                            @endif
                                            @if(! $account->is(auth()->user()))
                                                <form action="{{ route('users.destroy', $account) }}" method="POST" class="ml-3 inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-700 hover:underline" onclick="return confirm('Archive this user?')">Archive</button>
                                                </form>
                                            @else
                                                <span class="ml-3 text-xs text-gray-400">Current account</span>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">No users match the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($users->hasPages())
                    <div class="border-t border-gray-200 px-5 py-4">
                        {{ $users->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
