<?php

namespace App\Http\Controllers;

use App\Models\AcademicYearClosure;
use App\Models\AssessmentItem;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\ClassStreamPost;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Timetable;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearClosureController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $academicYears = Semester::query()
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->filter()
            ->values();

        $selectedYear = $request->query('academic_year', $academicYears->first());
        $summary = $selectedYear ? $this->buildSummary($selectedYear) : $this->emptySummary();

        return view('academic-year-closures.index', [
            'academicYears' => $academicYears,
            'selectedYear' => $selectedYear,
            'summary' => $summary,
            'canManageClosure' => $this->canManageClosure($request),
        ]);
    }

    public function archive(Request $request)
    {
        $this->authorizeArchiveView($request);

        $closures = $this->archiveClosures($request->user());
        $years = $this->archiveYears($closures, $request->user());

        return view('academic-year-closures.archive', [
            'years' => $years,
            'closures' => $closures,
        ]);
    }

    public function archiveYear(Request $request)
    {
        $this->authorizeArchiveView($request);

        return view('academic-year-closures.archive-year', $this->archiveYearData($request));
    }

    public function exportArchiveYear(Request $request)
    {
        $this->authorizeArchiveView($request);

        $data = $this->archiveYearData($request, false);
        $filename = 'academic-year-archive-results-'.str($data['academicYear'])->replace(['/', '\\', ' '], '-')->toString().'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Academic Year',
                'Student ID',
                'Student',
                'College',
                'Department',
                'Stage',
                'Semester',
                'Module Code',
                'Module',
                'Group',
                'Final Mark',
                'Result',
            ]);

            if ($data['resultRows']->isNotEmpty()) {
                foreach ($data['resultRows'] as $mark) {
                    $section = $mark->courseSection;
                    $course = $mark->course ?? $section?->course;
                    $studentDepartment = $mark->student?->department;
                    $courseDepartment = $section?->course?->department;
                    $department = $studentDepartment ?? $courseDepartment;
                    $college = $studentDepartment?->college ?? $courseDepartment?->college;
                    $semester = $section?->semester;
                    $passed = (float) $mark->final_mark >= 50;

                    fputcsv($handle, [
                        $data['academicYear'],
                        $mark->student?->student_id,
                        $mark->student?->full_name,
                        $college?->name,
                        $department?->name,
                        $section?->grade_level ?: 'No stage',
                        $semester ? trim($semester->name.' '.$semester->academic_year) : $data['academicYear'],
                        $course?->code,
                        $course?->name,
                        $section?->section_code,
                        number_format((float) $mark->final_mark, 2, '.', ''),
                        $passed ? 'Passed' : 'Failed',
                    ]);
                }
            } else {
                foreach ($data['snapshotResultRows'] as $row) {
                    fputcsv($handle, [
                        $data['academicYear'],
                        $row['student_number'] ?? '',
                        $row['student_name'] ?? '',
                        $row['college'] ?? '',
                        $row['department'] ?? '',
                        $row['stage'] ?? 'No stage',
                        trim(($row['semester'] ?? '').' '.($row['academic_year'] ?? '')) ?: $data['academicYear'],
                        $row['course_code'] ?? '',
                        $row['course_name'] ?? '',
                        $row['group'] ?? '',
                        number_format((float) ($row['final_mark'] ?? 0), 2, '.', ''),
                        $row['result'] ?? ((float) ($row['final_mark'] ?? 0) >= 50 ? 'Passed' : 'Failed'),
                    ]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function archiveYearData(Request $request, bool $limitResults = true): array
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string'],
            'q' => ['nullable', 'string', 'max:120'],
            'college_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'stage' => ['nullable', 'string'],
            'semester_id' => ['nullable', 'integer'],
            'result_status' => ['nullable', Rule::in(['passed', 'failed'])],
            'sort' => ['nullable', Rule::in(['final_desc', 'final_asc', 'student_asc', 'student_desc', 'course_asc'])],
        ]);

        $academicYear = $validated['academic_year'];
        $q = trim((string) ($validated['q'] ?? ''));
        $semesterQuery = Semester::where('academic_year', $academicYear);
        $this->scopeArchiveQuery($semesterQuery, $request->user(), 'semester');
        $semesterIds = $semesterQuery->pluck('id');
        $closureSummary = $this->archiveSummaryForYear($academicYear, $request->user());
        $usingSnapshotFallback = $semesterIds->isEmpty() && ! empty($closureSummary);
        $archiveSnapshot = $closureSummary['archive_snapshot'] ?? [];

        $optionsQuery = $this->archivedModulesForYear($semesterIds, $request->user());
        $optionSections = $optionsQuery
            ->orderBy('semester_id')
            ->orderBy('grade_level')
            ->orderBy('section_code')
            ->limit(1000)
            ->get();

        $collegeId = $validated['college_id'] ?? null;
        $departmentId = $validated['department_id'] ?? null;
        $stage = $validated['stage'] ?? null;
        $semesterId = $validated['semester_id'] ?? null;
        $resultStatus = $validated['result_status'] ?? null;
        $sort = $validated['sort'] ?? 'final_desc';

        $filteredSectionQuery = $this->archivedModulesForYear($semesterIds, $request->user())
            ->when($collegeId, fn ($query) => $query->whereHas('course.department', fn ($department) => $department->where('college_id', $collegeId)))
            ->when($departmentId, fn ($query) => $query->whereHas('course', fn ($course) => $course->where('department_id', $departmentId)))
            ->when($stage, fn ($query) => $query->where('grade_level', $stage))
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($q !== '', fn ($query) => $this->applyArchiveSectionSearch($query, $q));
        $sectionIds = (clone $filteredSectionQuery)
            ->withoutEagerLoads()
            ->pluck('id');

        $sections = (clone $filteredSectionQuery)
            ->withCount([
                'enrollments as enrollment_count',
                'marks',
                'assessmentItems',
            ])
            ->orderBy('semester_id')
            ->orderBy('grade_level')
            ->orderBy('section_code')
            ->when($limitResults, fn ($query) => $query->limit(500))
            ->get();
        $resultBaseQuery = Mark::with([
                'student.department.college',
                'course.department.college',
                'courseSection.semester',
                'courseSection.course.department.college',
            ])
            ->where('visibility_status', 'published')
            ->whereNotNull('final_mark');

        if ($sectionIds->isNotEmpty()) {
            $resultBaseQuery->whereIn('course_section_id', $sectionIds);
        } elseif ($usingSnapshotFallback) {
            $resultBaseQuery->whereNull('course_section_id');
        } else {
            $resultBaseQuery->whereRaw('1 = 0');
        }

        $this->scopeArchiveMarkQuery($resultBaseQuery, $request->user());
        $this->applyArchiveResultSearch($resultBaseQuery, $q);

        $resultStats = [
            'published' => (clone $resultBaseQuery)->count(),
            'passed' => (clone $resultBaseQuery)->where('final_mark', '>=', 50)->count(),
            'failed' => (clone $resultBaseQuery)->where('final_mark', '<', 50)->count(),
        ];

        $databaseStudentResultStats = $this->studentArchiveResultStats($resultBaseQuery);
        $studentIdsForDisplay = $this->studentArchiveResultStudentIds(
            $resultBaseQuery,
            $resultStatus,
            $sort,
            $limitResults ? 500 : null
        );
        $studentResultSourceRows = $studentIdsForDisplay->isNotEmpty()
            ? $this->markRowsForStudentArchive((clone $resultBaseQuery)->whereIn('student_id', $studentIdsForDisplay)->get())
            : collect();

        $resultRows = (clone $resultBaseQuery)
            ->when($resultStatus === 'passed', fn ($query) => $query->where('final_mark', '>=', 50))
            ->when($resultStatus === 'failed', fn ($query) => $query->where('final_mark', '<', 50));

        match ($sort) {
            'final_asc' => $resultRows->orderBy('final_mark')->orderBy('id'),
            'student_asc' => $resultRows
                ->join('students', 'marks.student_id', '=', 'students.id')
                ->select('marks.*')
                ->orderBy('students.full_name')
                ->orderByDesc('marks.final_mark'),
            'student_desc' => $resultRows
                ->join('students', 'marks.student_id', '=', 'students.id')
                ->select('marks.*')
                ->orderByDesc('students.full_name')
                ->orderByDesc('marks.final_mark'),
            'course_asc' => $resultRows
                ->join('courses', 'marks.course_id', '=', 'courses.id')
                ->select('marks.*')
                ->orderBy('courses.name')
                ->orderByDesc('marks.final_mark'),
            default => $resultRows->orderByDesc('final_mark')->orderBy('id'),
        };

        if ($limitResults) {
            $resultRows->limit(500);
        }

        $resultRows = $resultRows->get();
        $snapshotModules = $this->snapshotModulesForArchive($archiveSnapshot, $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
        ]);
        $snapshotResultRows = $this->snapshotResultsForArchive($archiveSnapshot, $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
            'result_status' => $resultStatus,
            'sort' => $sort,
        ]);
        $snapshotStudentSourceRows = $this->snapshotResultsForArchive($archiveSnapshot, $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
            'result_status' => null,
            'sort' => $sort,
        ]);

        if ($resultRows->isEmpty() && $snapshotResultRows->isNotEmpty()) {
            $snapshotPublishedResults = $this->snapshotResultsForArchive($archiveSnapshot, $request->user(), [
                'q' => $q,
                'college_id' => $collegeId,
                'department_id' => $departmentId,
                'stage' => $stage,
                'semester_id' => $semesterId,
                'result_status' => null,
                'sort' => $sort,
            ]);

            $resultStats = [
                'published' => $snapshotPublishedResults->count(),
                'passed' => $snapshotPublishedResults->where('result', 'Passed')->count(),
                'failed' => $snapshotPublishedResults->where('result', 'Failed')->count(),
            ];
        }

        $studentResultRows = $this->studentResultsForArchive(
            $studentResultSourceRows->isNotEmpty() ? $studentResultSourceRows : $snapshotStudentSourceRows,
            $resultStatus,
            $sort
        );
        $studentResultStats = $databaseStudentResultStats['students'] > 0 || $snapshotStudentSourceRows->isEmpty()
            ? $databaseStudentResultStats
            : [
                'students' => $studentResultRows->count(),
                'passed' => $studentResultRows->where('result', 'Passed')->count(),
                'failed' => $studentResultRows->where('result', 'Failed')->count(),
            ];

        $groupedSections = $sections
            ->groupBy(fn (CourseSection $section) => $section->course?->department?->college?->name ?? 'No college')
            ->map(fn ($collegeSections) => $collegeSections
                ->groupBy(fn (CourseSection $section) => $section->course?->department?->name ?? 'No department')
                ->map(fn ($departmentSections) => $departmentSections
                    ->groupBy(fn (CourseSection $section) => $section->grade_level ?: 'No stage')
                    ->map(fn ($stageSections) => $stageSections
                        ->groupBy(fn (CourseSection $section) => trim(($section->semester?->name ?? 'Semester').' '.($section->semester?->academic_year ?? ''))))));
        $filteredEnrollmentCount = $sectionIds->isNotEmpty()
            ? Enrollment::whereIn('course_section_id', $sectionIds)->count()
            : 0;
        $filteredMarksCount = $sectionIds->isNotEmpty()
            ? Mark::whereIn('course_section_id', $sectionIds)->count()
            : 0;
        $summaryStats = [
            'modules' => $sectionIds->count() ?: ($snapshotModules->count() ?: ($usingSnapshotFallback ? (int) ($closureSummary['section_count'] ?? $closureSummary['archived_modules'] ?? 0) : 0)),
            'enrollments' => $filteredEnrollmentCount ?: ($this->snapshotCountForUser($archiveSnapshot, 'enrollments', $request->user()) ?: ($usingSnapshotFallback ? (int) ($closureSummary['enrollment_count'] ?? 0) : 0)),
            'marks' => $filteredMarksCount ?: ($this->snapshotCountForUser($archiveSnapshot, 'marks', $request->user()) ?: ($usingSnapshotFallback ? (int) ($closureSummary['entered_marks'] ?? $closureSummary['published_marks'] ?? 0) : 0)),
        ];
        $snapshotCoverage = [
            ['label' => 'Modules', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'modules', $request->user())],
            ['label' => 'Roster Rows', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'enrollments', $request->user())],
            ['label' => 'Marks', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'marks', $request->user())],
            ['label' => 'Assessments', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'assessments', $request->user())],
            ['label' => 'Attendance', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'attendance', $request->user())],
            ['label' => 'Timetable', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'timetable', $request->user())],
            ['label' => 'Materials', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'materials', $request->user())],
            ['label' => 'Stream Posts', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'stream_posts', $request->user())],
            ['label' => 'Class Messages', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'class_messages', $request->user())],
            ['label' => 'Finance Records', 'value' => $this->snapshotCountForUser($archiveSnapshot, 'finance_transactions', $request->user())],
        ];

        return [
            'academicYear' => $academicYear,
            'sections' => $sections,
            'summaryStats' => $summaryStats,
            'usingSnapshotFallback' => $usingSnapshotFallback,
            'snapshotCoverage' => $snapshotCoverage,
            'snapshotModules' => $snapshotModules,
            'snapshotResultRows' => $snapshotResultRows,
            'groupedSections' => $groupedSections,
            'colleges' => $optionSections->pluck('course.department.college')->filter()->unique('id')->sortBy('name')->values(),
            'departments' => $optionSections->pluck('course.department')->filter()->unique('id')->sortBy('name')->values(),
            'stages' => $optionSections->pluck('grade_level')->filter()->unique()->sort()->values(),
            'semesters' => $optionSections->pluck('semester')->filter()->unique('id')->sortBy('name')->values(),
            'resultRows' => $resultRows,
            'resultStats' => $resultStats,
            'studentResultRows' => $studentResultRows,
            'studentResultStats' => $studentResultStats,
            'filters' => [
                'q' => $q,
                'college_id' => $collegeId,
                'department_id' => $departmentId,
                'stage' => $stage,
                'semester_id' => $semesterId,
                'result_status' => $resultStatus,
                'sort' => $sort,
            ],
        ];
    }

    public function archiveYearShortcut(Request $request, string $academicYear)
    {
        $params = $request->except('academic_year');
        $params['academic_year'] = urldecode($academicYear);

        return redirect()->route('academic-year-closures.archive.show', $params);
    }

    public function rebuildArchiveSummaries(?string $academicYear = null, bool $force = false): array
    {
        $years = AcademicYearClosure::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year', $academicYear))
            ->distinct()
            ->orderBy('academic_year')
            ->pluck('academic_year');

        $rebuilt = 0;
        $skipped = 0;

        foreach ($years as $year) {
            $closures = AcademicYearClosure::where('academic_year', $year)->get();
            $needsRebuild = $closures->contains(function (AcademicYearClosure $closure) {
                $summary = $closure->summary ?? [];

                return empty($summary['archive_snapshot'] ?? null);
            });

            if (! $force && ! $needsRebuild) {
                $skipped += $closures->count();

                continue;
            }

            $summary = $this->buildSummary($year);

            AcademicYearClosure::where('academic_year', $year)->update([
                'summary' => $summary['snapshot'],
            ]);

            $rebuilt += $closures->count();
        }

        return [
            'years' => $years->count(),
            'rebuilt' => $rebuilt,
            'skipped' => $skipped,
        ];
    }

    public function store(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);

        $academicYears = Semester::query()->distinct()->pluck('academic_year')->filter()->values()->all();

        $validated = $request->validate([
            'academic_year' => ['required', 'string', Rule::in($academicYears)],
            'confirm_results' => ['accepted'],
            'confirm_finance' => ['accepted'],
        ]);

        $summary = $this->buildSummary($validated['academic_year']);

        if ($summary['blockers_count'] > 0) {
            return back()
                ->withInput()
                ->with('error', 'Academic year cannot be closed until missing and unpublished results are completed.');
        }

        DB::transaction(function () use ($request, $summary, $validated) {
            if ($summary['section_ids']->isNotEmpty()) {
                $this->archiveCurrentEnrollments($request, $summary);

                CourseSection::whereIn('id', $summary['section_ids'])
                    ->whereIn('status', ['planned', 'active'])
                    ->update(['status' => 'closed']);

                CourseSection::whereIn('id', $summary['section_ids'])
                    ->whereNull('deleted_at')
                    ->delete();
            }

            $closureSummary = $this->buildSummary($validated['academic_year']);

            foreach ($closureSummary['university_ids'] as $universityId) {
                AcademicYearClosure::updateOrCreate(
                    [
                        'university_id' => $universityId,
                        'academic_year' => $validated['academic_year'],
                    ],
                    [
                        'status' => 'closed',
                        'closed_by' => $request->user()->id,
                        'closed_at' => now(),
                        'summary' => $closureSummary['snapshot'],
                    ]
                );
            }
        });

        return redirect()
            ->route('academic-year-closures.index', ['academic_year' => $validated['academic_year']])
            ->with('success', 'Academic year closed. Current rosters were completed, waitlists were cleared, old modules were archived, and finance balances remain visible for follow-up.');
    }

    private function buildSummary(string $academicYear): array
    {
        $semesters = Semester::with('university')
            ->where('academic_year', $academicYear)
            ->orderBy('name')
            ->get();

        $semesterIds = $semesters->pluck('id');
        $universityIds = $semesters->pluck('university_id')->unique()->values();

        $sections = CourseSection::withTrashed()
            ->with(['course.department.college', 'semester.university'])
            ->withCount(['activeEnrollments', 'marks'])
            ->whereIn('semester_id', $semesterIds)
            ->orderBy('semester_id')
            ->orderBy('section_code')
            ->get();

        $sectionIds = $sections->pluck('id');
        $resultEnrollmentQuery = Enrollment::whereIn('course_section_id', $sectionIds)->whereIn('status', ['enrolled', 'completed']);
        $markQuery = Mark::whereIn('course_section_id', $sectionIds);
        $financeQuery = FinanceTransaction::where('academic_year', $academicYear)
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled');

        $enrollmentsCount = (clone $resultEnrollmentQuery)->count();
        $studentCount = (clone $resultEnrollmentQuery)->distinct('student_id')->count('student_id');
        $enteredMarks = (clone $markQuery)->count();
        $missingMarks = max(0, $enrollmentsCount - $enteredMarks);
        $unpublishedMarks = (clone $markQuery)->where('visibility_status', 'draft')->count();
        $publishedMarks = (clone $markQuery)->where('visibility_status', 'published')->count();
        $openFinanceInvoices = (clone $financeQuery)->whereIn('payment_status', ['open', 'partial', 'overdue'])->count();
        $openFinanceByCurrency = (clone $financeQuery)
            ->whereIn('payment_status', ['open', 'partial', 'overdue'])
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(fn (FinanceTransaction $row) => [
                'currency' => $row->currency,
                'total' => (float) $row->total,
            ]);

        $closures = AcademicYearClosure::with(['university', 'closedBy'])
            ->where('academic_year', $academicYear)
            ->get();
        $archiveSnapshot = $this->buildArchiveSnapshot($academicYear, $sections);

        $blockers = collect([
            [
                'label' => 'Missing result rows',
                'count' => $missingMarks,
                'detail' => 'Every enrolled student/module needs a result record before closure.',
            ],
            [
                'label' => 'Unpublished marks',
                'count' => $unpublishedMarks,
                'detail' => 'Draft marks must be reviewed and published before the year is closed.',
            ],
        ])->filter(fn (array $blocker) => $blocker['count'] > 0)->values();

        $warnings = collect([
            [
                'label' => 'Open tuition invoices',
                'count' => $openFinanceInvoices,
                'detail' => 'These balances stay open after closure so finance can continue follow-up.',
            ],
            [
                'label' => 'Open modules',
                'count' => $sections->filter(fn (CourseSection $section) => ! $section->trashed() && in_array($section->status, ['planned', 'active'], true))->count(),
                'detail' => 'Closing will mark planned and active modules as closed, then move them to archived modules.',
            ],
        ])->filter(fn (array $warning) => $warning['count'] > 0)->values();

        return [
            'academic_year' => $academicYear,
            'universities' => $semesters->pluck('university.name')->filter()->unique()->values(),
            'university_ids' => $universityIds,
            'semester_count' => $semesters->count(),
            'section_count' => $sections->count(),
            'section_ids' => $sectionIds,
            'student_count' => $studentCount,
            'enrollment_count' => $enrollmentsCount,
            'published_marks' => $publishedMarks,
            'entered_marks' => $enteredMarks,
            'missing_marks' => $missingMarks,
            'unpublished_marks' => $unpublishedMarks,
            'open_finance_invoices' => $openFinanceInvoices,
            'open_finance_by_currency' => $openFinanceByCurrency,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'blockers_count' => $blockers->sum('count'),
            'unarchived_section_count' => $sections->whereNull('deleted_at')->count(),
            'is_closed' => $closures->isNotEmpty(),
            'closures' => $closures,
            'recent_sections' => $sections->take(8),
            'snapshot' => [
                'semester_count' => $semesters->count(),
                'section_count' => $sections->count(),
                'student_count' => $studentCount,
                'enrollment_count' => $enrollmentsCount,
                'published_marks' => $publishedMarks,
                'entered_marks' => $enteredMarks,
                'open_finance_invoices' => $openFinanceInvoices,
                'open_finance_by_currency' => $openFinanceByCurrency->all(),
                'archived_modules' => $sections->whereNotNull('deleted_at')->count(),
                'archived_module_ids' => $sections->whereNotNull('deleted_at')->pluck('id')->values()->all(),
                'archive_snapshot' => $archiveSnapshot,
            ],
        ];
    }

    private function buildArchiveSnapshot(string $academicYear, $sections): array
    {
        $sectionIds = $sections->pluck('id')->filter()->values();

        if ($sectionIds->isEmpty()) {
            return [
                'version' => 2,
                'captured_at' => now()->toIso8601String(),
                'modules' => [],
                'enrollments' => [],
                'marks' => [],
                'assessments' => [],
                'attendance' => [],
                'timetable' => [],
                'materials' => [],
                'stream_posts' => [],
                'class_messages' => [],
                'finance_transactions' => [],
            ];
        }

        $moduleSnapshots = CourseSection::withTrashed()
            ->with(['course.department.college.university', 'course.university', 'semester.university', 'teacher.department'])
            ->withCount(['enrollments', 'marks', 'assessmentItems', 'attendances', 'materials', 'streamPosts', 'messages', 'timetables'])
            ->whereIn('id', $sectionIds)
            ->orderBy('semester_id')
            ->orderBy('grade_level')
            ->orderBy('section_code')
            ->get()
            ->map(function (CourseSection $section) {
                $course = $section->course;
                $department = $course?->department;
                $college = $department?->college;
                $university = $section->semester?->university ?? $course?->university ?? $college?->university;
                $teacher = $section->teacher;

                return [
                    'course_section_id' => $section->id,
                    'course_id' => $section->course_id,
                    'course_code' => $course?->code,
                    'course_name' => $course?->name,
                    'college_id' => $college?->id,
                    'college' => $college?->name,
                    'department_id' => $department?->id,
                    'department' => $department?->name,
                    'stage' => $section->grade_level,
                    'semester_id' => $section->semester_id,
                    'semester' => $section->semester?->name,
                    'academic_year' => $section->semester?->academic_year,
                    'group' => $section->section_code,
                    'teacher' => $teacher?->full_name,
                    'capacity' => $section->capacity,
                    'status' => $section->status,
                    'is_archived' => $section->trashed(),
                    'archived_at' => $section->deleted_at?->toIso8601String(),
                    'details' => [
                        'university' => [
                            'id' => $university?->id,
                            'name' => $university?->name,
                            'code' => $university?->code,
                        ],
                        'college' => [
                            'id' => $college?->id,
                            'name' => $college?->name,
                            'code' => $college?->code,
                        ],
                        'department' => [
                            'id' => $department?->id,
                            'name' => $department?->name,
                            'code' => $department?->code,
                        ],
                        'course' => [
                            'id' => $course?->id,
                            'code' => $course?->code,
                            'name' => $course?->name,
                            'credits' => $course?->credits,
                            'status' => $course?->status,
                        ],
                        'semester' => [
                            'id' => $section->semester?->id,
                            'name' => $section->semester?->name,
                            'academic_year' => $section->semester?->academic_year,
                            'start_date' => $section->semester?->start_date,
                            'end_date' => $section->semester?->end_date,
                        ],
                        'teacher' => [
                            'id' => $teacher?->id,
                            'staff_id' => $teacher?->staff_id,
                            'name' => $teacher?->full_name,
                            'email' => $teacher?->email,
                            'title' => $teacher?->title,
                            'department' => $teacher?->department?->name,
                        ],
                        'module' => [
                            'group' => $section->section_code,
                            'stage' => $section->grade_level,
                            'capacity' => $section->capacity,
                            'status' => $section->status,
                            'students_can_post_stream' => (bool) $section->students_can_post_stream,
                            'notes' => $section->notes,
                            'created_at' => $section->created_at?->toIso8601String(),
                            'updated_at' => $section->updated_at?->toIso8601String(),
                            'archived_at' => $section->deleted_at?->toIso8601String(),
                        ],
                    ],
                    'counts' => [
                        'enrollments' => $section->enrollments_count,
                        'marks' => $section->marks_count,
                        'assessments' => $section->assessment_items_count,
                        'attendance' => $section->attendances_count,
                        'materials' => $section->materials_count,
                        'stream_posts' => $section->stream_posts_count,
                        'class_messages' => $section->messages_count,
                        'timetable' => $section->timetables_count,
                    ],
                ];
            })
            ->values()
            ->all();

        $enrollments = Enrollment::with(['student.department.college', 'courseSection'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('student_id')
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'enrollment_id' => $enrollment->id,
                'course_section_id' => $enrollment->course_section_id,
                'student_id' => $enrollment->student_id,
                'student_number' => $enrollment->student?->student_id,
                'student_name' => $enrollment->student?->full_name,
                'student_email' => $enrollment->student?->email,
                'college_id' => $enrollment->student?->department?->college?->id,
                'college' => $enrollment->student?->department?->college?->name,
                'department_id' => $enrollment->student?->department?->id,
                'department' => $enrollment->student?->department?->name,
                'status' => $enrollment->status,
                'is_retake' => (bool) $enrollment->is_retake,
                'enrolled_at' => $enrollment->enrolled_at?->format('Y-m-d'),
                'dropped_at' => $enrollment->dropped_at?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $marks = Mark::with(['student.department.college', 'course.department.college', 'courseSection.semester'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('student_id')
            ->get()
            ->map(fn (Mark $mark) => $this->archiveMarkSnapshot($mark))
            ->values()
            ->all();

        $assessments = AssessmentItem::with(['creator'])
            ->withCount(['submissions'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('due_at')
            ->get()
            ->map(fn (AssessmentItem $assessment) => [
                'assessment_item_id' => $assessment->id,
                'course_section_id' => $assessment->course_section_id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'max_score' => (float) $assessment->max_score,
                'weight_percent' => (float) $assessment->weight_percent,
                'status' => $assessment->status,
                'opens_at' => $assessment->opens_at?->toIso8601String(),
                'due_at' => $assessment->due_at?->toIso8601String(),
                'created_by' => $assessment->creator?->name,
                'submissions_count' => $assessment->submissions_count,
            ])
            ->values()
            ->all();

        $attendance = Attendance::with(['student'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('date')
            ->orderBy('course_section_id')
            ->get()
            ->map(fn (Attendance $attendance) => [
                'attendance_id' => $attendance->id,
                'course_section_id' => $attendance->course_section_id,
                'student_id' => $attendance->student_id,
                'student_number' => $attendance->student?->student_id,
                'student_name' => $attendance->student?->full_name,
                'date' => $attendance->date?->format('Y-m-d'),
                'status' => $attendance->status,
                'remarks' => $attendance->remarks,
            ])
            ->values()
            ->all();

        $timetable = Timetable::with(['teacher', 'classroom'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Timetable $entry) => [
                'timetable_id' => $entry->id,
                'course_section_id' => $entry->course_section_id,
                'teacher' => $entry->teacher?->full_name,
                'classroom' => $entry->classroom?->name,
                'room_number' => $entry->room_number,
                'day_of_week' => $entry->day_of_week,
                'start_time' => $entry->start_time,
                'end_time' => $entry->end_time,
                'type' => $entry->type,
                'status' => $entry->status,
            ])
            ->values()
            ->all();

        $materials = CourseMaterial::with(['uploadedBy'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('title')
            ->get()
            ->map(fn (CourseMaterial $material) => [
                'course_material_id' => $material->id,
                'course_section_id' => $material->course_section_id,
                'title' => $material->title,
                'description' => $material->description,
                'file_path' => $material->file_path,
                'file_type' => $material->file_type,
                'visibility' => $material->visibility,
                'uploaded_by' => $material->uploadedBy?->name,
            ])
            ->values()
            ->all();

        $streamPosts = ClassStreamPost::with(['user'])
            ->withCount(['comments', 'reactions'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ClassStreamPost $post) => [
                'stream_post_id' => $post->id,
                'course_section_id' => $post->course_section_id,
                'user' => $post->user?->name,
                'body' => $post->body,
                'attachment_name' => $post->attachment_name,
                'attachment_path' => $post->attachment_path,
                'comments_count' => $post->comments_count,
                'reactions_count' => $post->reactions_count,
                'created_at' => $post->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $messages = ClassMessage::with(['sender', 'recipient'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ClassMessage $message) => [
                'class_message_id' => $message->id,
                'course_section_id' => $message->course_section_id,
                'sender' => $message->sender?->name,
                'recipient' => $message->recipient?->name,
                'body' => $message->body,
                'attachment_name' => $message->attachment_name,
                'attachment_path' => $message->attachment_path,
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $financeTransactions = FinanceTransaction::with(['student.department.college'])
            ->where('academic_year', $academicYear)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn (FinanceTransaction $transaction) => [
                'finance_transaction_id' => $transaction->id,
                'student_id' => $transaction->student_id,
                'student_number' => $transaction->student?->student_id,
                'student_name' => $transaction->student?->full_name,
                'college_id' => $transaction->student?->department?->college?->id,
                'college' => $transaction->student?->department?->college?->name,
                'department_id' => $transaction->student?->department?->id,
                'department' => $transaction->student?->department?->name,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'balance_after' => is_null($transaction->balance_after) ? null : (float) $transaction->balance_after,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'payment_status' => $transaction->payment_status,
                'invoice_number' => $transaction->invoice_number,
                'receipt_number' => $transaction->receipt_number,
                'transaction_date' => $transaction->transaction_date?->format('Y-m-d'),
                'due_date' => $transaction->due_date?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return [
            'version' => 2,
            'captured_at' => now()->toIso8601String(),
            'modules' => $moduleSnapshots,
            'enrollments' => $enrollments,
            'marks' => $marks,
            'assessments' => $assessments,
            'attendance' => $attendance,
            'timetable' => $timetable,
            'materials' => $materials,
            'stream_posts' => $streamPosts,
            'class_messages' => $messages,
            'finance_transactions' => $financeTransactions,
        ];
    }

    private function archiveMarkSnapshot(Mark $mark): array
    {
        $section = $mark->courseSection;
        $course = $mark->course ?? $section?->course;
        $studentDepartment = $mark->student?->department;
        $courseDepartment = $course?->department;
        $department = $studentDepartment ?? $courseDepartment;
        $college = $studentDepartment?->college ?? $courseDepartment?->college;

        return [
            'mark_id' => $mark->id,
            'course_section_id' => $mark->course_section_id,
            'course_id' => $mark->course_id,
            'course_code' => $course?->code,
            'course_name' => $course?->name,
            'student_id' => $mark->student_id,
            'student_number' => $mark->student?->student_id,
            'student_name' => $mark->student?->full_name,
            'college_id' => $college?->id,
            'college' => $college?->name,
            'department_id' => $department?->id,
            'department' => $department?->name,
            'stage' => $section?->grade_level,
            'semester_id' => $section?->semester_id,
            'semester' => $section?->semester?->name,
            'academic_year' => $section?->semester?->academic_year,
            'group' => $section?->section_code,
            'prefinal_mark' => is_null($mark->prefinal_mark) ? null : (float) $mark->prefinal_mark,
            'first_trial_final_exam' => is_null($mark->first_trial_final_exam) ? null : (float) $mark->first_trial_final_exam,
            'second_trial_final_exam' => is_null($mark->second_trial_final_exam) ? null : (float) $mark->second_trial_final_exam,
            'final_exam' => is_null($mark->final_exam) ? null : (float) $mark->final_exam,
            'final_mark' => is_null($mark->final_mark) ? null : (float) $mark->final_mark,
            'submission_status' => $mark->submission_status,
            'visibility_status' => $mark->visibility_status,
            'published_at' => $mark->published_at?->toIso8601String(),
        ];
    }

    private function archiveCurrentEnrollments(Request $request, array $summary): void
    {
        $now = now();
        $enrollments = Enrollment::whereIn('course_section_id', $summary['section_ids'])
            ->whereIn('status', ['enrolled', 'waitlisted'])
            ->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        $events = [];

        foreach ($enrollments as $enrollment) {
            $previousStatus = $enrollment->status;
            $newStatus = $previousStatus === 'waitlisted' ? 'dropped' : 'completed';
            $notes = $previousStatus === 'waitlisted'
                ? 'Waitlist cleared during academic year closing.'
                : 'Completed during academic year closing.';

            $enrollment->forceFill([
                'status' => $newStatus,
                'dropped_at' => $previousStatus === 'waitlisted' ? today() : $enrollment->dropped_at,
                'drop_reason' => $previousStatus === 'waitlisted' ? 'Academic year closed before seat assignment.' : $enrollment->drop_reason,
                'notes' => trim((string) $enrollment->notes."\n".$notes),
                'updated_at' => $now,
            ])->save();

            $events[] = [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'course_section_id' => $enrollment->course_section_id,
                'actor_id' => $request->user()->id,
                'action' => $newStatus === 'completed' ? 'completed_by_year_closure' : 'waitlist_cleared_by_year_closure',
                'notes' => $notes,
                'metadata' => json_encode(['academic_year' => $summary['academic_year']]),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        EnrollmentEvent::insert($events);
    }

    private function emptySummary(): array
    {
        return [
            'academic_year' => null,
            'universities' => collect(),
            'university_ids' => collect(),
            'semester_count' => 0,
            'section_count' => 0,
            'section_ids' => collect(),
            'student_count' => 0,
            'enrollment_count' => 0,
            'published_marks' => 0,
            'entered_marks' => 0,
            'missing_marks' => 0,
            'unpublished_marks' => 0,
            'open_finance_invoices' => 0,
            'open_finance_by_currency' => collect(),
            'blockers' => collect(),
            'warnings' => collect(),
            'blockers_count' => 0,
            'is_closed' => false,
            'closures' => collect(),
            'unarchived_section_count' => 0,
            'recent_sections' => collect(),
            'snapshot' => [],
        ];
    }

    private function canManageClosure(Request $request): bool
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');
    }

    private function authorizeArchiveView(Request $request): void
    {
        $user = $request->user();

        abort_unless($user, 403);

        if (
            $user->hasAnyRole([
                'super_administrator',
                'administrator',
                'university_administrator',
                'college_administrator',
                'department_administrator',
                'examination_administrator',
                'examination_committee',
            ])
            || $user->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage'])
        ) {
            return;
        }

        abort(403);
    }

    private function archiveClosures(User $user)
    {
        $query = AcademicYearClosure::with(['university', 'closedBy'])
            ->orderByDesc('academic_year')
            ->orderByDesc('closed_at');

        if (! $this->canSeeAllArchiveData($user)) {
            if (! $user->university_id) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('university_id', $user->university_id);
            }
        }

        return $query->get();
    }

    private function archiveYears($closures, User $user)
    {
        return $closures
            ->groupBy('academic_year')
            ->map(function ($yearClosures, string $academicYear) use ($user) {
                $semesterQuery = Semester::where('academic_year', $academicYear);
                $this->scopeArchiveQuery($semesterQuery, $user, 'semester');
                $semesterIds = $semesterQuery->pluck('id');
                $sectionQuery = $this->archivedModulesForYear($semesterIds, $user);
                $sectionIds = (clone $sectionQuery)->pluck('id');
                $archivedModules = (clone $sectionQuery)
                    ->whereNotNull('deleted_at')
                    ->count();
                $visibleClosedModules = (clone $sectionQuery)
                    ->whereNull('deleted_at')
                    ->where('status', 'closed')
                    ->count();
                $enrollmentQuery = Enrollment::whereIn('course_section_id', $sectionIds)
                    ->whereIn('status', ['enrolled', 'completed']);
                $financeQuery = FinanceTransaction::where('academic_year', $academicYear)
                    ->where('type', 'invoice')
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('payment_status', ['open', 'partial', 'overdue']);
                $this->scopeArchiveQuery($financeQuery, $user, 'student_record');
                $summary = $yearClosures
                    ->map(fn (AcademicYearClosure $closure) => $closure->summary ?? [])
                    ->filter()
                    ->first() ?? [];
                $useStoredSummary = $this->canSeeAllArchiveData($user) || $this->hasUniversityArchiveScope($user);
                if ($semesterIds->isEmpty() && $useStoredSummary) {
                    $archivedModules = (int) ($summary['archived_modules'] ?? $summary['section_count'] ?? $archivedModules);
                }

                return [
                    'academic_year' => $academicYear,
                    'universities' => $yearClosures->pluck('university.name')->filter()->unique()->values(),
                    'closed_at' => $yearClosures->max('closed_at'),
                    'closed_by' => $yearClosures->pluck('closedBy.name')->filter()->unique()->values(),
                    'closure_count' => $yearClosures->count(),
                    'semester_count' => $useStoredSummary ? ($summary['semester_count'] ?? $semesterIds->count()) : $semesterIds->count(),
                    'student_count' => $useStoredSummary ? ($summary['student_count'] ?? 0) : (clone $enrollmentQuery)->distinct('student_id')->count('student_id'),
                    'enrollment_count' => $useStoredSummary ? ($summary['enrollment_count'] ?? 0) : (clone $enrollmentQuery)->count(),
                    'archived_modules' => $archivedModules,
                    'visible_closed_modules' => $visibleClosedModules,
                    'open_finance_invoices' => $useStoredSummary ? ($summary['open_finance_invoices'] ?? 0) : $financeQuery->count(),
                ];
            })
            ->filter(fn (array $year) => $this->canSeeAllArchiveData($user)
                || $this->hasUniversityArchiveScope($user)
                || $year['archived_modules'] > 0
                || $year['visible_closed_modules'] > 0
                || $year['enrollment_count'] > 0)
            ->values();
    }

    private function archivedModulesForYear($semesterIds, ?User $user = null)
    {
        $query = CourseSection::withTrashed()
            ->with(['course.department.college', 'semester', 'teacher'])
            ->whereIn('semester_id', $semesterIds)
            ->where(function ($query) {
                $query->whereNotNull('deleted_at')
                    ->orWhere('status', 'closed');
            });

        if ($user) {
            $this->scopeArchiveQuery($query, $user, 'section');
        }

        return $query;
    }

    private function scopeArchiveQuery($query, User $user, string $modelType): void
    {
        if ($this->canSeeAllArchiveData($user)) {
            return;
        }

        OrganizationScope::apply($query, $user, $modelType);
    }

    private function scopeArchiveMarkQuery($query, User $user): void
    {
        if ($this->canSeeAllArchiveData($user)) {
            return;
        }

        if (! $user->university_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scopeQuery) use ($user) {
            $this->applyArchiveMarkOrganizationBranch($scopeQuery, $user, 'courseSection.course.department');
            $scopeQuery->orWhere(function ($branch) use ($user) {
                $this->applyArchiveMarkOrganizationBranch($branch, $user, 'course.department');
            });
            $scopeQuery->orWhereHas('student', function ($student) use ($user) {
                $student
                    ->where('university_id', $user->university_id)
                    ->when($this->requiresCollegeArchiveScope($user), fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $user->college_id)))
                    ->when($this->requiresDepartmentArchiveScope($user), fn ($builder) => $builder->where('department_id', $user->department_id));
            });
        });
    }

    private function applyArchiveMarkOrganizationBranch($query, User $user, string $relation): void
    {
        $query->whereHas($relation, function ($department) use ($user) {
            $department
                ->where('university_id', $user->university_id)
                ->when($this->requiresCollegeArchiveScope($user), fn ($builder) => $builder->where('college_id', $user->college_id))
                ->when($this->requiresDepartmentArchiveScope($user), fn ($builder) => $builder->whereKey($user->department_id));
        });
    }

    private function canSeeAllArchiveData(User $user): bool
    {
        return $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasAnyDirectPermissionGrant(['academic_setup.view', 'academic_setup.manage']);
    }

    private function hasUniversityArchiveScope(User $user): bool
    {
        return $user->hasRole('university_administrator')
            || ($user->hasAnyRole(['examination_administrator', 'examination_committee']) && filled($user->university_id) && blank($user->college_id) && blank($user->department_id));
    }

    private function requiresCollegeArchiveScope(User $user): bool
    {
        return $user->hasRole('college_administrator')
            || $user->hasRole('department_administrator')
            || ($user->hasAnyRole(['examination_administrator', 'examination_committee']) && filled($user->college_id));
    }

    private function requiresDepartmentArchiveScope(User $user): bool
    {
        return $user->hasRole('department_administrator')
            || ($user->hasAnyRole(['examination_administrator', 'examination_committee']) && filled($user->department_id));
    }

    private function archiveSummaryForYear(string $academicYear, User $user): array
    {
        $query = AcademicYearClosure::where('academic_year', $academicYear);

        if (! $this->canSeeAllArchiveData($user)) {
            if (! $user->university_id) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('university_id', $user->university_id);
            }
        }

        return $query
            ->get()
            ->map(fn (AcademicYearClosure $closure) => $closure->summary ?? [])
            ->filter()
            ->first() ?? [];
    }

    private function snapshotCount(array $snapshot, string $key): int
    {
        return count($snapshot[$key] ?? []);
    }

    private function snapshotCountForUser(array $snapshot, string $key, User $user): int
    {
        if ($this->canSeeAllArchiveData($user) || $this->hasUniversityArchiveScope($user)) {
            return $this->snapshotCount($snapshot, $key);
        }

        $rows = collect($snapshot[$key] ?? []);

        if ($key === 'modules') {
            return $rows
                ->filter(fn (array $row) => $this->snapshotRowMatchesScope($row, $user))
                ->count();
        }

        $scopedSectionIds = collect($snapshot['modules'] ?? [])
            ->filter(fn (array $row) => $this->snapshotRowMatchesScope($row, $user))
            ->pluck('course_section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        return $rows
            ->filter(function (array $row) use ($user, $scopedSectionIds) {
                if (array_key_exists('college_id', $row) || array_key_exists('department_id', $row)) {
                    return $this->snapshotRowMatchesScope($row, $user);
                }

                return $scopedSectionIds->contains((int) ($row['course_section_id'] ?? 0));
            })
            ->count();
    }

    private function snapshotModulesForArchive(array $snapshot, User $user, array $filters)
    {
        return collect($snapshot['modules'] ?? [])
            ->filter(fn (array $row) => $this->snapshotRowMatchesScope($row, $user))
            ->filter(fn (array $row) => $this->snapshotRowMatchesFilters($row, $filters))
            ->values();
    }

    private function snapshotResultsForArchive(array $snapshot, User $user, array $filters)
    {
        $rows = collect($snapshot['marks'] ?? [])
            ->filter(fn (array $row) => ($row['visibility_status'] ?? null) === 'published')
            ->filter(fn (array $row) => ! is_null($row['final_mark'] ?? null))
            ->filter(fn (array $row) => $this->snapshotRowMatchesScope($row, $user))
            ->filter(fn (array $row) => $this->snapshotRowMatchesFilters($row, $filters))
            ->map(function (array $row) {
                $finalMark = (float) $row['final_mark'];
                $row['result'] = $finalMark >= 50 ? 'Passed' : 'Failed';

                return $row;
            })
            ->filter(function (array $row) use ($filters) {
                if (($filters['result_status'] ?? null) === 'passed') {
                    return $row['result'] === 'Passed';
                }

                if (($filters['result_status'] ?? null) === 'failed') {
                    return $row['result'] === 'Failed';
                }

                return true;
            });

        return match ($filters['sort'] ?? 'final_desc') {
            'final_asc' => $rows->sortBy(fn (array $row) => (float) $row['final_mark'])->values(),
            'student_asc' => $rows->sortBy(fn (array $row) => $row['student_name'] ?? '')->values(),
            'student_desc' => $rows->sortByDesc(fn (array $row) => $row['student_name'] ?? '')->values(),
            'course_asc' => $rows->sortBy(fn (array $row) => $row['course_name'] ?? '')->values(),
            default => $rows->sortByDesc(fn (array $row) => (float) $row['final_mark'])->values(),
        };
    }

    private function markRowsForStudentArchive($marks)
    {
        return $marks
            ->map(function (Mark $mark) {
                $section = $mark->courseSection;
                $course = $mark->course ?? $section?->course;
                $studentDepartment = $mark->student?->department;
                $courseDepartment = $course?->department;
                $department = $studentDepartment ?? $courseDepartment;
                $college = $studentDepartment?->college ?? $courseDepartment?->college;
                $finalMark = (float) $mark->final_mark;

                return [
                    'student_id' => $mark->student_id,
                    'student_number' => $mark->student?->student_id,
                    'student_name' => $mark->student?->full_name,
                    'college' => $college?->name,
                    'department' => $department?->name,
                    'course_code' => $course?->code,
                    'course_name' => $course?->name,
                    'group' => $section?->section_code,
                    'stage' => $section?->grade_level,
                    'semester' => $section?->semester?->name,
                    'academic_year' => $section?->semester?->academic_year,
                    'final_mark' => $finalMark,
                    'result' => $finalMark >= 50 ? 'Passed' : 'Failed',
                ];
            })
            ->values();
    }

    private function studentArchiveResultStats($resultBaseQuery): array
    {
        $studentRows = $this->studentArchiveAggregateQuery($resultBaseQuery);
        $total = DB::query()->fromSub(clone $studentRows, 'student_results')->count();
        $passed = DB::query()->fromSub(clone $studentRows, 'student_results')->where('failed_modules', 0)->count();
        $failed = DB::query()->fromSub(clone $studentRows, 'student_results')->where('failed_modules', '>', 0)->count();

        return [
            'students' => $total,
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    private function studentArchiveResultStudentIds($resultBaseQuery, ?string $resultStatus, string $sort, ?int $limit)
    {
        $query = $this->studentArchiveAggregateQuery($resultBaseQuery);

        if ($resultStatus === 'passed') {
            $query->havingRaw('SUM(CASE WHEN marks.final_mark < 50 THEN 1 ELSE 0 END) = 0');
        }

        if ($resultStatus === 'failed') {
            $query->havingRaw('SUM(CASE WHEN marks.final_mark < 50 THEN 1 ELSE 0 END) > 0');
        }

        match ($sort) {
            'final_asc' => $query->orderBy('average_mark')->orderBy('students.full_name'),
            'student_asc' => $query->orderBy('students.full_name')->orderByDesc('average_mark'),
            'student_desc' => $query->orderByDesc('students.full_name')->orderByDesc('average_mark'),
            'course_asc' => $query->orderBy('first_course_name')->orderByDesc('average_mark'),
            default => $query->orderByDesc('average_mark')->orderBy('students.full_name'),
        };

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('marks.student_id');
    }

    private function studentArchiveAggregateQuery($resultBaseQuery)
    {
        return (clone $resultBaseQuery)
            ->withoutEagerLoads()
            ->leftJoin('students', 'marks.student_id', '=', 'students.id')
            ->leftJoin('courses', 'marks.course_id', '=', 'courses.id')
            ->select('marks.student_id')
            ->selectRaw('AVG(marks.final_mark) as average_mark')
            ->selectRaw('MIN(courses.name) as first_course_name')
            ->selectRaw('SUM(CASE WHEN marks.final_mark < 50 THEN 1 ELSE 0 END) as failed_modules')
            ->groupBy('marks.student_id', 'students.full_name');
    }

    private function studentResultsForArchive($rows, ?string $resultStatus, string $sort)
    {
        $studentRows = collect($rows)
            ->groupBy(fn (array $row) => $row['student_id'] ?? $row['student_number'] ?? $row['student_name'] ?? 'unknown')
            ->map(function ($marks) {
                $marks = $marks->values();
                $finalMarks = $marks->pluck('final_mark')->map(fn ($mark) => (float) $mark);
                $failedModules = $marks->where('result', 'Failed')->count();
                $passedModules = $marks->where('result', 'Passed')->count();

                return [
                    'student_id' => $marks->first()['student_id'] ?? null,
                    'student_number' => $marks->first()['student_number'] ?? null,
                    'student_name' => $marks->first()['student_name'] ?? 'Unknown student',
                    'college' => $marks->first()['college'] ?? 'No college',
                    'department' => $marks->first()['department'] ?? 'No department',
                    'modules_count' => $marks->count(),
                    'passed_modules' => $passedModules,
                    'failed_modules' => $failedModules,
                    'average_mark' => $finalMarks->avg() ?? 0,
                    'highest_mark' => $finalMarks->max() ?? 0,
                    'lowest_mark' => $finalMarks->min() ?? 0,
                    'result' => $failedModules > 0 ? 'Failed' : 'Passed',
                    'modules' => $marks
                        ->sortBy('course_name')
                        ->values()
                        ->all(),
                ];
            })
            ->filter(function (array $row) use ($resultStatus) {
                if ($resultStatus === 'passed') {
                    return $row['result'] === 'Passed';
                }

                if ($resultStatus === 'failed') {
                    return $row['result'] === 'Failed';
                }

                return true;
            });

        return match ($sort) {
            'final_asc' => $studentRows->sortBy(fn (array $row) => $row['average_mark'])->values(),
            'student_asc' => $studentRows->sortBy(fn (array $row) => $row['student_name'])->values(),
            'student_desc' => $studentRows->sortByDesc(fn (array $row) => $row['student_name'])->values(),
            'course_asc' => $studentRows->sortBy(fn (array $row) => $row['modules'][0]['course_name'] ?? '')->values(),
            default => $studentRows->sortByDesc(fn (array $row) => $row['average_mark'])->values(),
        };
    }

    private function snapshotRowMatchesScope(array $row, User $user): bool
    {
        if ($this->canSeeAllArchiveData($user) || $this->hasUniversityArchiveScope($user)) {
            return true;
        }

        if ($this->requiresDepartmentArchiveScope($user)) {
            return (int) ($row['department_id'] ?? 0) === (int) $user->department_id;
        }

        if ($this->requiresCollegeArchiveScope($user)) {
            return (int) ($row['college_id'] ?? 0) === (int) $user->college_id;
        }

        return false;
    }

    private function snapshotRowMatchesFilters(array $row, array $filters): bool
    {
        if (($filters['college_id'] ?? null) && (int) ($row['college_id'] ?? 0) !== (int) $filters['college_id']) {
            return false;
        }

        if (($filters['department_id'] ?? null) && (int) ($row['department_id'] ?? 0) !== (int) $filters['department_id']) {
            return false;
        }

        if (($filters['stage'] ?? null) && (string) ($row['stage'] ?? '') !== (string) $filters['stage']) {
            return false;
        }

        if (($filters['semester_id'] ?? null) && (int) ($row['semester_id'] ?? 0) !== (int) $filters['semester_id']) {
            return false;
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $needle = str($search)->lower()->toString();
            $haystack = str(collect([
                $row['student_name'] ?? null,
                $row['student_number'] ?? null,
                $row['student_email'] ?? null,
                $row['course_name'] ?? null,
                $row['course_code'] ?? null,
                $row['group'] ?? null,
                $row['teacher'] ?? null,
            ])->filter()->implode(' '))->lower()->toString();

            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function applyArchiveSectionSearch($query, string $search): void
    {
        $query->where(function ($builder) use ($search) {
            $builder
                ->where('section_code', 'like', "%{$search}%")
                ->orWhereHas('course', function ($course) use ($search) {
                    $course
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('teacher', fn ($teacher) => $teacher->where('full_name', 'like', "%{$search}%"))
                ->orWhereHas('enrollments.student', function ($student) use ($search) {
                    $student
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
        });
    }

    private function applyArchiveResultSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($search) {
            $builder
                ->whereHas('student', function ($student) use ($search) {
                    $student
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('course', function ($course) use ($search) {
                    $course
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orWhereHas('courseSection', fn ($section) => $section->where('section_code', 'like', "%{$search}%"));
        });
    }
}
