<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\RolePermissionService;
use App\Support\UserRolePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAnyUserManagement(['users.create', 'users.update', 'users.assign_roles', 'users.reset_password']);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'role_id' => $request->integer('role_id') ?: null,
            'university_id' => $request->integer('university_id') ?: null,
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'status' => in_array($request->query('status'), ['active', 'blocked', 'archived', 'all'], true) ? $request->query('status') : 'active',
            'verification' => in_array($request->query('verification'), ['verified', 'unverified'], true) ? $request->query('verification') : '',
        ];

        $directoryQuery = User::with(['roles', 'permissionOverrides', 'university', 'college', 'department'])
            ->when($filters['status'] === 'archived', fn ($query) => $query->onlyTrashed())
            ->when($filters['status'] === 'all', fn ($query) => $query->withTrashed())
            ->when($filters['status'] === 'active', fn ($query) => $query->whereNull('account_blocked_at'))
            ->when($filters['status'] === 'blocked', fn ($query) => $query->whereNotNull('account_blocked_at'))
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('name', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['role_id'], fn ($query) => $query->whereHas('roles', fn ($role) => $role->whereKey($filters['role_id'])))
            ->when($filters['university_id'], fn ($query) => $query->where('university_id', $filters['university_id']))
            ->when($filters['college_id'], fn ($query) => $query->where('college_id', $filters['college_id']))
            ->when($filters['department_id'], fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['verification'] === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verification'] === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy('name');

        $users = $directoryQuery->paginate(15)->withQueryString();
        $tracksLastActivity = config('session.driver') === 'database';
        $lastActivities = $tracksLastActivity
            ? DB::table(config('session.table', 'sessions'))
                ->select('user_id', DB::raw('MAX(last_activity) as last_activity'))
                ->whereIn('user_id', $users->pluck('id'))
                ->groupBy('user_id')
                ->pluck('last_activity', 'user_id')
            : collect();
        $users->getCollection()->each(function (User $user) use ($lastActivities) {
            $user->setAttribute('last_activity', $lastActivities[$user->id] ?? null);
            $user->setAttribute('can_be_managed', $this->canManageUser($user));
            $user->setAttribute('profile_managed', $this->isProfileManagedUser($user));
        });

        $roles = Role::orderBy('display_name')->get();
        [$universities, $colleges, $departments] = $this->organizationOptions();
        $stats = [
            ['label' => 'Available Accounts', 'value' => number_format(User::whereNull('account_blocked_at')->count()), 'detail' => 'Not archived or blocked'],
            ['label' => 'Blocked Accounts', 'value' => number_format(User::whereNotNull('account_blocked_at')->count()), 'detail' => 'Login currently blocked'],
            ['label' => 'Archived Users', 'value' => number_format(User::onlyTrashed()->count()), 'detail' => 'Soft-deleted accounts'],
            ['label' => 'Super Admins', 'value' => number_format(User::whereHas('roles', fn ($role) => $role->where('name', 'super_administrator'))->count()), 'detail' => 'Highest access accounts'],
            ['label' => 'Unverified Email', 'value' => number_format(User::whereNull('email_verified_at')->count()), 'detail' => 'Email not verified'],
        ];
        $abilities = $this->userManagementAbilities();
        $organizationRoleScopes = UserRolePolicy::organizationRoleScopes();

        return view('users.index', compact('users', 'filters', 'roles', 'universities', 'colleges', 'departments', 'stats', 'abilities', 'tracksLastActivity', 'organizationRoleScopes'));
    }

    public function create()
    {
        $this->authorizeUserManagement('users.create');

        $roles = $this->manageableRoles();
        $roleGroups = $this->roleGroups($roles);
        [$universities, $colleges, $departments] = $this->organizationOptions();
        $organizationRoleScopes = UserRolePolicy::organizationRoleScopes();

        return view('users.create', compact('roles', 'roleGroups', 'universities', 'colleges', 'departments', 'organizationRoleScopes'));
    }

    public function store(Request $request, RolePermissionService $rolePermissionService)
    {
        $this->authorizeUserManagement('users.create');
        $this->authorizeUserManagement('users.assign_roles');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'university_id' => ['nullable', 'integer', Rule::exists('universities', 'id')],
            'college_id' => ['nullable', 'integer', Rule::exists('colleges', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);

        $roleIds = $this->validatedRoleIds($validated['roles']);
        $this->validateRoleCombination($roleIds);
        unset($validated['roles']);
        $validated = array_merge($validated, $this->normalizedOrganization($roleIds, $validated));

        $user = DB::transaction(function () use ($validated, $roleIds, $rolePermissionService) {
            $user = new User($validated);
            $user->email_verified_at = now();
            $user->must_change_password = true;
            $user->save();
            $rolePermissionService->syncUserRoles($user, $roleIds);

            return $user;
        });

        if (auth()->user()?->hasRole('super_administrator') && ! $user->hasRole('super_administrator')) {
            return redirect()
                ->route('users.permissions.edit', $user)
                ->with('success', 'User created. Review the effective permissions before finishing.');
        }

        return redirect()->route('users.index')->with('success', 'User created and role assigned successfully.');
    }

    public function edit(User $user)
    {
        $this->authorizeAnyUserManagement(['users.update', 'users.reset_password']);
        $this->authorizeCanManageUser($user);

        $profileManaged = $this->isProfileManagedUser($user);
        $roles = $this->manageableRoles($user);
        $roleGroups = $this->roleGroups($roles);
        $userRoleIds = $user->roles()->pluck('roles.id')->all();
        [$universities, $colleges, $departments] = $this->organizationOptions();
        $organizationRoleScopes = UserRolePolicy::organizationRoleScopes();
        $abilities = $this->userManagementAbilities();

        return view('users.edit', compact('user', 'roles', 'roleGroups', 'userRoleIds', 'universities', 'colleges', 'departments', 'organizationRoleScopes', 'profileManaged', 'abilities'));
    }

    public function update(Request $request, User $user, RolePermissionService $rolePermissionService)
    {
        $this->authorizeUserManagement('users.update');
        $this->authorizeUserManagement('users.assign_roles');
        $this->authorizeCanManageUser($user);
        abort_if($this->isProfileManagedUser($user), 403, 'Teacher and student account details are managed from their profile workspace.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            'university_id' => ['nullable', 'integer', Rule::exists('universities', 'id')],
            'college_id' => ['nullable', 'integer', Rule::exists('colleges', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);

        $roleIds = $this->validatedRoleIds($validated['roles'], $user);
        $this->validateRoleCombination($roleIds, $user);
        unset($validated['roles']);
        $this->protectOwnSuperAdminRole($user, $roleIds);

        $validated = array_merge($validated, $this->normalizedOrganization($roleIds, $validated));

        DB::transaction(function () use ($user, $validated, $roleIds, $rolePermissionService) {
            $user->update($validated);
            $rolePermissionService->syncUserRoles($user, $roleIds);
        });

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->authorizeUserManagement('users.reset_password');
        $this->authorizeCanManageUser($user);

        $validated = $request->validate([
            'password' => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ]);
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        return redirect()->route('users.edit', $user)->with('success', 'Password reset successfully.');
    }

    public function editPermissions(User $user)
    {
        $this->authorizeSuperAdmin();
        abort_if($user->hasRole('super_administrator'), 403, 'Super Administrator permissions are always inherited and cannot be overridden.');

        $user->load(['roles.permissions', 'permissionOverrides']);
        $permissions = Permission::orderBy('name')->get();
        $permissionGroups = $permissions->groupBy(fn (Permission $permission) => $this->permissionModuleLabel($permission->name));
        $rolePermissionIds = $user->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('id')
            ->unique()
            ->values();
        $overrideEffects = $user->permissionOverrides
            ->mapWithKeys(fn (Permission $permission) => [$permission->id => $permission->pivot->effect]);
        $effectivePermissionIds = $permissions
            ->filter(function (Permission $permission) use ($rolePermissionIds, $overrideEffects) {
                $effect = $overrideEffects[$permission->id] ?? null;

                if ($effect === 'grant') {
                    return true;
                }

                if ($effect === 'deny') {
                    return false;
                }

                return $rolePermissionIds->contains($permission->id);
            })
            ->pluck('id')
            ->values()
            ->all();

        return view('users.permissions', compact(
            'user',
            'permissionGroups',
            'rolePermissionIds',
            'overrideEffects',
            'effectivePermissionIds'
        ));
    }

    public function updatePermissions(Request $request, User $user, RolePermissionService $rolePermissionService)
    {
        $this->authorizeSuperAdmin();
        abort_if($user->hasRole('super_administrator'), 403, 'Super Administrator permissions are always inherited and cannot be overridden.');

        $validated = $request->validate([
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $selectedIds = collect($validated['permission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $user->load('roles.permissions');
        $rolePermissionIds = $user->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('id')
            ->unique()
            ->values();
        $allPermissionIds = Permission::pluck('id');
        $overrides = [];

        foreach ($allPermissionIds as $permissionId) {
            $hasByRole = $rolePermissionIds->contains($permissionId);
            $isSelected = $selectedIds->contains($permissionId);

            if ($isSelected && ! $hasByRole) {
                $overrides[$permissionId] = ['effect' => 'grant'];
            }

            if (! $isSelected && $hasByRole) {
                $overrides[$permissionId] = ['effect' => 'deny'];
            }
        }

        DB::transaction(fn () => $rolePermissionService->syncUserPermissionOverrides($user, $overrides));

        return redirect()
            ->route('users.index')
            ->with('success', 'User permissions updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->authorizeSuperAdmin();
        abort_if($user->is(auth()->user()), 403, 'You cannot archive your own account.');
        abort_if($this->isProfileManagedUser($user), 403, 'Archive Student and Teacher accounts from their profile workspace.');

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User archived successfully.');
    }

    public function archived()
    {
        request()->merge(['status' => 'archived']);

        return $this->index(request());
    }

    public function restore(int $userId)
    {
        $this->authorizeSuperAdmin();

        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();

        return redirect()->route('users.archived')->with('success', 'User restored successfully.');
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('super_administrator'), 403);
    }

    private function authorizeUserManagement(string|array $permissions): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        $permissions = (array) $permissions;
        $hasEveryPermission = collect($permissions)->every(fn (string $permission) => $user->hasPermission($permission));

        abort_unless($user->hasRole('it_support') && $hasEveryPermission, 403);
    }

    private function authorizeAnyUserManagement(string|array $permissions): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        abort_unless($user->hasRole('it_support') && $user->hasAnyPermission((array) $permissions), 403);
    }

    private function authorizeCanManageUser(User $user): void
    {
        abort_unless($this->canManageUser($user), 403);
    }

    private function canManageUser(User $user): bool
    {
        $actor = auth()->user();

        if ($actor?->hasRole('super_administrator')) {
            return true;
        }

        if (! $actor?->hasRole('it_support')) {
            return false;
        }

        if ($user->roles->pluck('name')->intersect(UserRolePolicy::HIGH_RISK_ROLES)->isNotEmpty()) {
            return false;
        }

        return ! $user->permissionOverrides
            ->contains(fn (Permission $permission) => $permission->pivot->effect === 'grant');
    }

    private function manageableRoles(?User $target = null)
    {
        $roles = Role::orderBy('display_name')->get();
        $existingRoleNames = $target?->roles()->pluck('name')->all() ?? [];

        if (auth()->user()?->hasRole('super_administrator')) {
            return $roles
                ->reject(fn (Role $role) => in_array($role->name, UserRolePolicy::PROFILE_MANAGED_ROLES, true)
                    && ! in_array($role->name, $existingRoleNames, true))
                ->values();
        }

        return $roles
            ->filter(fn (Role $role) => in_array($role->name, UserRolePolicy::IT_SUPPORT_ASSIGNABLE_ROLES, true)
                || in_array($role->name, array_intersect($existingRoleNames, UserRolePolicy::PROFILE_MANAGED_ROLES), true))
            ->values();
    }

    private function validatedRoleIds(array $roleIds, ?User $target = null): array
    {
        $roleIds = array_map('intval', $roleIds);
        $selectedRoles = Role::whereIn('id', $roleIds)->get(['id', 'name']);

        if ($selectedRoles->count() !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages(['roles' => 'One or more selected roles are invalid.']);
        }

        if (auth()->user()?->hasRole('super_administrator')) {
            return $roleIds;
        }

        $existingProfileRoleNames = $target
            ? $target->roles()->whereIn('name', UserRolePolicy::PROFILE_MANAGED_ROLES)->pluck('name')->all()
            : [];
        $allowedRoleNames = array_merge(UserRolePolicy::IT_SUPPORT_ASSIGNABLE_ROLES, $existingProfileRoleNames);
        $blocked = $selectedRoles->pluck('name')->diff($allowedRoleNames)->isNotEmpty();

        abort_if($blocked, 403);

        return $roleIds;
    }

    private function organizationOptions(): array
    {
        return [
            University::orderBy('name')->get(),
            College::with('university')->orderBy('name')->get(),
            Department::with(['university', 'college'])->orderBy('name')->get(),
        ];
    }

    private function normalizedOrganization(array $roleIds, array $input): array
    {
        $roleNames = Role::whereIn('id', $roleIds)->pluck('name')->all();
        $requirement = UserRolePolicy::organizationRequirement($roleNames);

        if ($requirement === 'department') {
            if (empty($input['department_id'])) {
                throw ValidationException::withMessages(['department_id' => 'A department is required for the selected role.']);
            }

            $department = Department::withoutGlobalScopes()->with('college')->findOrFail($input['department_id']);

            return [
                'university_id' => $department->university_id,
                'college_id' => $department->college_id,
                'department_id' => $department->id,
            ];
        }

        if ($requirement === 'college') {
            if (empty($input['college_id'])) {
                throw ValidationException::withMessages(['college_id' => 'A college is required for a college administrator.']);
            }

            $college = College::withoutGlobalScopes()->findOrFail($input['college_id']);

            return [
                'university_id' => $college->university_id,
                'college_id' => $college->id,
                'department_id' => null,
            ];
        }

        if ($requirement === 'university') {
            if (empty($input['university_id'])) {
                throw ValidationException::withMessages(['university_id' => 'A university is required for a university administrator.']);
            }

            $university = University::withoutGlobalScopes()->findOrFail($input['university_id']);

            return [
                'university_id' => $university->id,
                'college_id' => null,
                'department_id' => null,
            ];
        }

        if ($requirement === 'flexible') {
            if (! empty($input['department_id'])) {
                $department = Department::withoutGlobalScopes()->with('college')->findOrFail($input['department_id']);

                return [
                    'university_id' => $department->university_id,
                    'college_id' => $department->college_id,
                    'department_id' => $department->id,
                ];
            }

            if (! empty($input['college_id'])) {
                $college = College::withoutGlobalScopes()->findOrFail($input['college_id']);

                return [
                    'university_id' => $college->university_id,
                    'college_id' => $college->id,
                    'department_id' => null,
                ];
            }

            if (! empty($input['university_id'])) {
                $university = University::withoutGlobalScopes()->findOrFail($input['university_id']);

                return [
                    'university_id' => $university->id,
                    'college_id' => null,
                    'department_id' => null,
                ];
            }

            throw ValidationException::withMessages(['university_id' => 'An organization scope is required for the selected role.']);
        }

        return ['university_id' => null, 'college_id' => null, 'department_id' => null];
    }

    private function protectOwnSuperAdminRole(User $user, array $roleIds): void
    {
        if (! $user->is(auth()->user())) {
            return;
        }

        $hasSuperAdmin = Role::whereIn('id', $roleIds)->where('name', 'super_administrator')->exists();

        if (! $hasSuperAdmin) {
            throw ValidationException::withMessages(['roles' => 'You cannot remove your own super administrator role.']);
        }
    }

    private function validateRoleCombination(array $roleIds, ?User $target = null): void
    {
        $roleNames = Role::whereIn('id', $roleIds)->pluck('name');
        $profileRoleNames = $roleNames->intersect(UserRolePolicy::PROFILE_MANAGED_ROLES);

        if (! $target && $profileRoleNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Create Student accounts from Student Records and Teacher accounts from Teachers.',
            ]);
        }

        if ($target) {
            $existingProfileRoleNames = $target->roles()->whereIn('name', UserRolePolicy::PROFILE_MANAGED_ROLES)->pluck('name');
            if ($profileRoleNames->sort()->values()->all() !== $existingProfileRoleNames->sort()->values()->all()) {
                throw ValidationException::withMessages(['roles' => 'Student and Teacher roles are managed from their profile workspace.']);
            }
        }

        if ($roleNames->intersect(UserRolePolicy::EXCLUSIVE_ROLES)->isNotEmpty() && $roleNames->count() > 1) {
            throw ValidationException::withMessages(['roles' => 'Portal roles cannot be combined with another role.']);
        }
    }

    private function isProfileManagedUser(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles->pluck('name')->intersect(UserRolePolicy::PROFILE_MANAGED_ROLES)->isNotEmpty();
        }

        return $user->roles()->whereIn('name', UserRolePolicy::PROFILE_MANAGED_ROLES)->exists();
    }

    private function userManagementAbilities(): array
    {
        $user = auth()->user();
        $isSuper = $user?->hasRole('super_administrator') ?? false;

        return [
            'create' => $isSuper || ($user?->hasRole('it_support') && $user->hasPermission('users.create') && $user->hasPermission('users.assign_roles')),
            'update' => $isSuper || ($user?->hasRole('it_support') && $user->hasPermission('users.update') && $user->hasPermission('users.assign_roles')),
            'reset_password' => $isSuper || ($user?->hasRole('it_support') && $user->hasPermission('users.reset_password')),
            'archive' => $isSuper,
            'permissions' => $isSuper,
        ];
    }

    private function roleGroups($roles)
    {
        $labels = [
            'system' => 'System',
            'academic' => 'Academic Administration',
            'learning' => 'Learning & Results',
            'finance' => 'Finance',
            'support' => 'Support & Read-only',
        ];

        return $roles
            ->groupBy(fn (Role $role) => match (true) {
                in_array($role->name, ['super_administrator', 'it_support'], true) => 'system',
                in_array($role->name, ['administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'university_president', 'registrar', 'admission_officer', 'hr_manager'], true) => 'academic',
                in_array($role->name, ['teacher', 'teaching_assistant', 'lms_administrator', 'examination_administrator', 'examination_committee'], true) => 'learning',
                in_array($role->name, ['accountant', 'chief_accountant'], true) => 'finance',
                default => 'support',
            })
            ->mapWithKeys(fn ($groupRoles, string $key) => [$labels[$key] => $groupRoles]);
    }

    private function permissionModuleLabel(string $permissionName): string
    {
        return match (str($permissionName)->before('.')->toString()) {
            'students' => 'Students',
            'teachers' => 'Teachers',
            'courses' => 'Courses',
            'enrollments' => 'Enrollments',
            'timetable' => 'Timetable',
            'attendance' => 'Attendance',
            'marks' => 'Learning & Results',
            'finance' => 'Finance',
            'lms' => 'LMS',
            'reports' => 'Reports',
            'users' => 'Users & Access',
            'academic_setup' => 'Academic Setup',
            default => 'Other',
        };
    }
}
