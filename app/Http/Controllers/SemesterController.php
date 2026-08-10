<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Semester;
use App\Models\TuitionRate;
use App\Models\University;
use App\Models\User;
use App\Services\TuitionChargeService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SemesterController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $filters = [
            'university_id' => $request->integer('university_id') ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'term_type' => in_array($request->query('term_type'), ['regular', 'summer'], true) ? $request->query('term_type') : '',
        ];
        $query = Semester::with(['university', 'academicYear'])
            ->withCount('courseSections')
            ->when($filters['university_id'], fn ($query, $id) => $query->where('university_id', $id))
            ->when($filters['academic_year_id'], fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['term_type'] !== '', fn ($query) => $query->where('term_type', $filters['term_type']))
            ->orderByDesc('academic_year')
            ->orderBy('sequence');
        OrganizationScope::apply($query, $user, 'semester');
        $semesters = $query->paginate(15);
        $canManageSemesters = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');
        $universities = $this->scopedUniversities()->get(['id', 'name']);
        $academicYears = $this->scopedAcademicYears();

        return view('semesters.index', compact('semesters', 'canManageSemesters', 'universities', 'academicYears', 'filters'));
    }

    public function create(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $universities = $this->scopedUniversities()->get();
        $academicYears = $this->scopedAcademicYears();

        $selectedUniversityId = $request->integer('university_id') ?: null;
        $selectedAcademicYearId = $request->integer('academic_year_id') ?: null;

        return view('semesters.create', compact('universities', 'academicYears', 'selectedUniversityId', 'selectedAcademicYearId'));
    }

    public function createAcademicYear()
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $universities = $this->scopedUniversities()->get();

        return view('academic-years.create', compact('universities'));
    }

    public function editAcademicYear(AcademicYear $academicYear)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeAcademicYearScope($academicYear);

        if (in_array($academicYear->status, ['closed', 'archived'], true)) {
            return redirect()->route('academic-years.index')->with('error', 'Closed academic years cannot be edited.');
        }

        return view('academic-years.edit', compact('academicYear'));
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

    public function showAcademicYear(AcademicYear $academicYear)
    {
        $user = auth()->user();
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);
        $this->authorizeAcademicYearScope($academicYear);

        $academicYear->load('university')->loadCount('semesters');
        $semesters = $academicYear->semesters()
            ->withCount('courseSections')
            ->orderBy('sequence')
            ->orderBy('name')
            ->get();
        $modules = CourseSection::query()
            ->with(['course.department', 'semester', 'teacher', 'stage'])
            ->whereHas('semester', fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->withCount('activeEnrollments')
            ->orderBy('semester_id')
            ->orderBy('section_code')
            ->paginate(20)
            ->withQueryString();
        $canManageAcademicSetup = $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');

        return view('academic-years.show', compact('academicYear', 'semesters', 'modules', 'canManageAcademicSetup'));
    }

    public function storeAcademicYear(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'regex:/^(\d{4})\/(\d{4})$/'],
            'university_ids' => ['required', 'array', 'min:1'],
            'university_ids.*' => ['integer', 'distinct', 'exists:universities,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', Rule::in(['upcoming', 'active'])],
            'generate_semesters' => ['nullable', 'boolean'],
        ]);
        $this->validateConsecutiveAcademicYear($validated['academic_year']);

        if ($validated['status'] === 'active' && empty($validated['generate_semesters'])) {
            throw ValidationException::withMessages([
                'generate_semesters' => 'Generate the regular semesters, or create the year as upcoming and configure its semesters before activation.',
            ]);
        }

        foreach ($validated['university_ids'] as $universityId) {
            $this->authorizeUniversityId((int) $universityId);
            $existingAcademicYearId = AcademicYear::query()
                ->where('university_id', $universityId)
                ->where('name', $validated['academic_year'])
                ->value('id');
            $this->ensureAcademicYearDatesDoNotOverlap(
                (int) $universityId,
                $validated['starts_on'],
                $validated['ends_on'],
                $existingAcademicYearId ? (int) $existingAcademicYearId : null
            );
            if ($validated['status'] === 'active') {
                $this->ensureNoOtherActiveAcademicYear((int) $universityId, $existingAcademicYearId ? (int) $existingAcademicYearId : null);
            }
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($validated, $request, &$created, &$skipped) {
            foreach ($validated['university_ids'] as $universityId) {
                $academicYear = AcademicYear::firstOrCreate(
                    [
                        'university_id' => $universityId,
                        'name' => $validated['academic_year'],
                    ],
                    [
                        'starts_on' => $validated['starts_on'],
                        'ends_on' => $validated['ends_on'],
                        'status' => $validated['status'],
                    ]
                );

                if ($academicYear->wasRecentlyCreated) {
                    $created++;
                    if ($validated['status'] === 'active') {
                        $this->ensureTuitionRatesConfiguredForActivation($academicYear);
                    }
                    if (! empty($validated['generate_semesters'])) {
                        $this->generateRegularSemesters($academicYear, $request->user());
                    }
                } else {
                    $skipped++;
                }
            }
        });

        $message = $created > 0
            ? "{$created} academic year record(s) created for {$validated['academic_year']}."
            : "No new academic year records were created for {$validated['academic_year']}; they already exist.";

        if ($skipped > 0) {
            $message .= " {$skipped} existing records were skipped.";
        }

        return redirect()
            ->route('academic-years.index')
            ->with('success', $message.' Use Semesters to define the periods inside the year.');
    }

    public function updateAcademicYear(Request $request, AcademicYear $academicYear)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeAcademicYearScope($academicYear);

        if (in_array($academicYear->status, ['closed', 'archived'], true)) {
            return redirect()->route('academic-years.index')->with('error', 'Closed academic years cannot be edited.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'regex:/^(\d{4})\/(\d{4})$/',
                Rule::unique('academic_years', 'name')
                    ->where(fn ($query) => $query->where('university_id', $academicYear->university_id))
                    ->ignore($academicYear->id),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'status' => ['required', Rule::in(['upcoming', 'active'])],
        ]);
        $this->validateConsecutiveAcademicYear($validated['name'], 'name');
        $this->ensureAcademicYearDatesDoNotOverlap(
            (int) $academicYear->university_id,
            $validated['starts_on'],
            $validated['ends_on'],
            $academicYear->id
        );
        if ($academicYear->status === 'active' && $validated['status'] === 'upcoming') {
            throw ValidationException::withMessages([
                'status' => 'An active academic year cannot return to upcoming. Close it through Academic Year Closing.',
            ]);
        }
        if ($validated['status'] === 'active') {
            $this->ensureNoOtherActiveAcademicYear((int) $academicYear->university_id, $academicYear->id);
            $this->ensureAcademicYearReadyForActivation($academicYear);
        }
        $this->ensureExistingSemestersFitAcademicYear($academicYear, $validated['starts_on'], $validated['ends_on']);

        $oldName = $academicYear->name;
        if ($oldName !== $validated['name'] && $academicYear->semesters()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'The academic year name is locked after semesters have been created. Dates and status can still be updated.',
            ]);
        }
        $academicYear->update($validated);
        if ($oldName !== $academicYear->name) {
            $academicYear->semesters()->update(['academic_year' => $academicYear->name]);
        }

        return redirect()->route('academic-years.index')->with('success', 'Academic year updated successfully.');
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $validated = $this->validateSemester($request);
        $this->authorizeUniversityId($validated['university_id']);

        $academicYear = $this->resolveAcademicYear($validated);
        $this->ensureAcademicYearWritable($academicYear);
        $validated['academic_year_id'] = $academicYear->id;
        $validated['academic_year'] = $academicYear->name;
        $validated['university_id'] = $academicYear->university_id;
        $this->ensureSemesterDatesWithinAcademicYear($validated, $academicYear);
        $this->ensureSemesterDatesDoNotOverlap($academicYear, $validated['start_date'], $validated['end_date']);
        $this->ensureSemesterStructureIsValid($academicYear, $validated['term_type'], (int) $validated['sequence']);

        $semester = Semester::create($validated);
        app(TuitionChargeService::class)->generateNextInstallmentsForSemester($semester, $request->user());

        return redirect()->route('semesters.index')->with('success', 'Semester created successfully.');
    }

    public function edit(Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);
        $semester->load('academicYear');

        if ($semester->isLocked()) {
            return redirect()->route('academic-years.show', $semester->academicYear)->with('error', 'Semesters in closed academic years are read-only.');
        }

        $universities = $this->scopedUniversities()->get();
        $academicYears = $this->scopedAcademicYears();

        return view('semesters.edit', compact('semester', 'universities', 'academicYears'));
    }

    public function update(Request $request, Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);

        $semester->load('academicYear');
        $this->ensureAcademicYearWritable($semester->academicYear);
        $validated = $this->validateSemester($request, $semester);
        $this->authorizeUniversityId($validated['university_id']);

        $academicYear = $this->resolveAcademicYear($validated);
        $this->ensureAcademicYearWritable($academicYear);

        if ($semester->courseSections()->withTrashed()->exists()
            && ((int) $semester->academic_year_id !== (int) $academicYear->id
                || (int) $semester->university_id !== (int) $academicYear->university_id
                || (int) $semester->sequence !== (int) $validated['sequence']
                || $semester->term_type !== $validated['term_type'])) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'A semester with module offerings cannot change academic year, institution, type, or sequence. Dates and display details can still be updated.',
            ]);
        }

        $validated['academic_year_id'] = $academicYear->id;
        $validated['academic_year'] = $academicYear->name;
        $validated['university_id'] = $academicYear->university_id;
        $this->ensureSemesterDatesWithinAcademicYear($validated, $academicYear);
        $this->ensureSemesterDatesDoNotOverlap($academicYear, $validated['start_date'], $validated['end_date'], $semester->id);
        $this->ensureSemesterStructureIsValid($academicYear, $validated['term_type'], (int) $validated['sequence'], $semester->id);

        $semester->update($validated);

        return redirect()->route('semesters.index')->with('success', 'Semester updated successfully.');
    }

    public function destroy(Semester $semester)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $this->authorizeSemesterScope($semester);
        $semester->load('academicYear');
        $this->ensureAcademicYearWritable($semester->academicYear);

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

        return $query->get(['id', 'university_id', 'name', 'status']);
    }

    private function authorizeUniversityId(int $universityId): void
    {
        $query = University::whereKey($universityId);
        OrganizationScope::apply($query, auth()->user(), 'university');
        abort_unless($query->exists(), 403);
    }

    private function validateSemester(Request $request, ?Semester $semester = null): array
    {
        if (! $request->filled('term_type')) {
            $request->merge(['term_type' => $semester?->term_type ?? 'regular']);
        }
        if (! $request->filled('sequence')) {
            $request->merge(['sequence' => $semester?->sequence ?? $this->nextSemesterSequence($request)]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'term_type' => ['required', Rule::in(['regular', 'summer'])],
            'sequence' => ['required', 'integer', 'min:1', 'max:20'],
            'academic_year_id' => ['nullable', 'integer', 'required_without:academic_year', 'exists:academic_years,id'],
            'academic_year' => ['nullable', 'string', 'required_without:academic_year_id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'university_id' => ['required', 'exists:universities,id'],
        ]);
    }

    private function resolveAcademicYear(array $validated): AcademicYear
    {
        $query = AcademicYear::query()
            ->where('university_id', $validated['university_id']);

        if (! empty($validated['academic_year_id'])) {
            $query->whereKey($validated['academic_year_id']);
        } else {
            $query->where('name', $validated['academic_year']);
        }

        OrganizationScope::apply($query, auth()->user(), 'academic_year');

        return $query->firstOrFail();
    }

    private function nextSemesterSequence(Request $request): int
    {
        $query = Semester::query();
        if ($request->integer('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        } else {
            $query->where('university_id', $request->integer('university_id'))
                ->where('academic_year', $request->input('academic_year'));
        }

        return ((int) $query->max('sequence')) + 1;
    }

    private function ensureSemesterStructureIsValid(AcademicYear $academicYear, string $termType, int $sequence, ?int $ignoredSemesterId = null): void
    {
        $expectedSemesters = $academicYear->university->expectedSemesterCount();
        $query = $academicYear->semesters()->when($ignoredSemesterId, fn ($query) => $query->whereKeyNot($ignoredSemesterId));

        if ((clone $query)->where('sequence', $sequence)->exists()) {
            throw ValidationException::withMessages(['sequence' => 'That semester sequence is already used in this academic year.']);
        }

        if ($termType === 'regular') {
            if ($sequence > $expectedSemesters || (clone $query)->where('term_type', 'regular')->count() >= $expectedSemesters) {
                throw ValidationException::withMessages([
                    'term_type' => "{$academicYear->university->name} supports {$expectedSemesters} regular semesters per academic year.",
                ]);
            }

            return;
        }

        if ((clone $query)->where('term_type', 'summer')->exists()) {
            throw ValidationException::withMessages(['term_type' => 'Only one optional summer semester is allowed per academic year.']);
        }

        if ($sequence !== $expectedSemesters + 1) {
            throw ValidationException::withMessages(['sequence' => 'The summer semester must follow the regular semesters.']);
        }
    }

    private function authorizeAcademicYearScope(AcademicYear $academicYear): void
    {
        $query = AcademicYear::whereKey($academicYear->id);
        OrganizationScope::apply($query, auth()->user(), 'academic_year');
        abort_unless($query->exists(), 403);
    }

    private function validateConsecutiveAcademicYear(string $academicYear, string $field = 'academic_year'): void
    {
        [$firstYear, $secondYear] = array_map('intval', explode('/', $academicYear));
        if ($secondYear !== $firstYear + 1) {
            throw ValidationException::withMessages([
                $field => 'Use consecutive years in YYYY/YYYY format, for example 2027/2028.',
            ]);
        }
    }

    private function ensureAcademicYearDatesDoNotOverlap(int $universityId, string $startsOn, string $endsOn, ?int $ignoredYearId = null): void
    {
        $overlaps = AcademicYear::query()
            ->where('university_id', $universityId)
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->whereDate('starts_on', '<=', $endsOn)
            ->whereDate('ends_on', '>=', $startsOn)
            ->when($ignoredYearId, fn ($query) => $query->whereKeyNot($ignoredYearId))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'The academic-year dates overlap an existing year for one of the selected institutions.',
            ]);
        }
    }

    private function ensureNoOtherActiveAcademicYear(int $universityId, ?int $ignoredYearId = null): void
    {
        $activeYear = AcademicYear::query()
            ->where('university_id', $universityId)
            ->where('status', 'active')
            ->when($ignoredYearId, fn ($query) => $query->whereKeyNot($ignoredYearId))
            ->first();

        if ($activeYear) {
            throw ValidationException::withMessages([
                'status' => "{$activeYear->name} is already active for this university. Close it before activating another year.",
            ]);
        }
    }

    private function ensureAcademicYearWritable(?AcademicYear $academicYear): void
    {
        if (! $academicYear || $academicYear->isLocked()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Closed and archived academic years are read-only.',
            ]);
        }
    }

    private function ensureAcademicYearReadyForActivation(AcademicYear $academicYear): void
    {
        $expected = $academicYear->university->expectedSemesterCount();
        $semesters = $academicYear->semesters()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderBy('start_date')
            ->get();
        $regularSemesters = $semesters->where('term_type', 'regular');

        if ($regularSemesters->count() !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Define exactly {$expected} regular semesters before activating this academic year.",
            ]);
        }

        $expectedSequences = range(1, $expected);
        $actualSequences = $regularSemesters->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->sort()->values()->all();
        if ($actualSequences !== $expectedSequences) {
            throw ValidationException::withMessages([
                'status' => 'Regular semester sequences must be complete and start at 1 before activation.',
            ]);
        }

        $previousSemester = null;
        foreach ($semesters as $semester) {
            if ($previousSemester && $semester->start_date->lte($previousSemester->end_date)) {
                throw ValidationException::withMessages([
                    'status' => 'Semester dates must not overlap before the academic year can be activated.',
                ]);
            }

            $previousSemester = $semester;
        }

        $this->ensureTuitionRatesConfiguredForActivation($academicYear);
    }

    private function ensureTuitionRatesConfiguredForActivation(AcademicYear $academicYear): void
    {
        $departments = Department::where('university_id', $academicYear->university_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($departments->isEmpty()) {
            return;
        }

        $pricedDepartmentIds = TuitionRate::where('academic_year_id', $academicYear->id)
            ->whereIn('department_id', $departments->pluck('id'))
            ->distinct()
            ->pluck('department_id');

        $missingDepartments = $departments->reject(fn (Department $department) => $pricedDepartmentIds->contains($department->id));

        if ($missingDepartments->isEmpty()) {
            return;
        }

        $names = $missingDepartments->pluck('name');
        $list = $names->take(5)->implode(', ');
        if ($names->count() > 5) {
            $list .= ', and '.($names->count() - 5).' more';
        }

        throw ValidationException::withMessages([
            'status' => "Set a tuition rate for every department before activating this academic year. Missing a rate: {$list}.",
        ]);
    }

    private function ensureExistingSemestersFitAcademicYear(AcademicYear $academicYear, string $startsOn, string $endsOn): void
    {
        $outsideRange = $academicYear->semesters()
            ->where(function ($query) use ($startsOn, $endsOn) {
                $query->whereDate('start_date', '<', $startsOn)
                    ->orWhereDate('end_date', '>', $endsOn);
            })
            ->exists();

        if ($outsideRange) {
            throw ValidationException::withMessages([
                'starts_on' => 'The new academic-year dates would place an existing semester outside the year.',
            ]);
        }
    }

    private function ensureSemesterDatesDoNotOverlap(AcademicYear $academicYear, string $startsOn, string $endsOn, ?int $ignoredSemesterId = null): void
    {
        $overlap = $academicYear->semesters()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $endsOn)
            ->whereDate('end_date', '>=', $startsOn)
            ->when($ignoredSemesterId, fn ($query) => $query->whereKeyNot($ignoredSemesterId))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => 'The semester dates overlap another semester in this academic year.',
            ]);
        }
    }

    private function generateRegularSemesters(AcademicYear $academicYear, User $actor): void
    {
        $academicYear->load('university');
        $count = $academicYear->university->expectedSemesterCount();
        $startsOn = Carbon::parse($academicYear->starts_on)->startOfDay();
        $endsOn = Carbon::parse($academicYear->ends_on)->startOfDay();
        $totalDays = ((int) $startsOn->diffInDays($endsOn)) + 1;
        $daysPerSemester = max(1, intdiv($totalDays, $count));
        $tuitionCharges = app(TuitionChargeService::class);

        for ($sequence = 1; $sequence <= $count; $sequence++) {
            $semesterStart = $startsOn->copy()->addDays(($sequence - 1) * $daysPerSemester);
            $semesterEnd = $sequence === $count
                ? $endsOn->copy()
                : $semesterStart->copy()->addDays($daysPerSemester - 1);

            $semester = Semester::create([
                'university_id' => $academicYear->university_id,
                'academic_year_id' => $academicYear->id,
                'academic_year' => $academicYear->name,
                'name' => 'Semester '.$sequence,
                'term_type' => 'regular',
                'sequence' => $sequence,
                'start_date' => $semesterStart->toDateString(),
                'end_date' => $semesterEnd->toDateString(),
            ]);
            $tuitionCharges->generateNextInstallmentsForSemester($semester, $actor);
        }
    }

    private function ensureSemesterDatesWithinAcademicYear(array $semesterData, AcademicYear $academicYear): void
    {
        if (! $academicYear->starts_on || ! $academicYear->ends_on) {
            return;
        }

        if (! empty($semesterData['start_date']) && $semesterData['start_date'] < $academicYear->starts_on->toDateString()) {
            throw ValidationException::withMessages(['start_date' => 'The semester cannot start before its academic year.']);
        }

        if (! empty($semesterData['end_date']) && $semesterData['end_date'] > $academicYear->ends_on->toDateString()) {
            throw ValidationException::withMessages(['end_date' => 'The semester cannot end after its academic year.']);
        }
    }

    private function authorizeSemesterScope(Semester $semester): void
    {
        $query = Semester::whereKey($semester->id);
        OrganizationScope::apply($query, auth()->user(), 'semester');
        abort_unless($query->exists(), 403);
    }
}
