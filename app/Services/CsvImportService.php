<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Department;
use App\Models\ImportLog;
use App\Models\Mark;
use App\Models\Student;
use App\Models\University;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CsvImportService
{
    public function importStudents(string $filePath, string $filename): ImportLog
    {
        $rows = $this->parseCsv($filePath);
        $successful = 0;
        $failed = 0;
        $errors = [];

        $defaultUniversity = University::first();

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2;
                try {
                    $studentId = trim($row['student_id'] ?? '');
                    $fullName = trim($row['full_name'] ?? $row['name'] ?? '');
                    $email = trim($row['email'] ?? '');
                    $departmentLookup = trim($row['department_code'] ?? $row['department'] ?? '');

                    if ($studentId === '' || $fullName === '' || $email === '') {
                        throw ValidationException::withMessages(['csv' => 'student_id, full_name, and email are required.']);
                    }

                    $dept = Department::where('name', $departmentLookup)
                        ->orWhere('code', $departmentLookup)
                        ->first();

                    if (! $dept) {
                        throw ValidationException::withMessages(['csv' => "Department not found: {$departmentLookup}"]);
                    }
                    $this->ensureDepartmentAllowed($dept);

                    Student::updateOrCreate(
                        ['email' => $email],
                        [
                            'university_id' => $dept->university_id ?: $defaultUniversity?->id,
                            'department_id' => $dept->id,
                            'student_id' => $studentId,
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => trim($row['phone'] ?? ''),
                            'status' => trim($row['status'] ?? 'Active'),
                            'admission_status' => trim($row['admission_status'] ?? 'Admitted'),
                            'admission_date' => $this->nullableValue($row['admission_date'] ?? null),
                            'admission_type' => $this->nullableValue($row['admission_type'] ?? null),
                            'previous_school' => $this->nullableValue($row['previous_school'] ?? null),
                            'emergency_contact_name' => $this->nullableValue($row['emergency_contact_name'] ?? null),
                            'emergency_contact_relationship' => $this->nullableValue($row['emergency_contact_relationship'] ?? null),
                            'emergency_contact_phone' => $this->nullableValue($row['emergency_contact_phone'] ?? null),
                        ]
                    );
                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: ".$this->messageFor($e);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ImportLog::create([
            'type' => 'students',
            'filename' => $filename,
            'total_rows' => count($rows),
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors,
            'imported_by' => Auth::id(),
        ]);
    }

    public function importCourses(string $filePath, string $filename): ImportLog
    {
        $rows = $this->parseCsv($filePath);
        $successful = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2;
                try {
                    $code = trim($row['code'] ?? $row['course_code'] ?? '');
                    $name = trim($row['name'] ?? $row['course_name'] ?? '');
                    $departmentLookup = trim($row['department_code'] ?? $row['department'] ?? '');

                    if ($code === '' || $name === '' || $departmentLookup === '') {
                        throw ValidationException::withMessages(['csv' => 'code, name, and department are required.']);
                    }

                    $department = Department::where('name', $departmentLookup)
                        ->orWhere('code', $departmentLookup)
                        ->first();

                    if (! $department) {
                        throw ValidationException::withMessages(['csv' => "Department not found: {$departmentLookup}"]);
                    }
                    $this->ensureDepartmentAllowed($department);

                    $existingCourse = Course::where('university_id', $department->university_id)
                        ->where('code', $code)
                        ->first();
                    $user = Auth::user();

                    if ($existingCourse && ! ($user->hasRole('super_administrator') || $user->hasPermission('courses.update'))) {
                        throw ValidationException::withMessages(['csv' => "Update permission is required for course {$code}."]);
                    }
                    if (! $existingCourse && ! ($user->hasRole('super_administrator') || $user->hasPermission('courses.create'))) {
                        throw ValidationException::withMessages(['csv' => "Create permission is required for course {$code}."]);
                    }

                    Course::updateOrCreate(
                        ['university_id' => $department->university_id, 'code' => $code],
                        [
                            'department_id' => $department->id,
                            'name' => $name,
                            'credits' => (int) ($row['credits'] ?? 3),
                            'status' => in_array(strtolower(trim($row['status'] ?? 'active')), ['active', 'inactive'], true)
                                ? strtolower(trim($row['status'] ?? 'active'))
                                : 'active',
                        ]
                    );

                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: ".$this->messageFor($e);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ImportLog::create([
            'type' => 'courses',
            'filename' => $filename,
            'total_rows' => count($rows),
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors,
            'imported_by' => Auth::id(),
        ]);
    }

    public function importMarks(string $filePath, string $filename): ImportLog
    {
        $rows = $this->parseCsv($filePath);
        $successful = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2;
                try {
                    $studentEmail = trim($row['student_email'] ?? '');
                    $studentId = trim($row['student_id'] ?? '');

                    if ($studentEmail === '' && $studentId === '') {
                        throw ValidationException::withMessages(['csv' => 'student_email or student_id is required.']);
                    }

                    $student = Student::where(function ($query) use ($studentEmail, $studentId) {
                        $query->when($studentEmail !== '', fn ($q) => $q->orWhere('email', $studentEmail))
                            ->when($studentId !== '', fn ($q) => $q->orWhere('student_id', $studentId));
                    })
                        ->first();

                    if (! $student) {
                        throw new \Exception('Student not found: '.($row['student_email'] ?? $row['student_id'] ?? 'N/A'));
                    }
                    $this->ensureDepartmentAllowed($student->department);

                    $course = Course::where('university_id', $student->university_id)
                        ->where('code', trim($row['course_code'] ?? ''))
                        ->first();
                    if (! $course) {
                        throw new \Exception('Course not found: '.($row['course_code'] ?? 'N/A'));
                    }
                    $this->ensureDepartmentAllowed($course->department);

                    $sectionCode = trim($row['section_code'] ?? '');
                    $sectionIds = $student->enrollments()
                        ->where('status', 'enrolled')
                        ->whereHas('courseSection', fn ($query) => $query
                            ->where('course_id', $course->id)
                            ->when($sectionCode !== '', fn ($query) => $query->where('section_code', $sectionCode)))
                        ->pluck('course_section_id');

                    if ($sectionIds->isEmpty()) {
                        throw new \Exception('Student is not enrolled in the requested course section.');
                    }
                    if ($sectionIds->count() > 1) {
                        throw new \Exception('section_code is required when a student has multiple sections for this course.');
                    }

                    $existingMark = Mark::where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('course_section_id', $sectionIds->first())
                        ->first();

                    if ($existingMark && ! in_array($existingMark->submission_status, ['draft', 'rejected'], true)) {
                        throw ValidationException::withMessages([
                            'csv' => 'This mark is already submitted, under review, approved, or published and cannot be overwritten by CSV import. Use the mark review or correction workflow instead.',
                        ]);
                    }

                    Mark::updateOrCreate(
                        ['student_id' => $student->id, 'course_id' => $course->id, 'course_section_id' => $sectionIds->first()],
                        [
                            'assignments' => floatval($row['assignments'] ?? 0),
                            'quizzes' => floatval($row['quizzes'] ?? 0),
                            'midterm' => floatval($row['midterm'] ?? 0),
                            'practical' => floatval($row['practical'] ?? 0),
                            'final_exam' => floatval($row['final_exam'] ?? 0),
                            'final_mark' => floatval($row['final_mark'] ?? 0),
                            'status' => 'Draft',
                        ]
                    );
                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: ".$this->messageFor($e);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ImportLog::create([
            'type' => 'marks',
            'filename' => $filename,
            'total_rows' => count($rows),
            'successful' => $successful,
            'failed' => $failed,
            'errors' => $errors,
            'imported_by' => Auth::id(),
        ]);
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);

        if ($headers === false) {
            return [];
        }

        $headers = array_map(fn ($header) => trim(strtolower((string) $header)), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) === count($headers)) {
                $rows[] = array_combine($headers, array_map('trim', $data));
            }
        }

        fclose($handle);

        return $rows;
    }

    public function getSampleStudentsCsv(): string
    {
        return "student_id,full_name,email,phone,department,status,admission_status,admission_date,admission_type,previous_school,emergency_contact_name,emergency_contact_relationship,emergency_contact_phone\n".
               "STU001,John Doe,john@example.com,+1234567890,Computer Science,Active,Admitted,2026-07-10,Regular,Central High,Jane Doe,Mother,+1234567891\n".
               'STU002,Jane Smith,jane@example.com,+0987654321,Mathematics,Active,Admitted,2026-07-10,Transfer,North High,Sam Smith,Father,+0987654322';
    }

    public function getSampleCoursesCsv(): string
    {
        return "code,name,department,credits,status\n".
               "CS101,Introduction to Programming,Computer Science,3,active\n".
               'MATH101,Calculus I,Mathematics,4,active';
    }

    public function getSampleMarksCsv(): string
    {
        return "student_email,student_id,course_code,section_code,assignments,quizzes,midterm,practical,final_exam,final_mark\n".
               "john@example.com,STU001,CS101,A,8,7,15,9,40,79\n".
               'jane@example.com,STU002,CS101,A,9,8,18,10,45,90';
    }

    private function nullableValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function messageFor(\Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        return $exception->getMessage();
    }

    private function ensureDepartmentAllowed(?Department $department): void
    {
        $user = Auth::user();
        if (! $user || $user->hasRole('super_administrator') || ! $department) {
            return;
        }

        if ($user->department_id && (int) $department->id !== (int) $user->department_id) {
            throw ValidationException::withMessages(['csv' => "Department is outside your access scope: {$department->name}"]);
        }

        if (! $user->department_id && $user->college_id && (int) $department->college_id !== (int) $user->college_id) {
            throw ValidationException::withMessages(['csv' => "Department is outside your access scope: {$department->name}"]);
        }

        if (! $user->department_id && ! $user->college_id && $user->university_id && (int) $department->university_id !== (int) $user->university_id) {
            throw ValidationException::withMessages(['csv' => "Department is outside your access scope: {$department->name}"]);
        }
    }
}
