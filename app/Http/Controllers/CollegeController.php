<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;

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
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('colleges.index', compact('colleges', 'canManageColleges'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $universities = $this->scopedUniversities()->get();

        return view('colleges.create', compact('universities'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        $college->update($validated);

        return redirect()->route('colleges.index')->with('success', 'College updated successfully.');
    }

    public function destroy(College $college)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeCollegeScope($college);

        $college->delete();

        return redirect()->route('colleges.index')->with('success', 'College deleted successfully.');
    }

    private function scopedUniversities()
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, auth()->user(), 'university');

        return $query;
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
