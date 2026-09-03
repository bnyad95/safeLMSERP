<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Department;
use App\Models\University;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $query = Department::with(['university', 'college'])->orderBy('name');
        OrganizationScope::apply($query, $user, 'department');
        $departments = $query->paginate(15);
        $canManageDepartments = $user->hasAnyRole(['super_administrator', 'administrator'])
            || ($user->hasDirectPermissionGrant('academic_setup.manage') && ! $user->department_id);

        return view('departments.index', compact('departments', 'canManageDepartments'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCreationScope();

        $universities = $this->scopedUniversities()->get();
        $colleges = $this->scopedColleges()->get();

        return view('departments.create', compact('universities', 'colleges'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCreationScope();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where(fn ($query) => $query->where('college_id', $request->integer('college_id'))->whereNull('deleted_at'))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->where(fn ($query) => $query->where('college_id', $request->integer('college_id'))->whereNull('deleted_at'))],
            'university_id' => ['required', 'exists:universities,id'],
            'college_id' => ['required', 'exists:colleges,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);
        $this->authorizeDepartmentAssignment($validated);

        Department::create($validated);

        return redirect()->route('departments.index')->with('success', __('Department created successfully.'));
    }

    public function edit(Department $department)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeDepartmentScope($department);

        $universities = $this->scopedUniversities()->get();
        $colleges = $this->scopedColleges()->get();

        return view('departments.edit', compact('department', 'universities', 'colleges'));
    }

    public function update(Request $request, Department $department)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeDepartmentScope($department);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where(fn ($query) => $query->where('college_id', $request->integer('college_id'))->whereNull('deleted_at'))->ignore($department->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->where(fn ($query) => $query->where('college_id', $request->integer('college_id'))->whereNull('deleted_at'))->ignore($department->id)],
            'university_id' => ['required', 'exists:universities,id'],
            'college_id' => ['required', 'exists:colleges,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);
        $this->authorizeDepartmentAssignment($validated);

        $parentChanged = (int) $department->university_id !== (int) $validated['university_id']
            || (int) $department->college_id !== (int) $validated['college_id'];
        if ($parentChanged && $this->hasOperationalData($department)) {
            return back()->withInput()->with('error', __('A department with academic records or assigned users cannot be moved to another college or institution.'));
        }

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', __('Department updated successfully.'));
    }

    public function destroy(Department $department)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeDepartmentScope($department);

        if ($this->hasOperationalData($department)) {
            return back()->with('error', __('This department contains academic or historical records and cannot be archived.'));
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', __('Department deleted successfully.'));
    }

    private function hasOperationalData(Department $department): bool
    {
        return $department->students()->withTrashed()->exists()
            || $department->teachers()->withTrashed()->exists()
            || $department->courses()->withTrashed()->exists()
            || $department->stages()->exists()
            || User::withTrashed()->where('department_id', $department->id)->exists();
    }

    private function authorizeCreationScope(): void
    {
        $user = auth()->user();
        abort_if(! $user->hasAnyRole(['super_administrator', 'administrator']) && $user->department_id, 403);
    }

    private function scopedUniversities()
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, auth()->user(), 'university');

        return $query;
    }

    private function scopedColleges()
    {
        $query = College::orderBy('name');
        OrganizationScope::apply($query, auth()->user(), 'college');

        return $query;
    }

    private function authorizeUniversityId(int $universityId): void
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }

    private function authorizeDepartmentAssignment(array $validated): void
    {
        $user = auth()->user();

        if ($user->hasRole('college_administrator')) {
            abort_unless((int) ($validated['college_id'] ?? 0) === (int) $user->college_id, 403);
        }

        if (! empty($validated['college_id'])) {
            $college = College::findOrFail($validated['college_id']);
            $this->authorizeCollegeScope($college);
            abort_unless((int) $college->university_id === (int) $validated['university_id'], 422);
        }
    }

    private function authorizeCollegeScope(College $college): void
    {
        $query = College::whereKey($college->id);
        OrganizationScope::apply($query, auth()->user(), 'college');
        abort_unless($query->exists(), 403);
    }

    private function authorizeDepartmentScope(Department $department): void
    {
        $query = Department::whereKey($department->id);
        OrganizationScope::apply($query, auth()->user(), 'department');
        abort_unless($query->exists(), 403);
    }
}
