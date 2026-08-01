<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $defaultSemesterNames = $this->defaultSemesterNames();

        return view('academic-years.create', compact('universities', 'defaultSemesterNames'));
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
            'semester_names' => ['required', 'string', 'max:255'],
            'first_semester_start_date' => ['nullable', 'date'],
            'semester_length_months' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        [$firstYear, $secondYear] = array_map('intval', explode('/', $validated['academic_year']));
        if ($secondYear !== $firstYear + 1) {
            throw ValidationException::withMessages([
                'academic_year' => 'Use consecutive years in YYYY/YYYY format, for example 2027/2028.',
            ]);
        }

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

        DB::transaction(function () use ($validated, $semesterNames, $startDate, $semesterLength, &$created, &$skipped) {
            foreach ($validated['university_ids'] as $universityId) {
                $yearStartsOn = $startDate?->copy();
                $yearEndsOn = $yearStartsOn && $semesterLength > 0
                    ? $yearStartsOn->copy()->addMonthsNoOverflow($semesterNames->count() * $semesterLength)->subDay()
                    : null;

                $academicYear = AcademicYear::firstOrCreate(
                    [
                        'university_id' => $universityId,
                        'name' => $validated['academic_year'],
                    ],
                    [
                        'starts_on' => $yearStartsOn?->toDateString(),
                        'ends_on' => $yearEndsOn?->toDateString(),
                        'status' => 'active',
                    ]
                );

                foreach ($semesterNames as $index => $name) {
                    $periodStart = $startDate && $semesterLength > 0
                        ? $startDate->copy()->addMonthsNoOverflow($index * $semesterLength)
                        : null;
                    $periodEnd = $periodStart
                        ? $periodStart->copy()->addMonthsNoOverflow($semesterLength)->subDay()
                        : null;

                    $semester = Semester::firstOrCreate(
                        [
                            'university_id' => $universityId,
                            'academic_year' => $validated['academic_year'],
                            'name' => $name,
                        ],
                        [
                            'academic_year_id' => $academicYear->id,
                            'start_date' => $periodStart?->toDateString(),
                            'end_date' => $periodEnd?->toDateString(),
                        ]
                    );

                    if ($semester->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                        if (! $semester->academic_year_id) {
                            $semester->update(['academic_year_id' => $academicYear->id]);
                        }
                    }
                }
            }
        });

        $message = $created > 0
            ? "{$created} semester periods created for {$validated['academic_year']}."
            : "No new semester periods were created for {$validated['academic_year']}; they already exist.";

        if ($skipped > 0) {
            $message .= " {$skipped} duplicate periods were skipped.";
        }

        return redirect()
            ->route('academic-years.index')
            ->with('success', $message.' Existing universities, colleges, departments, and Course Catalog records were reused.');
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

    private function defaultSemesterNames(): string
    {
        $yearQuery = AcademicYear::query()->orderByDesc('name');
        OrganizationScope::apply($yearQuery, auth()->user(), 'academic_year');
        $latestAcademicYear = $yearQuery->value('name');

        if (! $latestAcademicYear) {
            return "Semester 1\nSemester 2";
        }

        $namesQuery = Semester::where('academic_year', $latestAcademicYear);
        OrganizationScope::apply($namesQuery, auth()->user(), 'semester');
        $names = $namesQuery
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
