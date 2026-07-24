<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Http\Request;

class IntegrationApiController extends Controller
{
    public function students(Request $request)
    {
        $this->requireApiPermission($request, ['students.view', 'students.update', 'students.create']);

        $query = trim((string) $request->query('q', ''));
        $filters = $this->filters($request);
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $students = Student::with(['university', 'department.college'])
            ->when($query !== '', fn ($builder) => $builder->where(function ($q) use ($query) {
                $q->where('student_id', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            }))
            ->orderBy('student_id');
        $this->applyStudentFilters($students, $filters);

        return $students
            ->paginate($perPage)
            ->through(fn (Student $student) => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'status' => $student->status,
                'admission_status' => $student->admission_status,
                'department' => $student->department?->only(['id', 'name', 'code']),
                'college' => $student->department?->college?->only(['id', 'name', 'code']),
                'university' => $student->university?->only(['id', 'name', 'code']),
                'updated_at' => $student->updated_at?->toIso8601String(),
            ]);
    }

    public function courses(Request $request)
    {
        $this->requireApiPermission($request, ['courses.view']);

        $query = trim((string) $request->query('q', ''));
        $filters = $this->filters($request);
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $courses = Course::with(['university', 'department.college'])
            ->when($query !== '', fn ($builder) => $builder->where(function ($q) use ($query) {
                $q->where('code', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            }))
            ->orderBy('code');
        $this->applyCourseFilters($courses, $filters);

        return $courses
            ->paginate($perPage)
            ->through(fn (Course $course) => [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'credits' => $course->credits,
                'status' => $course->status,
                'department' => $course->department?->only(['id', 'name', 'code']),
                'college' => $course->department?->college?->only(['id', 'name', 'code']),
                'university' => $course->university?->only(['id', 'name', 'code']),
                'updated_at' => $course->updated_at?->toIso8601String(),
            ]);
    }

    public function marks(Request $request)
    {
        $this->requireApiPermission($request, ['marks.view', 'marks.enter', 'marks.review', 'marks.approve', 'marks.publish']);

        $filters = $this->filters($request);
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $marks = Mark::with(['student', 'course', 'courseSection.semester'])
            ->when($request->filled('student_id'), fn ($builder) => $builder->whereHas('student', fn ($studentQuery) => $studentQuery->where('student_id', $request->query('student_id'))))
            ->when($request->filled('course_code'), fn ($builder) => $builder->whereHas('course', fn ($courseQuery) => $courseQuery->where('code', $request->query('course_code'))))
            ->orderBy('student_id')
            ->orderBy('course_id');
        $this->applyMarkFilters($marks, $filters);

        return $marks
            ->paginate($perPage)
            ->through(fn (Mark $mark) => [
                'id' => $mark->id,
                'student' => [
                    'id' => $mark->student?->id,
                    'student_id' => $mark->student?->student_id,
                    'full_name' => $mark->student?->full_name,
                    'email' => $mark->student?->email,
                ],
                'course' => [
                    'id' => $mark->course?->id,
                    'code' => $mark->course?->code,
                    'name' => $mark->course?->name,
                ],
                'assignments' => $mark->assignments,
                'quizzes' => $mark->quizzes,
                'midterm' => $mark->midterm,
                'practical' => $mark->practical,
                'final_exam' => $mark->final_exam,
                'final_mark' => $mark->final_mark,
                'status' => $mark->status,
                'submission_status' => $mark->submission_status,
                'visibility_status' => $mark->visibility_status,
                'updated_at' => $mark->updated_at?->toIso8601String(),
            ]);
    }

    private function requireApiPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        abort_unless($user, 401);

        if ($user->hasRole('super_administrator') || $user->hasAnyPermission($permissions)) {
            return;
        }

        abort(403);
    }

    private function filters(Request $request): array
    {
        return [
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'stage' => trim((string) $request->query('stage', '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'academic_year' => trim((string) $request->query('academic_year', '')),
        ];
    }

    private function applyStudentFilters($query, array $filters): void
    {
        $this->applyStudentOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('enrollments.courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('enrollments.courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('enrollments.courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyCourseFilters($query, array $filters): void
    {
        $this->applyCourseOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']));
    }

    private function applyMarkFilters($query, array $filters): void
    {
        $this->applyCourseRecordOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyStudentOrgScope($query): void
    {
        $user = request()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->department_id) {
            $query->where('department_id', $user->department_id);
        } elseif ($user?->college_id) {
            $query->whereHas('department', fn ($department) => $department->where('college_id', $user->college_id));
        } elseif ($user?->university_id) {
            $query->where('university_id', $user->university_id);
        }
    }

    private function applyCourseOrgScope($query): void
    {
        $user = request()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->department_id) {
            $query->where('department_id', $user->department_id);
        } elseif ($user?->college_id) {
            $query->whereHas('department', fn ($department) => $department->where('college_id', $user->college_id));
        } elseif ($user?->university_id) {
            $query->where('university_id', $user->university_id);
        }
    }

    private function applyCourseRecordOrgScope($query): void
    {
        $user = request()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->department_id) {
            $query->whereHas('course', fn ($course) => $course->where('department_id', $user->department_id));
        } elseif ($user?->college_id) {
            $query->whereHas('course.department', fn ($department) => $department->where('college_id', $user->college_id));
        } elseif ($user?->university_id) {
            $query->whereHas('course', fn ($course) => $course->where('university_id', $user->university_id));
        }
    }
}
