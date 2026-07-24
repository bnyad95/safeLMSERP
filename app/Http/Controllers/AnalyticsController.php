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

        $students = Student::with(['department.college', 'enrollments.courseSection.semester'])
            ->where('status', 'Active')
            ->tap(fn ($query) => $this->applyStudentFilters($query, $filters))
            ->get();
        $studentIds = $students->pluck('id');
        $marks = Mark::with(['student', 'course.department', 'courseSection.semester'])
            ->whereIn('student_id', $studentIds)
            ->where('visibility_status', 'published')
            ->whereNotNull('final_mark')
            ->tap(fn ($query) => $this->applyMarkFilters($query, $filters))
            ->get();
        $attendances = Attendance::with(['student.department', 'course.department', 'courseSection.semester'])
            ->whereIn('student_id', $studentIds)
            ->tap(fn ($query) => $this->applyAttendanceFilters($query, $filters))
            ->get();
        $financeTransactions = $canViewFinanceAnalytics
            ? FinanceTransaction::with('student.department.college')
                ->whereIn('student_id', $studentIds)
                ->tap(fn ($query) => $this->applyFinanceFilters($query, $filters))
                ->get()
            : collect();

        $attendanceRisk = $this->attendanceRisk($students, $attendances);
        $gpaTrend = $this->gpaTrend($marks);
        $unpaidBalances = $this->unpaidBalances($financeTransactions);
        $coursePerformance = $this->coursePerformance($marks, $attendances);

        return view('analytics.index', [
            'stats' => collect([
                [
                    'label' => 'Attendance Risk',
                    'value' => number_format($attendanceRisk->where('risk', 'High')->count()),
                    'detail' => 'Students below 75%',
                ],
                [
                    'label' => 'Average GPA',
                    'value' => $marks->isNotEmpty() ? number_format($this->averageGpa($marks), 2) : 'N/A',
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
                    'value' => $marks->isNotEmpty() ? number_format(($marks->where('final_mark', '>=', 50)->count() / $marks->count()) * 100, 1).'%' : 'N/A',
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

    private function attendanceRisk($students, $attendances)
    {
        return $students
            ->map(function (Student $student) use ($attendances) {
                $records = $attendances->where('student_id', $student->id);
                $total = $records->count();
                $present = $records->whereIn('status', ['present', 'late', 'excused'])->count();
                $rate = $total > 0 ? round(($present / $total) * 100, 1) : null;

                return [
                    'student' => $student,
                    'total' => $total,
                    'absent' => $records->where('status', 'absent')->count(),
                    'rate' => $rate,
                    'risk' => is_null($rate) ? 'No data' : ($rate < 75 ? 'High' : ($rate < 85 ? 'Watch' : 'Normal')),
                ];
            })
            ->filter(fn ($row) => $row['total'] > 0 && in_array($row['risk'], ['High', 'Watch'], true))
            ->sortBy(fn ($row) => $row['rate'])
            ->take(10)
            ->values();
    }

    private function gpaTrend($marks)
    {
        return $marks
            ->groupBy(function (Mark $mark) {
                $semester = $mark->courseSection?->semester;

                return $semester ? $semester->name.' '.$semester->academic_year : 'Unassigned';
            })
            ->map(function ($semesterMarks, string $semester) {
                return [
                    'semester' => $semester,
                    'marks_count' => $semesterMarks->count(),
                    'average_mark' => round((float) $semesterMarks->avg('final_mark'), 1),
                    'gpa' => $this->averageGpa($semesterMarks),
                ];
            })
            ->sortBy('semester')
            ->values();
    }

    private function unpaidBalances($financeTransactions)
    {
        return $financeTransactions
            ->groupBy(fn (FinanceTransaction $transaction) => $transaction->student_id.'|'.$transaction->currency)
            ->map(function ($transactions) {
                $charges = $transactions->whereIn('type', FinanceTransaction::chargeTypes())->sum(fn ($transaction) => (float) $transaction->amount);
                $credits = $transactions->whereIn('type', FinanceTransaction::creditTypes())->sum(fn ($transaction) => (float) $transaction->amount);
                $balance = round($charges - $credits, 2);
                $student = $transactions->first()->student;

                return [
                    'student' => $student,
                    'balance' => $balance,
                    'currency' => $transactions->first()->currency,
                    'overdue' => $transactions
                        ->where('type', 'invoice')
                        ->filter(fn ($transaction) => $transaction->due_date && $transaction->due_date->isPast() && ! in_array($transaction->payment_status, ['paid', 'cancelled'], true))
                        ->count(),
                    'last_activity' => optional($transactions->sortByDesc('transaction_date')->first()->transaction_date)->format('Y-m-d'),
                ];
            })
            ->filter(fn ($row) => $row['student'] && $row['balance'] > 0)
            ->sortByDesc('balance')
            ->take(10)
            ->values();
    }

    private function coursePerformance($marks, $attendances)
    {
        return Course::with('department')
            ->orderBy('code')
            ->get()
            ->map(function (Course $course) use ($marks, $attendances) {
                $courseMarks = $marks->where('course_id', $course->id);
                $courseAttendance = $attendances->where('course_id', $course->id);
                $attendanceRate = $courseAttendance->isNotEmpty()
                    ? round(($courseAttendance->whereIn('status', ['present', 'late', 'excused'])->count() / $courseAttendance->count()) * 100, 1)
                    : null;

                return [
                    'course' => $course,
                    'marks_count' => $courseMarks->count(),
                    'average_mark' => $courseMarks->isNotEmpty() ? round((float) $courseMarks->avg('final_mark'), 1) : null,
                    'pass_rate' => $courseMarks->isNotEmpty()
                        ? round(($courseMarks->where('final_mark', '>=', 50)->count() / $courseMarks->count()) * 100, 1)
                        : null,
                    'attendance_rate' => $attendanceRate,
                ];
            })
            ->filter(fn ($row) => $row['marks_count'] > 0 || ! is_null($row['attendance_rate']))
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
