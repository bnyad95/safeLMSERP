<?php

namespace App\Http\Controllers;

use App\Models\CourseMaterial;
use App\Models\FinanceTransaction;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;

class RoleWorkspaceController extends Controller
{
    public function library(Request $request)
    {
        abort_unless($request->user()?->hasRole('librarian'), 403);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'file_type' => trim((string) $request->query('file_type', '')),
            'visibility' => in_array($request->query('visibility'), ['draft', 'published'], true) ? $request->query('visibility') : '',
        ];

        $materialsQuery = CourseMaterial::with(['course.department', 'courseSection'])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('title', 'like', "%{$filters['q']}%")
                    ->orWhere('description', 'like', "%{$filters['q']}%")
                    ->orWhereHas('course', fn ($course) => $course
                        ->where('name', 'like', "%{$filters['q']}%")
                        ->orWhere('code', 'like', "%{$filters['q']}%"));
            }))
            ->when($filters['file_type'] !== '', fn ($query) => $query->where('file_type', $filters['file_type']))
            ->when($filters['visibility'] !== '', fn ($query) => $query->where('visibility', $filters['visibility']))
            ->latest();

        $this->scopeCourseMaterials($materialsQuery, $request);

        $resourceStats = CourseMaterial::query();
        $this->scopeCourseMaterials($resourceStats, $request);
        $documentStats = StudentDocument::query();
        OrganizationScope::apply($documentStats, $request->user(), 'student_record');

        $materials = (clone $materialsQuery)->paginate(12)->withQueryString();
        $stats = [
            ['label' => __('Resources'), 'value' => number_format((clone $resourceStats)->count()), 'detail' => __('Course materials in the LMS')],
            ['label' => __('Published'), 'value' => number_format((clone $resourceStats)->where('visibility', 'published')->count()), 'detail' => __('Visible learning resources')],
            ['label' => __('Drafts'), 'value' => number_format((clone $resourceStats)->where('visibility', 'draft')->count()), 'detail' => __('Need teacher publication')],
            ['label' => __('Student Documents'), 'value' => number_format($documentStats->count()), 'detail' => __('Academic document records')],
        ];
        $fileTypes = CourseMaterial::getFileTypeOptions();

        return view('workspaces.library', compact('materials', 'stats', 'filters', 'fileTypes'));
    }

    public function parent(Request $request)
    {
        abort_unless($request->user()?->hasRole('parent_user'), 403);

        $children = Student::with([
            'department.college',
            'guardians',
            'enrollments.courseSection.course',
            'marks.course',
            'attendances.course',
            'financeTransactions',
        ])
            ->whereHas('guardians', fn ($guardian) => $guardian->where('email', $request->user()->email))
            ->orderBy('full_name')
            ->get();

        $childSummaries = $children->map(function (Student $student) {
            $publishedMarks = $student->marks->where('visibility_status', 'published');
            $attendanceTotal = $student->attendances->count();
            $attendancePresent = $student->attendances->where('status', 'present')->count();
            $finance = $this->financeSummary($student);

            return [
                'student' => $student,
                'classes' => $student->enrollments
                    ->where('status', 'enrolled')
                    ->filter(fn ($enrollment) => in_array($enrollment->courseSection?->status, ['planned', 'active'], true))
                    ->count(),
                'average' => $publishedMarks->isNotEmpty() ? number_format((float) $publishedMarks->avg('final_mark'), 1) : __('N/A'),
                'attendance' => $attendanceTotal > 0 ? number_format(($attendancePresent / $attendanceTotal) * 100, 1).'%' : __('N/A'),
                'balance' => $finance['value'],
                'finance_detail' => $finance['detail'],
                'recent_marks' => $publishedMarks->sortByDesc('published_at')->take(4),
                'recent_attendance' => $student->attendances->sortByDesc('date')->take(4),
            ];
        });

        $stats = [
            ['label' => __('Linked Students'), 'value' => number_format($children->count()), 'detail' => __('Matched by guardian email')],
            ['label' => __('Active Classes'), 'value' => number_format($childSummaries->sum('classes')), 'detail' => __('Current enrolled classes')],
            ['label' => __('Published Results'), 'value' => number_format($children->flatMap->marks->where('visibility_status', 'published')->count()), 'detail' => __('Visible marks only')],
        ];

        return view('workspaces.parent', compact('childSummaries', 'stats'));
    }

    public function reception(Request $request)
    {
        abort_unless($request->user()?->hasRole('receptionist'), 403);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), ['Active', 'Inactive', 'Graduated'], true) ? $request->query('status') : '',
        ];

        $studentsQuery = Student::with(['department.college', 'guardians']);
        OrganizationScope::apply($studentsQuery, $request->user(), 'student');

        $students = $studentsQuery
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($search) use ($filters) {
                $search->where('full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('student_id', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%")
                    ->orWhere('phone', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        $studentStats = Student::query();
        OrganizationScope::apply($studentStats, $request->user(), 'student');
        $guardianStats = StudentGuardian::query();
        OrganizationScope::apply($guardianStats, $request->user(), 'student_record');

        $stats = [
            ['label' => __('Students'), 'value' => number_format((clone $studentStats)->count()), 'detail' => __('Available front-desk records')],
            ['label' => __('Active'), 'value' => number_format((clone $studentStats)->where('status', 'Active')->count()), 'detail' => __('Currently active students')],
            ['label' => __('With Guardians'), 'value' => number_format($guardianStats->distinct('student_id')->count('student_id')), 'detail' => __('Emergency or guardian contacts')],
        ];

        return view('workspaces.reception', compact('students', 'filters', 'stats'));
    }

    private function financeSummary(Student $student): array
    {
        $transactions = $student->financeTransactions->where('status', '!=', 'cancelled');
        $currency = $transactions->first()?->currency ?? 'USD';
        $charges = $transactions->whereIn('type', FinanceTransaction::chargeTypes())->sum(fn ($transaction) => (float) $transaction->amount);
        $credits = $transactions->whereIn('type', FinanceTransaction::creditTypes())->sum(fn ($transaction) => (float) $transaction->amount);
        $balance = max(0, $charges - $credits);

        return [
            'value' => $balance > 0 ? money($balance, $currency).' '.$currency : __('No balance'),
            'detail' => $balance > 0 ? __('Outstanding tuition balance') : __('No unpaid tuition charges'),
        ];
    }

    private function scopeCourseMaterials($query, Request $request): void
    {
        $query->whereHas('course', function ($course) use ($request) {
            OrganizationScope::apply($course, $request->user(), 'course');
        });
    }
}
