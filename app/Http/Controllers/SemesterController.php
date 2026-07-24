<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $defaultSemesterNames = $this->defaultSemesterNames();

        return view('academic-years.create', compact('universities', 'defaultSemesterNames'));
    }

    public function storeAcademicYear(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'university_ids' => ['required', 'array', 'min:1'],
            'university_ids.*' => ['integer', 'distinct', 'exists:universities,id'],
            'semester_names' => ['required', 'string', 'max:255'],
            'first_semester_start_date' => ['nullable', 'date'],
            'semester_length_months' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $semesterNames = collect(preg_split('/[\r\n,]+/', $validated['semester_names']))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($semesterNames->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['semester_names' => 'Add at least one semester name.']);
        }

        foreach ($validated['university_ids'] as $universityId) {
            $this->authorizeUniversityId((int) $universityId);
        }

        $created = 0;
        $skipped = 0;
        $startDate = filled($validated['first_semester_start_date'] ?? null)
            ? Carbon::parse($validated['first_semester_start_date'])->startOfDay()
            : null;
        $semesterLength = (int) ($validated['semester_length_months'] ?? 0);

        foreach ($validated['university_ids'] as $universityId) {
            foreach ($semesterNames as $index => $name) {
                $existing = Semester::where('university_id', $universityId)
                    ->where('academic_year', $validated['academic_year'])
                    ->where('name', $name)
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                $periodStart = $startDate && $semesterLength > 0
                    ? $startDate->copy()->addMonthsNoOverflow($index * $semesterLength)
                    : null;
                $periodEnd = $periodStart
                    ? $periodStart->copy()->addMonthsNoOverflow($semesterLength)->subDay()
                    : null;

                Semester::create([
                    'university_id' => $universityId,
                    'name' => $name,
                    'academic_year' => $validated['academic_year'],
                    'start_date' => $periodStart?->toDateString(),
                    'end_date' => $periodEnd?->toDateString(),
                ]);

                $created++;
            }
        }

        $message = $created > 0
            ? "{$created} semester periods created for {$validated['academic_year']}."
            : "No new semester periods were created for {$validated['academic_year']}; they already exist.";

        if ($skipped > 0) {
            $message .= " {$skipped} duplicate periods were skipped.";
        }

        return redirect()
            ->route('bologna-definition')
            ->with('success', $message.' Existing universities, colleges, departments, and course names were reused.');
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        Semester::create($validated);

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
            'academic_year' => ['required', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
        $this->authorizeUniversityId($validated['university_id']);

        $semester->update($validated);

        return redirect()->route('semesters.index')->with('success', 'Semester updated successfully.');
    }

    public function destroy(Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);

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
        $query = Semester::query()
            ->whereNotNull('academic_year')
            ->distinct()
            ->orderByDesc('academic_year');
        OrganizationScope::apply($query, auth()->user(), 'semester');

        return $query->pluck('academic_year')->filter()->values();
    }

    private function defaultSemesterNames(): string
    {
        $latestAcademicYear = Semester::query()
            ->orderByDesc('academic_year')
            ->value('academic_year');

        if (! $latestAcademicYear) {
            return "Semester 1\nSemester 2";
        }

        $names = Semester::where('academic_year', $latestAcademicYear)
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();

        return $names->isNotEmpty()
            ? $names->join("\n")
            : "Semester 1\nSemester 2";
    }

    private function authorizeUniversityId(int $universityId): void
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }

    private function authorizeSemesterScope(Semester $semester): void
    {
        $query = Semester::whereKey($semester->id);
        OrganizationScope::apply($query, auth()->user(), 'semester');
        abort_unless($query->exists(), 403);
    }
}
