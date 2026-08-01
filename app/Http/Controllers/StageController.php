<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Department;
use App\Models\Stage;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $departmentId = $request->integer('department_id') ?: null;
        $query = Stage::with('department.college')
            ->withCount('courseSections')
            ->when($departmentId, fn ($query, $id) => $query->where('department_id', $id))
            ->orderBy('department_id')
            ->orderBy('sequence');
        OrganizationScope::apply($query, $user, 'stage');

        $departmentQuery = Department::with('college')->orderBy('name');
        OrganizationScope::apply($departmentQuery, $user, 'department');

        $stages = $query->paginate(20)->withQueryString();
        $departments = $departmentQuery->get(['id', 'name', 'college_id']);
        $canManageStages = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('stages.index', compact('stages', 'departments', 'departmentId', 'canManageStages'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        return view('stages.create', $this->organizationOptions());
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $validated = $this->validateStage($request);
        $department = $this->scopedDepartment((int) $validated['department_id']);
        $university = University::findOrFail($department->university_id);
        $this->ensureStageSequenceWithinInstitutionRule($university, (int) $validated['sequence']);
        unset($validated['college_id']);

        Stage::create($validated + ['university_id' => $department->university_id]);

        return redirect()->route('stages.index')->with('success', 'Stage created successfully.');
    }

    public function edit(Stage $stage)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeStage($stage);

        return view('stages.edit', ['stage' => $stage] + $this->organizationOptions());
    }

    public function update(Request $request, Stage $stage)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeStage($stage);
        $validated = $this->validateStage($request, $stage);
        $department = $this->scopedDepartment((int) $validated['department_id']);
        $university = University::findOrFail($department->university_id);
        $this->ensureStageSequenceWithinInstitutionRule($university, (int) $validated['sequence']);

        if ((int) $stage->department_id !== $department->id && $stage->courseSections()->withTrashed()->exists()) {
            return back()->withInput()->with('error', 'A stage with modules cannot be moved to another department.');
        }

        unset($validated['college_id']);
        DB::transaction(function () use ($stage, $validated, $department) {
            $stage->update($validated + ['university_id' => $department->university_id]);
            $stage->courseSections()->withTrashed()->update(['grade_level' => $stage->name]);
        });

        return redirect()->route('stages.index')->with('success', 'Stage updated successfully.');
    }

    public function destroy(Stage $stage)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeStage($stage);

        if ($stage->courseSections()->withTrashed()->exists()) {
            return back()->with('error', 'This stage is used by modules and cannot be deleted.');
        }

        $stage->delete();

        return redirect()->route('stages.index')->with('success', 'Stage deleted successfully.');
    }

    private function validateStage(Request $request, ?Stage $stage = null): array
    {
        return $request->validate([
            'college_id' => ['required', 'integer', 'exists:colleges,id'],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query->where('college_id', $request->integer('college_id'))->whereNull('deleted_at')),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('stages', 'name')->where(fn ($query) => $query->where('department_id', $request->integer('department_id')))->ignore($stage?->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'sequence' => [
                'required',
                'integer',
                'min:1',
                'max:20',
                Rule::unique('stages', 'sequence')->where(fn ($query) => $query->where('department_id', $request->integer('department_id')))->ignore($stage?->id),
            ],
        ]);
    }

    private function organizationOptions(): array
    {
        $user = auth()->user();
        $collegeQuery = College::orderBy('name');
        $departmentQuery = Department::with('college')->orderBy('name');
        OrganizationScope::apply($collegeQuery, $user, 'college');
        OrganizationScope::apply($departmentQuery, $user, 'department');

        return [
            'colleges' => $collegeQuery->get(['id', 'name']),
            'departments' => $departmentQuery->get(['id', 'name', 'college_id']),
        ];
    }

    private function scopedDepartment(int $departmentId): Department
    {
        $query = Department::whereKey($departmentId);
        OrganizationScope::apply($query, auth()->user(), 'department');

        return $query->firstOrFail();
    }

    private function authorizeStage(Stage $stage): void
    {
        $query = Stage::whereKey($stage->id);
        OrganizationScope::apply($query, auth()->user(), 'stage');
        abort_unless($query->exists(), 403);
    }

    private function ensureStageSequenceWithinInstitutionRule(University $university, int $sequence): void
    {
        $maxStages = $university->expectedStageCount();

        if ($sequence > $maxStages) {
            throw ValidationException::withMessages([
                'sequence' => "{$university->name} is configured as ".($university->isInstitute() ? 'an institute' : 'a university')." and supports only {$maxStages} stages.",
            ]);
        }
    }
}
