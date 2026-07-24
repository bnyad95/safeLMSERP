<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyRole('super_administrator', 'administrator', 'university_president');

        $canViewFinanceAnalytics = $this->canViewFinanceAnalytics();
        $filters = $this->analyticsFilters($request);
        $activeTab = in_array($request->query('tab'), ['academic', 'attendance', 'finance', 'courses'], true)
            ? $request->query('tab')
            : 'academic';
        abort_if($activeTab === 'finance' && ! $canViewFinanceAnalytics, 403);

        $markStats = $this->markStats($filters);
        $attendanceRisk = $this->attendanceRisk($filters);
        $gpaTrend = $this->gpaTrend($filters);
        $unpaidBalances = $canViewFinanceAnalytics ? $this->unpaidBalances($filters) : collect();
        $coursePerformance = $this->coursePerformance($filters);

        return view('analytics.index', [
            'stats' => collect([
                [
                    'label' => 'Attendance Risk',
                    'value' => number_format($attendanceRisk->where('risk', 'High')->count()),
                    'detail' => 'Students below 75%',
                ],
                [
                    'label' => 'Average GPA',
                    'value' => $markStats['total'] > 0 ? number_format($markStats['average_gpa'], 2) : 'N/A',
                    'detail' => 'Published marks only',
                ],
                [
                    'label' => 'Unpaid Balance',
                    'value' => $this->formatCurrencyTotals($unpaidBalances),
                    'detail' => $unpaidBalances->count().' open student accounts',
                    'finance' => true,
                ],
                [
                    'label' => 'Course Pass Rate',
                    'value' => $markStats['total'] > 0 ? number_format(($markStats['passed'] / $markStats['total']) * 100, 1).'%' : 'N/A',
                    'detail' => 'Across published course marks',
                ],
            ])->reject(fn ($stat) => ($stat['finance'] ?? false) && ! $canViewFinanceAnalytics)->values(),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($canViewFinanceAnalytics),
            'activeTab' => $activeTab,
            'canViewFinanceAnalytics' => $canViewFinanceAnalytics,
            'attendanceRisk' => $attendanceRisk,
            'gpaTrend' => $gpaTrend,
            'unpaidBalances' => $unpaidBalances,
            'coursePerformance' => $coursePerformance,
        ]);
    }

    private function analyticsFilters(Request $request): array
    {
        return [
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage' => trim((string) ($request->query('stage') ?? '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'academic_year' => trim((string) $request->query('academic_year', '')),
        ];
    }

    private function filterOptions(bool $includeFinance = false): array
    {
        $academicYears = Semester::query()
            ->pluck('academic_year');

        if ($includeFinance) {
            $academicYears = $academicYears->merge(FinanceTransaction::whereNotNull('academic_year')->pluck('academic_year'));
        }

        $academicYears = $academicYears->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return [
            'colleges' => College::orderBy('name')->get(['id', 'name']),
            'departments' => Department::with('college')->orderBy('name')->get(['id', 'name', 'college_id']),
            'stages' => CourseSection::whereNotNull('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level'),
            'semesters' => Semester::orderByDesc('academic_year')->orderBy('name')->get(['id', 'name', 'academic_year']),
            'academicYears' => $academicYears,
        ];
    }

    private function applyStudentFilters($query, array $filters): void
    {
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('enrollments.courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('enrollments.courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('enrollments.courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyMarkFilters($query, array $filters): void
    {
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyAttendanceFilters($query, array $filters): void
    {
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyFinanceFilters($query, array $filters): void
    {
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('student.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('student', fn ($student) => $student->where('department_id', $filters['department_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->where('academic_year', $filters['academic_year']));
    }

    private function activeStudentRelation($query, array $filters): void
    {
        $query->whereHas('student', function ($student) use ($filters) {
            $student->where('status', 'Active');
            $this->applyStudentFilters($student, $filters);
        });
    }

    private function publishedMarkQuery(array $filters)
    {
        return Mark::query()
            ->where('visibility_status', 'published')
            ->whereNotNull('final_mark')
            ->tap(fn ($query) => $this->activeStudentRelation($query, $filters))
            ->tap(fn ($query) => $this->applyMarkFilters($query, $filters));
    }

    private function attendanceQuery(array $filters)
    {
        return Attendance::query()
            ->tap(fn ($query) => $this->activeStudentRelation($query, $filters))
            ->tap(fn ($query) => $this->applyAttendanceFilters($query, $filters));
    }

    private function markStats(array $filters): array
    {
        $query = $this->publishedMarkQuery($filters);
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN final_mark >= 50 THEN 1 ELSE 0 END) as passed')
            ->selectRaw('AVG('.$this->gradePointSql().') as average_gpa')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'passed' => (int) ($row->passed ?? 0),
            'average_gpa' => round((float) ($row->average_gpa ?? 0), 2),
        ];
    }

    private function attendanceRisk(array $filters)
    {
        $presentSql = "SUM(CASE WHEN status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END)";
        $rateSql = "100.0 * {$presentSql} / COUNT(*)";

        return $this->attendanceQuery($filters)
            ->with('student.department')
            ->select('student_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("{$rateSql} as rate")
            ->groupBy('student_id')
            ->havingRaw("{$rateSql} < ?", [85])
            ->orderByRaw($rateSql)
            ->limit(10)
            ->get()
            ->map(function (Attendance $row) {
                $rate = round((float) $row->rate, 1);

                return [
                    'student' => $row->student,
                    'total' => (int) $row->total,
                    'absent' => (int) $row->absent,
                    'rate' => $rate,
                    'risk' => $rate < 75 ? 'High' : 'Watch',
                ];
            })
            ->values();
    }

    private function gpaTrend(array $filters)
    {
        return $this->publishedMarkQuery($filters)
            ->leftJoin('course_sections', 'marks.course_section_id', '=', 'course_sections.id')
            ->leftJoin('semesters', 'course_sections.semester_id', '=', 'semesters.id')
            ->select('course_sections.semester_id', 'semesters.name as semester_name', 'semesters.academic_year')
            ->selectRaw('COUNT(*) as marks_count')
            ->selectRaw('AVG(final_mark) as average_mark')
            ->selectRaw('AVG('.$this->gradePointSql().') as gpa')
            ->groupBy('course_sections.semester_id', 'semesters.name', 'semesters.academic_year')
            ->orderBy('semesters.academic_year')
            ->orderBy('semesters.name')
            ->get()
            ->map(function ($row) {
                $semester = $row->semester_id
                    ? trim($row->semester_name.' '.$row->academic_year)
                    : 'Unassigned';

                return [
                    'semester' => $semester,
                    'marks_count' => (int) $row->marks_count,
                    'average_mark' => round((float) $row->average_mark, 1),
                    'gpa' => round((float) $row->gpa, 2),
                ];
            })
            ->values();
    }

    private function unpaidBalances(array $filters)
    {
        $balanceSql = "SUM(CASE WHEN type IN ('invoice', 'refund') THEN amount ELSE 0 END) - SUM(CASE WHEN type IN ('payment', 'discount', 'scholarship') THEN amount ELSE 0 END)";

        return FinanceTransaction::query()
            ->with('student')
            ->tap(fn ($query) => $this->activeStudentRelation($query, $filters))
            ->tap(fn ($query) => $this->applyFinanceFilters($query, $filters))
            ->select('student_id', 'currency')
            ->selectRaw("{$balanceSql} as balance")
            ->selectRaw("SUM(CASE WHEN type = 'invoice' AND due_date < ? AND payment_status NOT IN ('paid', 'cancelled') THEN 1 ELSE 0 END) as overdue", [now()->toDateString()])
            ->selectRaw('MAX(transaction_date) as last_activity')
            ->groupBy('student_id', 'currency')
            ->havingRaw("{$balanceSql} > 0")
            ->orderByRaw("{$balanceSql} DESC")
            ->limit(10)
            ->get()
            ->map(function (FinanceTransaction $row) {
                return [
                    'student' => $row->student,
                    'balance' => round((float) $row->balance, 2),
                    'currency' => $row->currency,
                    'overdue' => (int) $row->overdue,
                    'last_activity' => $row->last_activity,
                ];
            })
            ->values();
    }

    private function coursePerformance(array $filters)
    {
        $markRows = $this->publishedMarkQuery($filters)
            ->select('course_id')
            ->selectRaw('COUNT(*) as marks_count')
            ->selectRaw('AVG(final_mark) as average_mark')
            ->selectRaw('100.0 * SUM(CASE WHEN final_mark >= 50 THEN 1 ELSE 0 END) / COUNT(*) as pass_rate')
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');
        $attendanceRows = $this->attendanceQuery($filters)
            ->select('course_id')
            ->selectRaw("100.0 * SUM(CASE WHEN status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END) / COUNT(*) as attendance_rate")
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');
        $courseIds = $markRows->keys()->merge($attendanceRows->keys())->filter()->unique()->values();

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Course::with('department')
            ->whereIn('id', $courseIds)
            ->get()
            ->map(function (Course $course) use ($markRows, $attendanceRows) {
                $markRow = $markRows->get($course->id);
                $attendanceRow = $attendanceRows->get($course->id);

                return [
                    'course' => $course,
                    'marks_count' => $markRow ? (int) $markRow->marks_count : 0,
                    'average_mark' => $markRow ? round((float) $markRow->average_mark, 1) : null,
                    'pass_rate' => $markRow ? round((float) $markRow->pass_rate, 1) : null,
                    'attendance_rate' => $attendanceRow ? round((float) $attendanceRow->attendance_rate, 1) : null,
                ];
            })
            ->sortBy(fn ($row) => $row['average_mark'] ?? 999)
            ->take(12)
            ->values();
    }

    private function formatCurrencyTotals($rows): string
    {
        if ($rows->isEmpty()) {
            return '0.00 IQD';
        }

        return $rows
            ->groupBy('currency')
            ->map(fn ($currencyRows, $currency) => number_format((float) $currencyRows->sum('balance'), 2).' '.$currency)
            ->implode(' / ');
    }

    private function averageGpa($marks): float
    {
        $marks = collect($marks)->filter(fn ($mark) => (float) $mark->final_mark > 0);

        if ($marks->isEmpty()) {
            return 0.0;
        }

        return round($marks->avg(fn ($mark) => $this->gradePoint((float) $mark->final_mark)), 2);
    }

    private function gradePointSql(): string
    {
        return "CASE
            WHEN final_mark >= 90 THEN 4.0
            WHEN final_mark >= 80 THEN 3.5
            WHEN final_mark >= 70 THEN 3.0
            WHEN final_mark >= 60 THEN 2.5
            WHEN final_mark >= 50 THEN 2.0
            ELSE 0.0
        END";
    }

    private function gradePoint(float $mark): float
    {
        return match (true) {
            $mark >= 90 => 4.0,
            $mark >= 80 => 3.5,
            $mark >= 70 => 3.0,
            $mark >= 60 => 2.5,
            $mark >= 50 => 2.0,
            default => 0.0,
        };
    }

    private function canViewFinanceAnalytics(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super_administrator')
            || $user?->hasRole('chief_accountant')
            || $user?->hasDirectPermissionGrant('finance.view');
    }
}
