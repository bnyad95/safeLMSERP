<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SemesterController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $query = Semester::with('university')->orderByDesc('academic_year')->orderBy('name');
        OrganizationScope::apply($query, $user, 'semester');
        $semesters = $query->paginate(15);
        $canManageSemesters = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('semesters.index', compact('semesters', 'canManageSemesters'));
    }

    public function create()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $universities = $this->scopedUniversities()->get();
        $academicYears = $this->scopedAcademicYears();

        return view('semesters.create', compact('universities', 'academicYears'));
    }

    public function createAcademicYear()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $universities = $this->scopedUniversities()->get();

        return view('academic-years.create', compact('universities'));
    }

    public function academicYears()
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $query = AcademicYear::with('university')
            ->withCount('semesters')
            ->selectSub(function ($query) {
                $query->from('course_sections')
                    ->join('semesters', 'semesters.id', '=', 'course_sections.semester_id')
                    ->whereColumn('semesters.academic_year_id', 'academic_years.id')
                    ->whereNull('course_sections.deleted_at')
                    ->selectRaw('COUNT(*)');
            }, 'modules_count')
            ->orderByDesc('name');
        OrganizationScope::apply($query, $user, 'academic_year');

        $academicYears = $query->paginate(15);
        $canManageAcademicSetup = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('academic-years.index', compact('academicYears', 'canManageAcademicSetup'));
    }

    public function storeAcademicYear(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^(\d{4})\/(\d{4})$/'],
            'university_ids' => ['required', 'array', 'min:1'],
            'university_ids.*' => ['integer', 'distinct', 'exists:universities,id'],
        ]);

        [$firstYear, $secondYear] = array_map('intval', explode('/', $validated['academic_year']));
        if ($secondYear !== $firstYear + 1) {
            throw ValidationException::withMessages([
                'academic_year' => 'Use consecutive years in YYYY/YYYY format, for example 2027/2028.',
            ]);
        }

        foreach ($validated['university_ids'] as $universityId) {
            $this->authorizeUniversityId((int) $universityId);
        }

        $created = 0;
        $skipped = 0;
        foreach ($validated['university_ids'] as $universityId) {
            $academicYear = AcademicYear::firstOrCreate(
                [
                    'university_id' => $universityId,
                    'name' => $validated['academic_year'],
                ],
                [
                    'status' => 'active',
                ]
            );

            if ($academicYear->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $message = $created > 0
            ? "{$created} academic year record(s) created for {$validated['academic_year']}."
            : "No new academic year records were created for {$validated['academic_year']}; they already exist.";

        if ($skipped > 0) {
            $message .= " {$skipped} existing records were skipped.";
        }

        return redirect()
            ->route('academic-years.index')
            ->with('success', $message.' Use Semesters to define the institution semester structure (8 for universities, 4 for institutes).');
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'string', Rule::exists('academic_years', 'name')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        $academicYear = AcademicYear::where('university_id', $validated['university_id'])
            ->where('name', $validated['academic_year'])
            ->firstOrFail();

        $this->ensureSemesterCountWithinInstitutionRule(
            (int) $validated['university_id'],
            $validated['academic_year']
        );

        Semester::create($validated + ['academic_year_id' => $academicYear->id]);

        return redirect()->route('semesters.index')->with('success', 'Semester created successfully.');
    }

    public function edit(Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);

        $universities = $this->scopedUniversities()->get();
        $academicYears = $this->scopedAcademicYears();

        return view('semesters.edit', compact('semester', 'universities', 'academicYears'));
    }

    public function update(Request $request, Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'string', Rule::exists('academic_years', 'name')->where(fn ($query) => $query->where('university_id', $request->integer('university_id')))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        $academicYear = AcademicYear::where('university_id', $validated['university_id'])
            ->where('name', $validated['academic_year'])
            ->firstOrFail();

        $this->ensureSemesterCountWithinInstitutionRule(
            (int) $validated['university_id'],
            $validated['academic_year'],
            $semester->id
        );

        $semester->update($validated + ['academic_year_id' => $academicYear->id]);

        return redirect()->route('semesters.index')->with('success', 'Semester updated successfully.');
    }

    public function destroy(Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);

        if ($semester->courseSections()->withTrashed()->exists()) {
            return back()->with('error', 'This semester has modules and historical records. Close its academic year instead of deleting it.');
        }

        $semester->delete();

        return redirect()->route('semesters.index')->with('success', 'Semester deleted successfully.');
    }

    private function scopedUniversities()
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, auth()->user(), 'university');

        return $query;
    }

    private function scopedAcademicYears()
    {
        $query = AcademicYear::query()->orderByDesc('name');
        OrganizationScope::apply($query, auth()->user(), 'academic_year');

        return $query->pluck('name')->filter()->values();
    }

    private function authorizeUniversityId(int $universityId): void
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }

    private function ensureSemesterCountWithinInstitutionRule(int $universityId, string $academicYear, ?int $ignoredSemesterId = null): void
    {
        $university = University::findOrFail($universityId);
        $expectedSemesters = $university->isInstitute() ? 4 : 8;

        $semesterCount = Semester::query()
            ->where('university_id', $universityId)
            ->where('academic_year', $academicYear)
            ->when($ignoredSemesterId, fn ($query) => $query->whereKeyNot($ignoredSemesterId))
            ->count();

        if ($semesterCount >= $expectedSemesters) {
            throw ValidationException::withMessages([
                'name' => "{$university->name} supports {$expectedSemesters} semesters per academic year.",
            ]);
        }
    }

    private function authorizeSemesterScope(Semester $semester): void
    {
        $query = Semester::whereKey($semester->id);
        OrganizationScope::apply($query, auth()->user(), 'semester');
        abort_unless($query->exists(), 403);
    }
}
