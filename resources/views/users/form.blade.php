@php
    $isEdit = isset($user);
    $isSelf = $isEdit && auth()->id() === $user->id;
    $selectedRoleIds = old('roles', $userRoleIds ?? []);
    $organizationRequiredRoles = ['university_administrator', 'college_administrator', 'department_administrator', 'examination_administrator', 'examination_committee'];
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Full name</label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Email address</label>
            <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>

        @unless($isEdit)
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm password</label>
                <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            </div>
        @endunless
    </div>

    <div class="border-t border-gray-200 pt-6">
        <h3 class="text-sm font-semibold text-gray-900">Administrator organization</h3>
        <p class="mt-1 text-xs text-gray-500">Required when assigning a university, college, or department administrator role.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">University</label>
                <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Not assigned</option>
                    @foreach($universities as $university)
                        <option value="{{ $university->id }}" @selected((int) old('university_id', $user->university_id ?? 0) === $university->id)>{{ $university->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">College</label>
                <select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Not assigned</option>
                    @foreach($colleges as $college)
                        <option value="{{ $college->id }}" @selected((int) old('college_id', $user->college_id ?? 0) === $college->id)>{{ $college->name }} / {{ $college->university->name ?? 'University' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Department</label>
                <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Not assigned</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) old('department_id', $user->department_id ?? 0) === $department->id)>{{ $department->name }} / {{ $department->college->name ?? $department->university->name ?? 'Organization' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Assign roles</label>
        <p class="mt-1 text-xs text-gray-500">High-access roles are highlighted. Organization-scoped administrator and examination roles require the matching university, college, or department selection above.</p>
        <div class="mt-4 space-y-5">
            @foreach ($roleGroups as $groupName => $groupRoles)
                <section>
                    <h4 class="text-xs font-semibold uppercase text-gray-500">{{ $groupName }}</h4>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($groupRoles as $role)
                            @php
                                $isSelfSuperAdminRole = $isSelf && $role->name === 'super_administrator' && in_array($role->id, $selectedRoleIds);
                                $requiresOrganization = in_array($role->name, $organizationRequiredRoles, true);
                                $isHighAccess = in_array($role->name, ['super_administrator', 'administrator', 'university_administrator', 'chief_accountant', 'examination_administrator', 'examination_committee'], true);
                            @endphp
                            <label class="flex items-start gap-3 rounded-lg border {{ $isHighAccess ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' }} p-3 hover:border-blue-300">
                                @if($isSelfSuperAdminRole)
                                    <input type="hidden" name="roles[]" value="{{ $role->id }}">
                                @endif
                                <input
                                    type="checkbox"
                                    name="roles[]"
                                    value="{{ $role->id }}"
                                    class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    @checked(in_array($role->id, $selectedRoleIds))
                                    @disabled($isSelfSuperAdminRole)
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800">{{ $role->display_name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $role->description }}</span>
                                    @if($requiresOrganization)
                                        <span class="mt-1 block text-xs font-semibold text-blue-700">Organization scope required</span>
                                    @endif
                                    @if($isSelfSuperAdminRole)
                                        <span class="mt-1 block text-xs font-semibold text-red-700">Protected for current account</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update User' : 'Create User' }}</button>
    </div>
</div>
