<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicYearClosure;
use App\Models\ActivityLog;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\ClassStreamComment;
use App\Models\ClassStreamPost;
use App\Models\ClassStreamReaction;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentProgressionException;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use App\Services\StudentProgressionService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicYearClosureController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.view', 'academic_setup.manage']);

        $universities = $this->accessibleClosingUniversities($request->user());
        $selectedUniversityId = $request->integer('university_id') ?: ($request->user()->university_id ?: $universities->first()?->id);
        $selectedUniversity = $universities->firstWhere('id', $selectedUniversityId);
        abort_if($selectedUniversityId && ! $selectedUniversity, 403);

        $academicYears = Semester::withTrashed()
            ->when($selectedUniversity, fn ($query) => $query->where('university_id', $selectedUniversity->id))
            ->when(! $selectedUniversity, fn ($query) => $query->whereRaw('1 = 0'))
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->filter()
            ->values();

        $selectedYear = $request->query('academic_year', $academicYears->first());
        if (! $selectedUniversity) {
            $selectedYear = null;
        } else {
            abort_if($selectedYear && ! $academicYears->contains($selectedYear), 404);
        }
        $summary = $selectedYear && $selectedUniversity
            ? $this->buildSummary($selectedYear, $selectedUniversity->id, $request->user())
            : $this->emptySummary();

        return view('academic-year-closures.index', [
            'universities' => $universities,
            'selectedUniversity' => $selectedUniversity,
            'academicYears' => $academicYears,
            'selectedYear' => $selectedYear,
            'summary' => $summary,
            'canResolveClosure' => $this->canResolveClosure($request),
            'canManageClosure' => $selectedUniversity
                ? $this->canManageClosure($request, $selectedUniversity->id)
                : false,
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

        $data = $this->archiveYearData($request, false, true);
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
                $exportedKeys = [];

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

                    $exportedKeys[$this->archiveResultKeyFromModel($mark)] = true;
                }

                foreach ($data['snapshotResultRows'] as $row) {
                    $key = $this->archiveResultRowKey($row);
                    if ($key !== '' && isset($exportedKeys[$key])) {
                        continue;
                    }

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

    private function archiveYearData(Request $request, bool $limitResults = true, bool $forceLoadStudents = false): array
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'string'],
            'q' => ['nullable', 'string', 'max:120'],
            'college_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'stage' => ['nullable', 'string'],
            'semester_id' => ['nullable', 'integer'],
            'students' => ['nullable', Rule::in(['0', '1'])],
            'result_status' => ['nullable', Rule::in(['passed', 'failed'])],
            'sort' => ['nullable', Rule::in(['final_desc', 'final_asc', 'student_asc', 'student_desc', 'course_asc'])],
        ]);

        $academicYear = $validated['academic_year'];
        $q = trim((string) ($validated['q'] ?? ''));
        $semesterQuery = Semester::withTrashed()->where('academic_year', $academicYear);
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
        $loadStudents = $forceLoadStudents || (($validated['students'] ?? '0') === '1');
        $resultStatus = $validated['result_status'] ?? null;
        $sort = $validated['sort'] ?? 'final_desc';
        $isClassroomStep = filled($stage);

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
            ->when($isClassroomStep, fn ($query) => $query->withCount([
                'enrollments as enrollment_count' => fn ($enrollments) => $enrollments->withTrashed(),
                'marks' => fn ($marks) => $marks->withTrashed(),
                'assessmentItems' => fn ($assessments) => $assessments->withTrashed(),
            ]))
            ->orderBy('semester_id')
            ->orderBy('grade_level')
            ->orderBy('section_code')
            ->when($limitResults && $isClassroomStep, fn ($query) => $query->limit(500))
            ->get();
        $resultStats = [
            'published' => 0,
            'passed' => 0,
            'failed' => 0,
        ];
        $resultRows = collect();
        $studentResultRows = collect();
        $studentResultStats = [
            'students' => 0,
            'passed' => 0,
            'failed' => 0,
        ];
        $snapshotResultRows = collect();
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
        $snapshotPublishedResults = $this->snapshotResultsForArchive($archiveSnapshot, $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
            'result_status' => null,
            'sort' => $sort,
        ]);
        if ($loadStudents) {
            $resultBaseQuery = Mark::withTrashed()->with([
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

            if ($resultRows->isEmpty() && $snapshotResultRows->isNotEmpty()) {
                $resultStats = [
                    'published' => $snapshotPublishedResults->count(),
                    'passed' => $snapshotPublishedResults->where('result', 'Passed')->count(),
                    'failed' => $snapshotPublishedResults->where('result', 'Failed')->count(),
                ];
            }

            $combinedStudentResultSourceRows = $studentResultSourceRows;
            if ($snapshotStudentSourceRows->isNotEmpty()) {
                $existingKeys = $combinedStudentResultSourceRows
                    ->map(fn (array $row) => $this->archiveResultRowKey($row))
                    ->filter()
                    ->flip();

                $snapshotRowsToAdd = $snapshotStudentSourceRows->reject(function (array $row) use ($existingKeys) {
                    $key = $this->archiveResultRowKey($row);

                    return $key !== '' && $existingKeys->has($key);
                });

                $combinedStudentResultSourceRows = $combinedStudentResultSourceRows
                    ->concat($snapshotRowsToAdd)
                    ->values();
            }

            $allScopedStudentResultRows = $this->studentResultsForArchive(
                $combinedStudentResultSourceRows,
                null,
                $sort
            );
            $studentResultRows = $this->studentResultsForArchive(
                $combinedStudentResultSourceRows,
                $resultStatus,
                $sort
            );
            $studentResultStats = [
                'students' => $allScopedStudentResultRows->count(),
                'passed' => $allScopedStudentResultRows->where('result', 'Passed')->count(),
                'failed' => $allScopedStudentResultRows->where('result', 'Failed')->count(),
            ];
        }

        $groupedSections = $sections
            ->groupBy(fn (CourseSection $section) => $section->course?->department?->college?->name ?? 'No college')
            ->map(fn ($collegeSections) => $collegeSections
                ->groupBy(fn (CourseSection $section) => $section->course?->department?->name ?? 'No department')
                ->map(fn ($departmentSections) => $departmentSections
                    ->groupBy(fn (CourseSection $section) => $section->grade_level ?: 'No stage')
                    ->map(fn ($stageSections) => $stageSections
                        ->groupBy(fn (CourseSection $section) => trim(($section->semester?->name ?? 'Semester').' '.($section->semester?->academic_year ?? ''))))));
        $hasActiveFilters = $q !== '' || ! is_null($collegeId) || ! is_null($departmentId) || ! is_null($stage) || ! is_null($semesterId);
        $filteredEnrollmentCount = $sectionIds->isNotEmpty()
            ? Enrollment::withTrashed()->whereIn('course_section_id', $sectionIds)->count()
            : 0;
        $filteredMarksCount = $sectionIds->isNotEmpty()
            ? Mark::withTrashed()->whereIn('course_section_id', $sectionIds)->count()
            : 0;
        $snapshotFilteredEnrollmentCount = $this->snapshotFilteredCountForArchive($archiveSnapshot, 'enrollments', $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
        ]);
        $snapshotFilteredMarksCount = $this->snapshotFilteredCountForArchive($archiveSnapshot, 'marks', $request->user(), [
            'q' => $q,
            'college_id' => $collegeId,
            'department_id' => $departmentId,
            'stage' => $stage,
            'semester_id' => $semesterId,
        ]);
        $summaryStats = [
            'modules' => $sectionIds->count() ?: ($snapshotModules->count() ?: ((! $hasActiveFilters && $usingSnapshotFallback) ? (int) ($closureSummary['section_count'] ?? $closureSummary['archived_modules'] ?? 0) : 0)),
            'enrollments' => $filteredEnrollmentCount ?: ($snapshotFilteredEnrollmentCount ?: ((! $hasActiveFilters && $usingSnapshotFallback) ? (int) ($closureSummary['enrollment_count'] ?? 0) : 0)),
            'marks' => $filteredMarksCount ?: ($snapshotFilteredMarksCount ?: ((! $hasActiveFilters && $usingSnapshotFallback) ? (int) ($closureSummary['entered_marks'] ?? $closureSummary['published_marks'] ?? 0) : 0)),
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
            'loadStudents' => $loadStudents,
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
            foreach ($closures as $closure) {
                $summary = $closure->summary ?? [];
                $needsRebuild = empty($summary['archive_snapshot'] ?? null);

                if (! $force && ! $needsRebuild) {
                    $skipped++;

                    continue;
                }

                $rebuiltSummary = $this->buildSummary($year, $closure->university_id, null, true);

                $closure->update(['summary' => $rebuiltSummary['snapshot']]);

                $rebuilt++;
            }
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

        $accessibleUniversities = $this->accessibleClosingUniversities($request->user());
        if (! $request->filled('university_id') && $accessibleUniversities->count() === 1) {
            $request->merge(['university_id' => $accessibleUniversities->first()->id]);
        }

        $validated = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'academic_year' => ['required', 'string', 'max:20'],
            'confirm_results' => ['accepted'],
            'confirm_finance' => ['accepted'],
        ]);
        $university = $accessibleUniversities->firstWhere('id', (int) $validated['university_id']);
        abort_unless($university, 403);
        abort_unless($this->canManageClosure($request, $university->id), 403);
        $yearExists = Semester::withTrashed()
            ->where('university_id', $university->id)
            ->where('academic_year', $validated['academic_year'])
            ->exists();
        abort_unless($yearExists, 404);

        $summary = $this->buildSummary($validated['academic_year'], $university->id, $request->user());

        if ($summary['blockers_count'] > 0) {
            return back()
                ->withInput()
                ->with('error', 'Academic year cannot be closed until missing and unpublished results are completed.');
        }

        $progressionReport = null;
        DB::transaction(function () use ($request, $summary, $validated, $university, &$progressionReport) {
            if ($summary['section_ids']->isNotEmpty()) {
                $progressionReport = app(StudentProgressionService::class)->process(
                    $summary['section_ids'],
                    $validated['academic_year'],
                    $request->user()
                );
                $this->archiveCurrentEnrollments($request, $summary);

                CourseSection::whereIn('id', $summary['section_ids'])
                    ->whereIn('status', ['planned', 'active'])
                    ->update(['status' => 'closed']);

                CourseSection::whereIn('id', $summary['section_ids'])
                    ->whereNull('deleted_at')
                    ->delete();
            }

            $closureSummary = $this->buildSummary($validated['academic_year'], $university->id, $request->user(), true);
            $closureSummary['snapshot']['progression'] = $progressionReport;
            $this->archiveYearRecords($closureSummary, $validated['academic_year'], $university->id);

            $closure = AcademicYearClosure::updateOrCreate(
                [
                    'university_id' => $university->id,
                    'academic_year' => $validated['academic_year'],
                ],
                [
                    'status' => 'closed',
                    'closed_by' => $request->user()->id,
                    'closed_at' => now(),
                    'summary' => $closureSummary['snapshot'],
                ]
            );

            AcademicYear::where('university_id', $university->id)
                ->where('name', $validated['academic_year'])
                ->update(['status' => 'closed']);

            ActivityLog::create([
                'log_name' => 'academic_year_closure',
                'description' => 'year_closed',
                'subject_type' => AcademicYearClosure::class,
                'subject_id' => $closure->id,
                'causer_type' => User::class,
                'causer_id' => $request->user()->id,
                'properties' => array_merge(
                    ['academic_year' => $validated['academic_year'], 'university_id' => $university->id],
                    $closureSummary['snapshot']
                ),
            ]);
        });

        return redirect()
            ->route('academic-year-closures.index', [
                'university_id' => $university->id,
                'academic_year' => $validated['academic_year'],
            ])
            ->with('success', 'Academic year closed and archived. Current rosters were completed, waitlists were cleared, and year records were moved to archive history.');
    }

    public function storeProgressionException(Request $request)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'status' => ['required', Rule::in(StudentProgressionException::STATUSES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $student = $this->managedStudentForYear((int) $validated['student_id'], $validated['academic_year'], $request->user());
        $this->ensureYearOpen($validated['academic_year'], $student->university_id);

        StudentProgressionException::updateOrCreate(
            ['student_id' => $student->id, 'academic_year' => $validated['academic_year']],
            [
                'status' => $validated['status'],
                'reason' => $validated['reason'],
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]
        );

        return redirect()->route('academic-year-closures.index', [
            'university_id' => $student->university_id,
            'academic_year' => $validated['academic_year'],
        ])
            ->with('success', 'The student exception was approved and recorded for year closing.');
    }

    public function destroyProgressionException(Request $request, StudentProgressionException $exception)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $student = $this->managedStudentForYear($exception->student_id, $exception->academic_year, $request->user());
        $this->ensureYearOpen($exception->academic_year, $student->university_id);
        $academicYear = $exception->academic_year;
        $exception->delete();

        return redirect()->route('academic-year-closures.index', [
            'university_id' => $student->university_id,
            'academic_year' => $academicYear,
        ])
            ->with('success', 'The progression exception was removed. The student must now receive a normal decision or another approved exception.');
    }

    public function assignStudentStage(Request $request, Student $student)
    {
        $this->requireAnyRoleOrDirectPermission(['super_administrator', 'administrator'], ['academic_setup.manage']);
        $validated = $request->validate([
            'academic_year' => ['required', 'string', 'max:20'],
            'stage_id' => ['required', 'integer', 'exists:stages,id'],
        ]);
        $student = $this->managedStudentForYear($student->id, $validated['academic_year'], $request->user());
        $this->ensureYearOpen($validated['academic_year'], $student->university_id);
        $stageQuery = Stage::whereKey($validated['stage_id'])
            ->where('university_id', $student->university_id)
            ->where('department_id', $student->department_id);
        OrganizationScope::apply($stageQuery, $request->user(), 'stage');
        $stage = $stageQuery->firstOrFail();
        $student->update(['current_stage_id' => $stage->id]);

        return redirect()->route('academic-year-closures.index', [
            'university_id' => $student->university_id,
            'academic_year' => $validated['academic_year'],
        ])
            ->with('success', 'The student current stage was assigned.');
    }

    private function buildSummary(string $academicYear, int $universityId, ?User $user = null, bool $includeArchiveSnapshot = false): array
    {
        $semesters = Semester::withTrashed()->with('university')
            ->where('university_id', $universityId)
            ->where('academic_year', $academicYear)
            ->orderBy('name')
            ->get();

        $semesterIds = $semesters->pluck('id');
        $university = University::find($universityId);

        $sectionQuery = CourseSection::withTrashed()
            ->with(['course.department.college', 'semester.university'])
            ->withCount(['activeEnrollments', 'marks'])
            ->whereIn('semester_id', $semesterIds)
            ->orderBy('semester_id')
            ->orderBy('section_code');
        if ($user) {
            OrganizationScope::apply($sectionQuery, $user, 'section');
        }
        $sections = $sectionQuery->get();

        $sectionIds = $sections->pluck('id');
        $readiness = app(StudentProgressionService::class)->readiness($sectionIds, $academicYear);
        $resultEnrollmentQuery = Enrollment::withTrashed()->whereIn('course_section_id', $sectionIds)->whereIn('status', ['enrolled', 'completed']);
        $markQuery = Mark::withTrashed()->whereIn('course_section_id', $sectionIds);
        $financeQuery = FinanceTransaction::withTrashed()->where('academic_year', $academicYear)
            ->whereHas('student', fn ($student) => $student->where('university_id', $universityId))
            ->where('type', 'invoice')
            ->where('status', '!=', 'cancelled');
        if ($user) {
            OrganizationScope::apply($financeQuery, $user, 'student_record');
        }

        $enrollmentsCount = (clone $resultEnrollmentQuery)->count();
        $studentCount = (clone $resultEnrollmentQuery)->distinct('student_id')->count('student_id');
        $enteredMarks = (clone $markQuery)->count();
        $missingMarks = $readiness['missing_result_count'];
        $unpublishedMarks = $readiness['unusable_result_count'];
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
            ->where('university_id', $universityId)
            ->where('academic_year', $academicYear)
            ->get();
        $archiveSnapshot = $includeArchiveSnapshot ? $this->buildArchiveSnapshot($academicYear, $universityId, $sections) : [];

        $blockers = collect([
            [
                'label' => 'Modules without a stage',
                'count' => $readiness['sections_without_stage_count'],
                'detail' => 'Every module offering must be assigned to a stage before closure.',
            ],
            [
                'label' => 'Missing result rows',
                'count' => $missingMarks,
                'detail' => 'Every non-exempt enrolled student/module needs a result record before closure.',
            ],
            [
                'label' => 'Unpublished marks',
                'count' => $unpublishedMarks,
                'detail' => 'Draft or incomplete marks must be completed and published, or the student needs an approved exception.',
            ],
            [
                'label' => 'Students without a current stage',
                'count' => $readiness['missing_current_stage_count'],
                'detail' => 'Assign the student current stage before calculating progression.',
            ],
            [
                'label' => 'Student stage mismatches',
                'count' => $readiness['current_stage_mismatch_count'],
                'detail' => 'The student current stage must match ordinary module enrollments. Earlier-stage retakes remain allowed.',
            ],
            [
                'label' => 'Conflicting student stages',
                'count' => $readiness['conflicting_stage_count'],
                'detail' => 'Ordinary module enrollments for the same year must use one stage. Retake modules are allowed at an earlier stage.',
            ],
        ])->filter(fn (array $blocker) => $blocker['count'] > 0)->values();

        $warnings = collect([
            [
                'label' => 'Open tuition invoices',
                'count' => $openFinanceInvoices,
                'detail' => 'These balances will be archived with the closed academic year records.',
            ],
            [
                'label' => 'Open modules',
                'count' => $sections->filter(fn (CourseSection $section) => ! $section->trashed() && in_array($section->status, ['planned', 'active'], true))->count(),
                'detail' => 'Closing will mark planned and active modules as closed, then move them to archived modules.',
            ],
        ])->filter(fn (array $warning) => $warning['count'] > 0)->values();

        return [
            'academic_year' => $academicYear,
            'universities' => collect([$university?->name])->filter(),
            'university_ids' => collect([$universityId]),
            'university_id' => $universityId,
            'semester_count' => $semesters->count(),
            'semester_ids' => $semesterIds,
            'section_count' => $sections->count(),
            'section_ids' => $sectionIds,
            'student_count' => $studentCount,
            'enrollment_count' => $enrollmentsCount,
            'published_marks' => $publishedMarks,
            'entered_marks' => $enteredMarks,
            'missing_marks' => $missingMarks,
            'unpublished_marks' => $unpublishedMarks,
            'progression_readiness' => $readiness,
            'open_finance_invoices' => $openFinanceInvoices,
            'open_finance_by_currency' => $openFinanceByCurrency,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'blockers_count' => $readiness['blockers_count'],
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

    private function buildArchiveSnapshot(string $academicYear, int $universityId, $sections): array
    {
        $sectionIds = $sections->pluck('id')->filter()->values();

        if ($sectionIds->isEmpty()) {
            return [
                'version' => 2,
                'storage' => 'inline',
                'captured_at' => now()->toIso8601String(),
                'counts' => [],
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
            ->withCount([
                'enrollments' => fn ($query) => $query->withTrashed(),
                'marks' => fn ($query) => $query->withTrashed(),
                'assessmentItems' => fn ($query) => $query->withTrashed(),
                'attendances' => fn ($query) => $query->withTrashed(),
                'materials' => fn ($query) => $query->withTrashed(),
                'streamPosts' => fn ($query) => $query->withTrashed(),
                'messages' => fn ($query) => $query->withTrashed(),
                'timetables',
            ])
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
                            'credits' => $this->archiveCreditValue($course?->credits),
                            'status' => $course?->status,
                        ],
                        'semester' => [
                            'id' => $section->semester?->id,
                            'name' => $section->semester?->name,
                            'academic_year' => $section->semester?->academic_year,
                            'start_date' => $section->semester?->start_date?->toDateString(),
                            'end_date' => $section->semester?->end_date?->toDateString(),
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

        $recordCounts = [
            'modules' => count($moduleSnapshots),
            'enrollments' => Enrollment::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'marks' => Mark::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'assessments' => AssessmentItem::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'attendance' => Attendance::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'timetable' => Timetable::whereIn('course_section_id', $sectionIds)->count(),
            'materials' => CourseMaterial::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'stream_posts' => ClassStreamPost::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'class_messages' => ClassMessage::withTrashed()->whereIn('course_section_id', $sectionIds)->count(),
            'finance_transactions' => FinanceTransaction::withTrashed()
                ->where('academic_year', $academicYear)
                ->whereHas('student', fn ($student) => $student->where('university_id', $universityId))
                ->count(),
        ];

        if (array_sum($recordCounts) > config('academics.archive_inline_record_limit', 5000)) {
            return [
                'version' => 3,
                'storage' => 'relational',
                'captured_at' => now()->toIso8601String(),
                'counts' => $recordCounts,
                'modules' => $moduleSnapshots,
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

        $enrollments = Enrollment::withTrashed()->with(['student.department.college', 'courseSection'])
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

        $marks = Mark::withTrashed()->with(['student.department.college', 'course.department.college', 'courseSection.semester'])
            ->whereIn('course_section_id', $sectionIds)
            ->orderBy('course_section_id')
            ->orderBy('student_id')
            ->get()
            ->map(fn (Mark $mark) => $this->archiveMarkSnapshot($mark))
            ->values()
            ->all();

        $assessments = AssessmentItem::withTrashed()->with(['creator'])
            ->withCount(['submissions' => fn ($query) => $query->withTrashed()])
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

        $attendance = Attendance::withTrashed()->with(['student'])
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

        $materials = CourseMaterial::withTrashed()->with(['uploadedBy'])
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

        $streamPosts = ClassStreamPost::withTrashed()->with(['user'])
            ->withCount(['comments' => fn ($query) => $query->withTrashed(), 'reactions'])
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

        $messages = ClassMessage::withTrashed()->with(['sender', 'recipient'])
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

        $financeTransactions = FinanceTransaction::withTrashed()->with(['student.department.college'])
            ->where('academic_year', $academicYear)
            ->whereHas('student', fn ($student) => $student->where('university_id', $universityId))
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
            'version' => 3,
            'storage' => 'inline',
            'captured_at' => now()->toIso8601String(),
            'counts' => $recordCounts,
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
        Enrollment::whereIn('course_section_id', $summary['section_ids'])
            ->whereIn('status', ['enrolled', 'waitlisted'])
            ->orderBy('id')
            ->chunkById(500, function ($enrollments) use ($request, $summary, $now): void {
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

                if ($events !== []) {
                    EnrollmentEvent::insert($events);
                }
            });
    }

    private function emptySummary(): array
    {
        return [
            'academic_year' => null,
            'universities' => collect(),
            'university_ids' => collect(),
            'semester_count' => 0,
            'semester_ids' => collect(),
            'section_count' => 0,
            'section_ids' => collect(),
            'student_count' => 0,
            'enrollment_count' => 0,
            'published_marks' => 0,
            'entered_marks' => 0,
            'missing_marks' => 0,
            'unpublished_marks' => 0,
            'progression_readiness' => app(StudentProgressionService::class)->readiness(collect(), ''),
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

    private function managedStudentForYear(int $studentId, string $academicYear, User $user): Student
    {
        $query = Student::whereKey($studentId)
            ->whereHas('enrollments', function ($enrollments) use ($academicYear) {
                $enrollments
                    ->whereIn('status', ['enrolled', 'completed'])
                    ->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $academicYear));
            });
        OrganizationScope::apply($query, $user, 'student');

        return $query->firstOrFail();
    }

    private function ensureYearOpen(string $academicYear, int $universityId): void
    {
        if (AcademicYearClosure::where('university_id', $universityId)->where('academic_year', $academicYear)->exists()) {
            throw ValidationException::withMessages([
                'academic_year' => 'Progression decisions cannot be changed after the academic year is closed.',
            ]);
        }
    }

    private function accessibleClosingUniversities(User $user)
    {
        $query = University::orderBy('name');
        if (! $user->hasAnyRole(['super_administrator', 'administrator'])) {
            OrganizationScope::apply($query, $user, 'university');
        }

        return $query->get(['id', 'name', 'code']);
    }

    private function canResolveClosure(Request $request): bool
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_administrator', 'administrator'])
            || $user->hasDirectPermissionGrant('academic_setup.manage');
    }

    private function canManageClosure(Request $request, int $universityId): bool
    {
        $user = $request->user();
        if ($user->hasAnyRole(['super_administrator', 'administrator'])) {
            return true;
        }

        return $user->hasDirectPermissionGrant('academic_setup.manage')
            && (int) $user->university_id === $universityId
            && blank($user->college_id)
            && blank($user->department_id);
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
                $semesterQuery = Semester::withTrashed()->where('academic_year', $academicYear);
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
                $enrollmentQuery = Enrollment::withTrashed()->whereIn('course_section_id', $sectionIds)
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

    private function archiveYearRecords(array $summary, string $academicYear, int $universityId): void
    {
        $sectionIds = collect($summary['section_ids'] ?? [])->filter()->values();
        $semesterIds = collect($summary['semester_ids'] ?? [])->filter()->values();

        if ($sectionIds->isNotEmpty()) {
            Enrollment::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            Mark::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            $assessmentItemIds = AssessmentItem::whereIn('course_section_id', $sectionIds)->pluck('id');
            $streamPostIds = ClassStreamPost::whereIn('course_section_id', $sectionIds)->pluck('id');

            if ($assessmentItemIds->isNotEmpty()) {
                AssessmentSubmission::whereIn('assessment_item_id', $assessmentItemIds)
                    ->whereNull('deleted_at')
                    ->delete();
            }

            if ($streamPostIds->isNotEmpty()) {
                ClassStreamComment::whereIn('class_stream_post_id', $streamPostIds)
                    ->whereNull('deleted_at')
                    ->delete();

                ClassStreamReaction::whereIn('class_stream_post_id', $streamPostIds)->delete();
            }

            AssessmentItem::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            Attendance::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            CourseMaterial::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            ClassStreamPost::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            ClassMessage::whereIn('course_section_id', $sectionIds)
                ->whereNull('deleted_at')
                ->delete();

            Timetable::whereIn('course_section_id', $sectionIds)
                ->where('status', '!=', 'archived')
                ->update(['status' => 'archived']);
        }

        FinanceTransaction::where('academic_year', $academicYear)
            ->whereHas('student', fn ($student) => $student->where('university_id', $universityId))
            ->whereNull('deleted_at')
            ->delete();

        if ($semesterIds->isNotEmpty()) {
            Semester::whereIn('id', $semesterIds)
                ->whereNull('deleted_at')
                ->delete();
        }
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
        return $user->hasAnyRole(['super_administrator', 'administrator']);
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
        return (int) ($snapshot['counts'][$key] ?? count($snapshot[$key] ?? []));
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

    private function snapshotFilteredCountForArchive(array $snapshot, string $key, User $user, array $filters): int
    {
        $rows = collect($snapshot[$key] ?? []);
        if ($rows->isEmpty()) {
            return 0;
        }

        $scopedFilteredSectionIds = collect($snapshot['modules'] ?? [])
            ->filter(fn (array $row) => $this->snapshotRowMatchesScope($row, $user))
            ->filter(fn (array $row) => $this->snapshotRowMatchesFilters($row, $filters))
            ->pluck('course_section_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($key === 'modules') {
            return $scopedFilteredSectionIds->count();
        }

        return $rows
            ->filter(function (array $row) use ($user, $filters, $scopedFilteredSectionIds) {
                $inScope = (array_key_exists('college_id', $row) || array_key_exists('department_id', $row))
                    ? $this->snapshotRowMatchesScope($row, $user)
                    : $scopedFilteredSectionIds->contains((int) ($row['course_section_id'] ?? 0));

                if (! $inScope) {
                    return false;
                }

                if ($this->snapshotRowMatchesFilters($row, $filters)) {
                    return true;
                }

                return $scopedFilteredSectionIds->contains((int) ($row['course_section_id'] ?? 0));
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
                    'mark_id' => $mark->id,
                    'course_section_id' => $mark->course_section_id,
                    'course_id' => $mark->course_id,
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

    private function archiveResultKeyFromModel(Mark $mark): string
    {
        if ($mark->id) {
            return 'mark:'.$mark->id;
        }

        return implode('|', [
            'fallback',
            (int) $mark->student_id,
            (int) $mark->course_id,
            (int) ($mark->course_section_id ?? 0),
            number_format((float) ($mark->final_mark ?? 0), 2, '.', ''),
        ]);
    }

    private function archiveResultRowKey(array $row): string
    {
        if (! empty($row['mark_id'])) {
            return 'mark:'.(int) $row['mark_id'];
        }

        return implode('|', [
            'fallback',
            (int) ($row['student_id'] ?? 0),
            (int) ($row['course_id'] ?? 0),
            (int) ($row['course_section_id'] ?? 0),
            number_format((float) ($row['final_mark'] ?? 0), 2, '.', ''),
        ]);
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

    private function archiveCreditValue(mixed $credits): int|float|null
    {
        if ($credits === null || ! is_numeric($credits)) {
            return null;
        }

        $numericCredits = (float) $credits;

        return floor($numericCredits) === $numericCredits ? (int) $numericCredits : $numericCredits;
    }
}
