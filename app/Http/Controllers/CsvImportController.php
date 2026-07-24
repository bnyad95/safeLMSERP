<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Attendance;
use App\Models\FinanceTransaction;
use App\Models\ImportLog;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Services\CsvImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends Controller
{
    public function __construct(private CsvImportService $service) {}

    public function index(Request $request)
    {
        $this->authorizeDataExchange();
        $filters = $this->filters($request);
        $activeTab = in_array($request->query('tab'), ['imports', 'exports', 'api', 'history'], true)
            ? $request->query('tab')
            : 'imports';

        $recentImports = ImportLog::with('importedBy')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('integrations.index', [
            'recentImports' => $recentImports,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'activeTab' => $activeTab,
            'samples' => [
                'students' => $this->service->getSampleStudentsCsv(),
                'courses' => $this->service->getSampleCoursesCsv(),
                'marks' => $this->service->getSampleMarksCsv(),
            ],
        ]);
    }

    public function importStudents(Request $request)
    {
        $this->requireAnyPermission('students.create', 'students.update');

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $log = $this->service->importStudents($file->getPathname(), $file->getClientOriginalName());

        return redirect()->route('integrations.index')->with('success',
            "Import complete: {$log->successful} students imported, {$log->failed} failed."
        );
    }

    public function importCourses(Request $request)
    {
        $this->requireAnyPermission('courses.create', 'courses.update');

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $log = $this->service->importCourses($file->getPathname(), $file->getClientOriginalName());

        return redirect()->route('integrations.index')->with('success',
            "Import complete: {$log->successful} courses imported, {$log->failed} failed."
        );
    }

    public function importMarks(Request $request)
    {
        $this->requireAnyPermission('marks.enter', 'marks.review', 'marks.approve');

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $log = $this->service->importMarks($file->getPathname(), $file->getClientOriginalName());

        return redirect()->route('integrations.index')->with('success',
            "Import complete: {$log->successful} marks imported, {$log->failed} failed."
        );
    }

    public function exportStudents(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('students.view', 'students.update', 'students.create');
        $filters = $this->filters($request);

        return $this->csvResponse('students-export.csv', [
            'Student ID',
            'Full Name',
            'Email',
            'Phone',
            'University',
            'College',
            'Department',
            'Status',
            'Admission Status',
            'Admission Date',
            'Admission Type',
            'Previous School',
            'Emergency Contact',
            'Emergency Relationship',
            'Emergency Phone',
        ], function ($output) use ($filters) {
            $query = Student::with(['university', 'department.college'])
                ->orderBy('student_id');
            $this->applyStudentFilters($query, $filters);

            $query
                ->chunk(200, function ($students) use ($output) {
                    foreach ($students as $student) {
                        fputcsv($output, [
                            $student->student_id,
                            $student->full_name,
                            $student->email,
                            $student->phone,
                            $student->university->name ?? '',
                            $student->department->college->name ?? '',
                            $student->department->name ?? '',
                            $student->status,
                            $student->admission_status,
                            $student->admission_date?->format('Y-m-d'),
                            $student->admission_type,
                            $student->previous_school,
                            $student->emergency_contact_name,
                            $student->emergency_contact_relationship,
                            $student->emergency_contact_phone,
                        ]);
                    }
                });
        });
    }

    public function exportCourses(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('courses.view');
        $filters = $this->filters($request);

        return $this->csvResponse('courses-export.csv', [
            'Code',
            'Name',
            'Department',
            'College',
            'Credits',
            'Status',
        ], function ($output) use ($filters) {
            $query = Course::with(['department.college'])
                ->orderBy('code');
            $this->applyCourseFilters($query, $filters);

            $query
                ->chunk(200, function ($courses) use ($output) {
                    foreach ($courses as $course) {
                        fputcsv($output, [
                            $course->code,
                            $course->name,
                            $course->department->name ?? '',
                            $course->department->college->name ?? '',
                            $course->credits,
                            $course->status,
                        ]);
                    }
                });
        });
    }

    public function exportMarks(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('marks.view', 'marks.enter', 'marks.review', 'marks.approve', 'marks.publish');
        $filters = $this->filters($request);

        return $this->csvResponse('marks-export.csv', [
            'Student ID',
            'Student Email',
            'Student Name',
            'Course Code',
            'Section',
            'Assignments',
            'Quizzes',
            'Midterm',
            'Practical',
            'Final Exam',
            'Final Mark',
            'Status',
            'Submission Status',
            'Visibility Status',
        ], function ($output) use ($filters) {
            $query = Mark::with(['student', 'course', 'courseSection.semester'])
                ->orderBy('student_id')
                ->orderBy('course_id');
            $this->applyMarkFilters($query, $filters);

            $query
                ->chunk(200, function ($marks) use ($output) {
                    foreach ($marks as $mark) {
                        fputcsv($output, [
                            $mark->student->student_id ?? '',
                            $mark->student->email ?? '',
                            $mark->student->full_name ?? '',
                            $mark->course->code ?? '',
                            $mark->courseSection->section_code ?? '',
                            $mark->assignments,
                            $mark->quizzes,
                            $mark->midterm,
                            $mark->practical,
                            $mark->final_exam,
                            $mark->final_mark,
                            $mark->status,
                            $mark->submission_status,
                            $mark->visibility_status,
                        ]);
                    }
                });
        });
    }

    public function exportTeachers(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('teachers.view', 'teachers.update', 'teachers.create');
        $filters = $this->filters($request);

        return $this->csvResponse('teachers-export.csv', [
            'Staff ID', 'Full Name', 'Email', 'Title', 'University', 'College', 'Department', 'Status',
        ], function ($output) use ($filters) {
            $query = Teacher::with(['university', 'department.college'])
                ->orderBy('staff_id');
            $this->applyTeacherFilters($query, $filters);

            $query->chunk(200, function ($teachers) use ($output) {
                foreach ($teachers as $teacher) {
                    fputcsv($output, [
                        $teacher->staff_id,
                        $teacher->full_name,
                        $teacher->email,
                        $teacher->title,
                        $teacher->university->name ?? '',
                        $teacher->department->college->name ?? '',
                        $teacher->department->name ?? '',
                        $teacher->status,
                    ]);
                }
            });
        });
    }

    public function exportEnrollments(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('enrollments.view', 'enrollments.manage');
        $filters = $this->filters($request);

        return $this->csvResponse('enrollments-export.csv', [
            'Student ID', 'Student Name', 'Student Email', 'Course Code', 'Course Name', 'Module', 'Stage', 'Semester', 'Academic Year', 'Teacher', 'Status', 'Enrolled At', 'Removed At',
        ], function ($output) use ($filters) {
            $query = Enrollment::with(['student', 'courseSection.course.department.college', 'courseSection.semester', 'courseSection.teacher'])
                ->orderBy('student_id');
            $this->applyEnrollmentFilters($query, $filters);

            $query->chunk(200, function ($enrollments) use ($output) {
                foreach ($enrollments as $enrollment) {
                    $section = $enrollment->courseSection;
                    fputcsv($output, [
                        $enrollment->student->student_id ?? '',
                        $enrollment->student->full_name ?? '',
                        $enrollment->student->email ?? '',
                        $section?->course?->code ?? '',
                        $section?->course?->name ?? '',
                        $section?->section_code ?? '',
                        $section?->grade_level ?? '',
                        $section?->semester?->name ?? '',
                        $section?->semester?->academic_year ?? '',
                        $section?->teacher?->full_name ?? '',
                        $enrollment->status,
                        $enrollment->enrolled_at?->format('Y-m-d'),
                        $enrollment->dropped_at?->format('Y-m-d'),
                    ]);
                }
            });
        });
    }

    public function exportAttendance(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('attendance.view', 'attendance.update');
        $filters = $this->filters($request);

        return $this->csvResponse('attendance-export.csv', [
            'Date', 'Student ID', 'Student Name', 'Course Code', 'Course Name', 'Module', 'Stage', 'Semester', 'Academic Year', 'Status', 'Remarks',
        ], function ($output) use ($filters) {
            $query = Attendance::with(['student', 'course', 'courseSection.semester'])
                ->orderBy('date');
            $this->applyAttendanceFilters($query, $filters);

            $query->chunk(200, function ($attendances) use ($output) {
                foreach ($attendances as $attendance) {
                    fputcsv($output, [
                        $attendance->date?->format('Y-m-d'),
                        $attendance->student->student_id ?? '',
                        $attendance->student->full_name ?? '',
                        $attendance->course->code ?? '',
                        $attendance->course->name ?? '',
                        $attendance->courseSection->section_code ?? '',
                        $attendance->courseSection->grade_level ?? '',
                        $attendance->courseSection->semester->name ?? '',
                        $attendance->courseSection->semester->academic_year ?? '',
                        $attendance->status,
                        $attendance->remarks,
                    ]);
                }
            });
        });
    }

    public function exportTimetable(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('timetable.view', 'timetable.manage');
        $filters = $this->filters($request);

        return $this->csvResponse('timetable-export.csv', [
            'Day', 'Start Time', 'End Time', 'Course Code', 'Course Name', 'Module', 'Stage', 'Semester', 'Academic Year', 'Teacher', 'Room', 'Type', 'Status',
        ], function ($output) use ($filters) {
            $query = Timetable::with(['course', 'courseSection.semester', 'teacher'])
                ->orderBy('day_of_week')
                ->orderBy('start_time');
            $this->applyTimetableFilters($query, $filters);

            $query->chunk(200, function ($timetables) use ($output) {
                foreach ($timetables as $timetable) {
                    fputcsv($output, [
                        $timetable->day_of_week,
                        $timetable->start_time,
                        $timetable->end_time,
                        $timetable->course->code ?? '',
                        $timetable->course->name ?? '',
                        $timetable->courseSection->section_code ?? '',
                        $timetable->courseSection->grade_level ?? '',
                        $timetable->courseSection->semester->name ?? '',
                        $timetable->courseSection->semester->academic_year ?? '',
                        $timetable->teacher->full_name ?? '',
                        $timetable->room_number,
                        $timetable->type,
                        $timetable->status,
                    ]);
                }
            });
        });
    }

    public function exportFinance(Request $request): StreamedResponse
    {
        $this->requireAnyPermission('finance.view');
        $filters = $this->filters($request);

        return $this->csvResponse('finance-export.csv', [
            'Student ID', 'Student Name', 'Type', 'Amount', 'Currency', 'Balance After', 'Status', 'Payment Status', 'Invoice Number', 'Receipt Number', 'Reference', 'Academic Year', 'Transaction Date', 'Due Date', 'Notes',
        ], function ($output) use ($filters) {
            $query = FinanceTransaction::with(['student.department.college'])
                ->orderByDesc('transaction_date');
            $this->applyFinanceFilters($query, $filters);

            $query->chunk(200, function ($transactions) use ($output) {
                foreach ($transactions as $transaction) {
                    fputcsv($output, [
                        $transaction->student->student_id ?? '',
                        $transaction->student->full_name ?? '',
                        $transaction->type,
                        $transaction->amount,
                        $transaction->currency,
                        $transaction->balance_after,
                        $transaction->status,
                        $transaction->payment_status,
                        $transaction->invoice_number,
                        $transaction->receipt_number,
                        $transaction->reference,
                        $transaction->academic_year,
                        $transaction->transaction_date?->format('Y-m-d'),
                        $transaction->due_date?->format('Y-m-d'),
                        $transaction->notes,
                    ]);
                }
            });
        });
    }

    public function sampleStudents()
    {
        $this->authorizeDataExchange();

        return response($this->service->getSampleStudentsCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sample-students.csv"',
        ]);
    }

    public function sampleCourses()
    {
        $this->authorizeDataExchange();

        return response($this->service->getSampleCoursesCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sample-courses.csv"',
        ]);
    }

    public function sampleMarks()
    {
        $this->authorizeDataExchange();

        return response($this->service->getSampleMarksCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sample-marks.csv"',
        ]);
    }

    private function authorizeDataExchange(): void
    {
        $this->requireAnyPermission(
            'students.view',
            'students.create',
            'students.update',
            'courses.view',
            'courses.create',
            'courses.update',
            'teachers.view',
            'enrollments.view',
            'timetable.view',
            'attendance.view',
            'finance.view',
            'marks.view',
            'marks.enter',
            'marks.review',
            'marks.approve',
            'marks.publish'
        );
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

    private function filterOptions(): array
    {
        $collegeQuery = \App\Models\College::with('university')->orderBy('name');
        $departmentQuery = Department::with('college')->orderBy('name');
        $semesterQuery = Semester::with('university')->orderByDesc('academic_year')->orderBy('name');
        $stageQuery = CourseSection::query()
            ->whereNotNull('grade_level')
            ->distinct();
        $yearSemesterQuery = Semester::query()->whereNotNull('academic_year')->distinct();
        $financeYearQuery = FinanceTransaction::query()->whereNotNull('academic_year')->distinct();

        $this->applyCollegeOptionScope($collegeQuery);
        $this->applyDepartmentOptionScope($departmentQuery);
        $this->applySemesterOptionScope($semesterQuery);
        $this->applyCourseSectionOptionScope($stageQuery);
        $this->applySemesterOptionScope($yearSemesterQuery);
        $this->applyStudentRelationOrgScope($financeYearQuery, 'student.department');

        $colleges = $collegeQuery->get(['id', 'name', 'code', 'university_id']);
        $departments = $departmentQuery->get(['id', 'name', 'code', 'college_id', 'university_id']);
        $semesters = $semesterQuery->get(['id', 'name', 'academic_year', 'university_id']);
        $stages = $stageQuery
            ->orderBy('grade_level')
            ->pluck('grade_level')
            ->filter()
            ->values();
        $academicYears = $yearSemesterQuery->pluck('academic_year')
            ->merge($financeYearQuery->pluck('academic_year'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return compact('colleges', 'departments', 'semesters', 'stages', 'academicYears');
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

    private function applyTeacherFilters($query, array $filters): void
    {
        $this->applyTeacherOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->where('department_id', $filters['department_id']));
    }

    private function applyEnrollmentFilters($query, array $filters): void
    {
        $this->applySectionBackedOrgScope($query, 'courseSection.course.department');
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('courseSection.course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('courseSection.course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyAttendanceFilters($query, array $filters): void
    {
        $this->applyCourseRecordOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
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

    private function applyTimetableFilters($query, array $filters): void
    {
        $this->applyCourseRecordOrgScope($query);
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('course.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('course', fn ($course) => $course->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->whereHas('courseSection.semester', fn ($semester) => $semester->where('academic_year', $filters['academic_year'])));
    }

    private function applyFinanceFilters($query, array $filters): void
    {
        $this->applyStudentRelationOrgScope($query, 'student.department');
        $query
            ->when($filters['college_id'], fn ($builder) => $builder->whereHas('student.department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($builder) => $builder->whereHas('student', fn ($student) => $student->where('department_id', $filters['department_id'])))
            ->when($filters['stage'] !== '', fn ($builder) => $builder->whereHas('student.enrollments.courseSection', fn ($section) => $section->where('grade_level', $filters['stage'])))
            ->when($filters['semester_id'], fn ($builder) => $builder->whereHas('student.enrollments.courseSection', fn ($section) => $section->where('semester_id', $filters['semester_id'])))
            ->when($filters['academic_year'] !== '', fn ($builder) => $builder->where('academic_year', $filters['academic_year']));
    }

    private function applyStudentOrgScope($query): void
    {
        $user = auth()->user();
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
        $user = auth()->user();
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

    private function applyTeacherOrgScope($query): void
    {
        $user = auth()->user();
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
        $user = auth()->user();
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

    private function applySectionBackedOrgScope($query, string $departmentRelation): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->department_id) {
            $query->whereHas($departmentRelation, fn ($department) => $department->whereKey($user->department_id));
        } elseif ($user?->college_id) {
            $query->whereHas($departmentRelation, fn ($department) => $department->where('college_id', $user->college_id));
        } elseif ($user?->university_id) {
            $query->whereHas($departmentRelation, fn ($department) => $department->where('university_id', $user->university_id));
        }
    }

    private function applyStudentRelationOrgScope($query, string $departmentRelation): void
    {
        $this->applySectionBackedOrgScope($query, $departmentRelation);
    }

    private function applyCollegeOptionScope($query): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->college_id) {
            $query->whereKey($user->college_id);
        } elseif ($user?->department_id) {
            $query->whereHas('departments', fn ($department) => $department->whereKey($user->department_id));
        } elseif ($user?->university_id) {
            $query->where('university_id', $user->university_id);
        }
    }

    private function applyDepartmentOptionScope($query): void
    {
        $user = auth()->user();
        if ($user?->hasRole('super_administrator')) {
            return;
        }
        if ($user?->department_id) {
            $query->whereKey($user->department_id);
        } elseif ($user?->college_id) {
            $query->where('college_id', $user->college_id);
        } elseif ($user?->university_id) {
            $query->where('university_id', $user->university_id);
        }
    }

    private function applySemesterOptionScope($query): void
    {
        $user = auth()->user();
        if (! $user?->hasRole('super_administrator') && $user?->university_id) {
            $query->where('university_id', $user->university_id);
        }
    }

    private function applyCourseSectionOptionScope($query): void
    {
        $this->applySectionBackedOrgScope($query, 'course.department');
    }

    private function csvResponse(string $fileName, array $headers, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($headers, $writer) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            $writer($output);
            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
