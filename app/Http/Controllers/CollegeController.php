<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\University;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollegeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $query = College::with('university')->orderBy('name');
        OrganizationScope::apply($query, $user, 'college');
        $colleges = $query->paginate(15);
        $canManageColleges = $user->hasAnyRole(['super_administrator', 'administrator'])
            || ($user->hasDirectPermissionGrant('academic_setup.manage') && ! $user->college_id && ! $user->department_id);

        return view('colleges.index', compact('colleges', 'canManageColleges'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCreationScope();

        $universities = $this->scopedUniversities()->get();

        return view('colleges.create', compact('universities'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCreationScope();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('colleges', 'name')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('colleges', 'code')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        College::create($validated);

        return redirect()->route('colleges.index')->with('success', 'College created successfully.');
    }

    public function edit(College $college)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCollegeScope($college);

        $universities = $this->scopedUniversities()->get();

        return view('colleges.edit', compact('college', 'universities'));
    }

    public function update(Request $request, College $college)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCollegeScope($college);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('colleges', 'name')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))->ignore($college->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('colleges', 'code')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))->ignore($college->id)],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        if ((int) $college->university_id !== (int) $validated['university_id']
            && ($college->departments()->withTrashed()->exists()
                || User::withTrashed()->where('college_id', $college->id)->exists())) {
            return back()->withInput()->with('error', 'A college with departments or assigned users cannot be moved to another institution.');
        }

        $college->update($validated);

        return redirect()->route('colleges.index')->with('success', 'College updated successfully.');
    }

    public function destroy(College $college)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCollegeScope($college);

        if ($college->departments()->withTrashed()->exists()
            || User::withTrashed()->where('college_id', $college->id)->exists()) {
            return back()->with('error', 'This college contains departments or assigned users and cannot be deleted.');
        }

        $college->delete();

        return redirect()->route('colleges.index')->with('success', 'College deleted successfully.');
    }

    private function scopedUniversities()
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, auth()->user(), 'university');

        return $query;
    }

    private function authorizeCreationScope(): void
    {
        $user = auth()->user();
        abort_if(! $user->hasAnyRole(['super_administrator', 'administrator']) && ($user->college_id || $user->department_id), 403);
    }

    private function authorizeUniversityId(int $universityId): void
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }

    private function authorizeCollegeScope(College $college): void
    {
        $query = College::whereKey($college->id);
        OrganizationScope::apply($query, auth()->user(), 'college');
        abort_unless($query->exists(), 403);
    }
}
