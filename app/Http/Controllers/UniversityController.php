<?php

namespace App\Http\Controllers;

use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UniversityController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $query = University::orderBy('name');
        OrganizationScope::apply($query, $user, 'university');
        $universities = $query->paginate(15);
        $canManageUniversities = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('universities.index', compact('universities', 'canManageUniversities'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        return view('universities.create');
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:universities,code'],
            'institution_type' => ['required', Rule::in(['university', 'institute'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $validated = array_merge($validated, University::structureForType($validated['institution_type']));

        University::create($validated);

        return redirect()->route('universities.index')->with('success', 'University created successfully.');
    }

    public function edit(University $university)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeUniversityScope($university);

        return view('universities.edit', compact('university'));
    }

    public function update(Request $request, University $university)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeUniversityScope($university);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('universities', 'code')->ignore($university->id)],
            'institution_type' => ['required', Rule::in(['university', 'institute'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $structure = University::structureForType($validated['institution_type']);
        if ($university->institution_type !== $validated['institution_type']
            && $university->stages()->where('sequence', '>', $structure['expected_stage_count'])->exists()) {
            throw ValidationException::withMessages([
                'institution_type' => "This organization has stages beyond Stage {$structure['expected_stage_count']}. Remove or reassign their module offerings before changing the institution type.",
            ]);
        }

        $validated = array_merge($validated, $structure);

        $university->update($validated);

        return redirect()->route('universities.index')->with('success', 'University updated successfully.');
    }

    public function destroy(University $university)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeUniversityScope($university);

        $hasAcademicData = $university->colleges()->exists()
            || $university->departments()->withTrashed()->exists()
            || $university->semesters()->exists()
            || $university->students()->withTrashed()->exists()
            || $university->teachers()->withTrashed()->exists()
            || $university->academicYears()->exists();

        if ($hasAcademicData) {
            return back()->with('error', 'This university contains academic or historical records and cannot be deleted.');
        }

        $university->delete();

        return redirect()->route('universities.index')->with('success', 'University deleted successfully.');
    }

    private function authorizeUniversityScope(University $university): void
    {
        $query = University::whereKey($university->id);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }
}
