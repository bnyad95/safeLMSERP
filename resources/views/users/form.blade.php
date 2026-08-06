@php
    $isEdit = isset($user);
    $isSelf = $isEdit && auth()->id() === $user->id;
    $selectedRoleIds = old('roles', $userRoleIds ?? []);
    $scopeLabels = [
        'university' => 'University scope required',
        'college' => 'College scope required',
        'department' => 'Department scope required',
        'flexible' => 'University, college, or department scope required',
    ];
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-100">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full name</label>
            <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label>
            <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
        </div>

        @unless($isEdit)
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Temporary password</label>
                <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 8 characters with uppercase, lowercase, and a number. The user must replace it after login.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm temporary password</label>
                <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
            </div>
        @endunless
    </div>

    <div class="border-t border-gray-200 pt-6 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Organization scope</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select from broad to specific. The selected role determines which level is stored; unrelated values are removed automatically.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="user-university">University</label>
                <select id="user-university" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    <option value="">Not assigned</option>
                    @foreach($universities as $university)
                        <option value="{{ $university->id }}" @selected((int) old('university_id', $user->university_id ?? 0) === $university->id)>{{ $university->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="user-college">College</label>
                <select id="user-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    <option value="">Not assigned</option>
                    @foreach($colleges as $college)
                        <option value="{{ $college->id }}" data-university-id="{{ $college->university_id }}" @selected((int) old('college_id', $user->college_id ?? 0) === $college->id)>{{ $college->name }} / {{ $college->university?->name ?? 'University' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="user-department">Department</label>
                <select id="user-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    <option value="">Not assigned</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" data-college-id="{{ $department->college_id }}" data-university-id="{{ $department->university_id }}" @selected((int) old('department_id', $user->department_id ?? 0) === $department->id)>{{ $department->name }} / {{ $department->college?->name ?? $department->university?->name ?? 'Organization' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assign roles</label>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Student and Teacher accounts are created from their dedicated directories so their profiles and login accounts stay connected.</p>
        <div class="mt-4 space-y-5">
            @foreach ($roleGroups as $groupName => $groupRoles)
                <section>
                    <h4 class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $groupName }}</h4>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($groupRoles as $role)
                            @php
                                $isSelfSuperAdminRole = $isSelf && $role->name === 'super_administrator' && in_array($role->id, $selectedRoleIds);
                                $roleScope = $organizationRoleScopes[$role->name] ?? null;
                                $isHighAccess = in_array($role->name, ['super_administrator', 'administrator', 'university_administrator', 'chief_accountant', 'examination_administrator', 'examination_committee'], true);
                            @endphp
                            <label class="flex items-start gap-3 rounded-lg border {{ $isHighAccess ? 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950' }} p-3 hover:border-blue-300 dark:hover:border-blue-700">
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
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $role->display_name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $role->description }}</span>
                                    @if($roleScope)
                                        <span class="mt-1 block text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $scopeLabels[$roleScope] }}</span>
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
        <a href="{{ route('users.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ $isEdit ? 'Update User' : 'Create User' }}</button>
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const university = document.getElementById('user-university');
            const college = document.getElementById('user-college');
            const department = document.getElementById('user-department');

            if (! university || ! college || ! department) return;

            const filterOrganizations = (clearInvalid = false) => {
                const universityId = university.value;
                const collegeId = college.value;

                [...college.options].forEach((option) => {
                    if (! option.value) return;
                    option.hidden = Boolean(universityId) && option.dataset.universityId !== universityId;
                });

                if (clearInvalid && college.selectedOptions[0]?.hidden) college.value = '';

                [...department.options].forEach((option) => {
                    if (! option.value) return;
                    option.hidden = (Boolean(universityId) && option.dataset.universityId !== universityId)
                        || (Boolean(college.value) && option.dataset.collegeId !== college.value);
                });

                if (clearInvalid && department.selectedOptions[0]?.hidden) department.value = '';
            };

            university.addEventListener('change', () => filterOrganizations(true));
            college.addEventListener('change', () => filterOrganizations(true));
            filterOrganizations(false);
        });
    </script>
@endonce
