<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\TimetableTimeSlot;
use App\Models\TuitionAgreement;
use App\Models\University;
use App\Models\User;
use App\Services\FinanceLedgerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RichDemoSeeder extends Seeder
{
    private array $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];

    private array $timeSlots = [];

    public function run(): void
    {
        DB::transaction(function () {
            $this->timeSlots = $this->makeTimeSlots();

            $university = $this->makeInstitution('Al-Rasheed University', 'ARU', University::TYPE_UNIVERSITY);
            $institute = $this->makeInstitution('Baghdad Technical Institute', 'BTI', University::TYPE_INSTITUTE);

            $this->makeStaffAccounts($university);

            $superAdmin = User::where('email', 'admin@example.com')->first();
            if ($superAdmin && ! $superAdmin->hasRole('super_administrator')) {
                $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_administrator'], ['display_name' => 'Super Administrator'])->id);
            }
        });
    }

    private function makeTimeSlots(): array
    {
        $slots = [
            ['label' => '08:00 - 09:30', 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'sort_order' => 1],
            ['label' => '09:45 - 11:15', 'start_time' => '09:45:00', 'end_time' => '11:15:00', 'sort_order' => 2],
            ['label' => '11:30 - 13:00', 'start_time' => '11:30:00', 'end_time' => '13:00:00', 'sort_order' => 3],
            ['label' => '13:15 - 14:45', 'start_time' => '13:15:00', 'end_time' => '14:45:00', 'sort_order' => 4],
        ];

        return collect($slots)->map(fn (array $slot) => TimetableTimeSlot::firstOrCreate(
            ['start_time' => $slot['start_time'], 'end_time' => $slot['end_time']],
            $slot
        ))->all();
    }

    private function makeInstitution(string $name, string $code, string $type): array
    {
        $university = University::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'institution_type' => $type, 'email' => strtolower($code).'@example.edu', 'phone' => '+964-770-000-0000']
        );

        $college = College::firstOrCreate(
            ['university_id' => $university->id, 'name' => $name.' College'],
            ['code' => $code.'-C']
        );

        $classrooms = collect(['Room 101', 'Room 102', 'Lab 201'])->map(
            fn (string $roomName) => Classroom::firstOrCreate(
                ['university_id' => $university->id, 'name' => $roomName],
                ['building' => 'Main Building', 'capacity' => 35, 'type' => str_contains($roomName, 'Lab') ? 'lab' : 'classroom', 'status' => 'available']
            )
        );

        $academicYear = AcademicYear::firstOrCreate(
            ['university_id' => $university->id, 'name' => '2026/2027'],
            ['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'status' => 'active']
        );

        $semesterCount = $university->expectedSemesterCount();
        $semesters = [];
        $semesterRanges = [
            ['name' => 'Semester 1', 'start' => '2026-09-01', 'end' => '2026-12-31'],
            ['name' => 'Semester 2', 'start' => '2027-02-01', 'end' => '2027-06-30'],
        ];
        for ($i = 0; $i < $semesterCount; $i++) {
            $range = $semesterRanges[$i] ?? $semesterRanges[0];
            $semesters[] = Semester::firstOrCreate(
                ['university_id' => $university->id, 'academic_year_id' => $academicYear->id, 'name' => $range['name']],
                [
                    'academic_year' => $academicYear->name,
                    'term_type' => 'regular',
                    'sequence' => $i + 1,
                    'start_date' => $range['start'],
                    'end_date' => $range['end'],
                ]
            );
        }

        $departmentNames = $type === University::TYPE_INSTITUTE
            ? ['Information Technology', 'Business Administration']
            : ['Computer Science', 'Electrical Engineering'];

        $stageNames = ['First Stage', 'Second Stage', 'Third Stage', 'Fourth Stage'];
        $stageCount = $university->expectedStageCount();

        $departments = [];
        $dayCursor = 0;
        $timeSlotCursor = 0;
        $studentSeq = 0;

        foreach ($departmentNames as $index => $departmentName) {
            $deptCode = $code.'-D'.($index + 1);
            $department = Department::firstOrCreate(
                ['university_id' => $university->id, 'code' => $deptCode],
                ['name' => $departmentName, 'college_id' => $college->id]
            );

            $stages = [];
            for ($s = 0; $s < $stageCount; $s++) {
                $stages[] = Stage::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $stageNames[$s]],
                    ['university_id' => $university->id, 'sequence' => $s + 1]
                );
            }
            $firstStage = $stages[0];

            $teachers = collect(range(1, 2))->map(function (int $n) use ($university, $department, $deptCode) {
                $teacherUser = User::firstOrCreate(
                    ['email' => strtolower($deptCode).'.teacher'.$n.'@example.edu'],
                    ['name' => "{$deptCode} Teacher {$n}", 'password' => 'Password123', 'email_verified_at' => now(), 'university_id' => $university->id, 'department_id' => $department->id]
                );
                $this->attachRole($teacherUser, 'teacher');

                $teacher = Teacher::firstOrCreate(
                    ['email' => $teacherUser->email],
                    [
                        'university_id' => $university->id,
                        'department_id' => $department->id,
                        'user_id' => $teacherUser->id,
                        'full_name' => $teacherUser->name,
                        'title' => 'Lecturer',
                        'status' => 'Active',
                    ]
                );

                if (! $teacher->user_id) {
                    $teacher->update(['user_id' => $teacherUser->id]);
                }

                return $teacher;
            });

            $courses = collect(range(1, 3))->map(function (int $n) use ($university, $department, $deptCode, $departmentName) {
                return Course::firstOrCreate(
                    ['university_id' => $university->id, 'code' => "{$deptCode}10{$n}"],
                    [
                        'department_id' => $department->id,
                        'name' => "{$departmentName} Course {$n}",
                        'credits' => 3,
                        'status' => 'active',
                    ]
                );
            });

            $sections = $courses->values()->map(function (Course $course, int $courseIndex) use (
                $semesters, $firstStage, $teachers, $classrooms, &$dayCursor, &$timeSlotCursor
            ) {
                $section = CourseSection::firstOrCreate(
                    ['course_id' => $course->id, 'semester_id' => $semesters[0]->id, 'section_code' => 'A'],
                    [
                        'stage_id' => $firstStage->id,
                        'teacher_id' => $teachers[$courseIndex % $teachers->count()]->id,
                        'capacity' => 30,
                        'status' => 'active',
                    ]
                );

                $slot = $this->timeSlots[$timeSlotCursor % count($this->timeSlots)];
                $day = $this->days[$dayCursor % count($this->days)];
                $dayCursor++;
                $timeSlotCursor++;

                Timetable::firstOrCreate(
                    ['course_section_id' => $section->id, 'day_of_week' => $day],
                    [
                        'course_id' => $course->id,
                        'teacher_id' => $section->teacher_id,
                        'classroom_id' => $classrooms[$courseIndex % $classrooms->count()]->id,
                        'time_slot_id' => $slot->id,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                        'type' => 'lecture',
                        'status' => 'scheduled',
                    ]
                );

                return $section;
            });

            $students = collect(range(1, 8))->map(function (int $n) use (
                $university, $department, $firstStage, $sections, $semesters, &$studentSeq
            ) {
                $studentSeq++;
                $studentUser = User::firstOrCreate(
                    ['email' => "student{$university->code}{$studentSeq}@example.edu"],
                    ['name' => "Student {$university->code}-{$studentSeq}", 'password' => 'Password123', 'email_verified_at' => now(), 'university_id' => $university->id, 'department_id' => $department->id]
                );
                $this->attachRole($studentUser, 'student');

                $student = Student::firstOrCreate(
                    ['email' => $studentUser->email],
                    [
                        'university_id' => $university->id,
                        'department_id' => $department->id,
                        'current_stage_id' => $firstStage->id,
                        'user_id' => $studentUser->id,
                        'full_name' => "Student {$university->code}-{$studentSeq}",
                        'phone' => '+964-770-100-'.str_pad((string) $studentSeq, 4, '0', STR_PAD_LEFT),
                        'status' => 'Active',
                        'admission_status' => 'Admitted',
                        'admission_date' => '2026-08-15',
                    ]
                );

                if (! $student->user_id) {
                    $student->update(['user_id' => $studentUser->id]);
                }

                $this->enrollAndGradeStudent($student, $sections, $studentSeq);
                $this->billStudent($university, $student, $semesters, $studentSeq);

                return $student;
            });

            $departments[] = compact('department', 'stages', 'teachers', 'courses', 'sections', 'students');
        }

        return [
            'university' => $university,
            'college' => $college,
            'academicYear' => $academicYear,
            'semesters' => $semesters,
            'departments' => $departments,
        ];
    }

    private function enrollAndGradeStudent(Student $student, $sections, int $studentSeq): void
    {
        $markStates = ['draft', 'submitted', 'under_review', 'approved_draft', 'published', 'rejected'];

        $sections->values()->each(function (CourseSection $section, int $sectionIndex) use ($student, $studentSeq, $markStates) {
            $enrollment = Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'course_section_id' => $section->id],
                ['status' => 'enrolled', 'enrolled_at' => '2026-09-01', 'notes' => 'Demo enrollment']
            );

            $state = $markStates[($studentSeq + $sectionIndex) % count($markStates)];
            $prefinal = $state === 'draft' ? null : round(mt_rand(300, 450) / 10, 1);
            $finalExam = in_array($state, ['approved_draft', 'published'], true) ? round(mt_rand(300, 450) / 10, 1) : null;

            Mark::firstOrCreate(
                ['student_id' => $student->id, 'course_section_id' => $section->id],
                [
                    'course_id' => $section->course_id,
                    'prefinal_mark' => $prefinal,
                    'first_trial_final_exam' => $finalExam,
                    'final_mark' => $finalExam !== null ? min(100, $prefinal + $finalExam) : 0,
                    'submission_status' => match ($state) {
                        'approved_draft', 'published' => 'approved',
                        'draft' => 'draft',
                        default => $state,
                    },
                    'visibility_status' => $state === 'published' ? 'published' : 'draft',
                    'submitted_at' => $state === 'draft' ? null : now(),
                    'published_at' => $state === 'published' ? now() : null,
                ]
            );
        });
    }

    private function billStudent(University $university, Student $student, array $semesters, int $studentSeq): void
    {
        $ledger = app(FinanceLedgerService::class);
        $pattern = $studentSeq % 3;
        $agentUser = User::where('email', 'admin@example.com')->first() ?? User::first();

        if ($pattern === 0) {
            $amount = 3000000;
            $payload = ['type' => 'invoice', 'transaction_date' => '2026-08-20'];
            $invoice = FinanceTransaction::create([
                'student_id' => $student->id,
                'recorded_by' => $agentUser->id,
                'approved_by' => $agentUser->id,
                'approved_at' => now(),
                'type' => 'invoice',
                'amount' => $amount,
                'currency' => 'IQD',
                'status' => 'approved',
                'posting_status' => 'posted',
                'invoice_number' => $ledger->documentNumber($payload, 'invoice_number'),
                'reference' => 'Full year tuition',
                'academic_year' => $semesters[0]->academic_year,
                'transaction_date' => '2026-08-20',
                'due_date' => '2026-09-15',
                'balance_after' => $amount,
                'payment_status' => 'paid',
            ]);
            $paymentPayload = ['type' => 'payment', 'transaction_date' => '2026-08-20'];
            FinanceTransaction::create([
                'student_id' => $student->id,
                'invoice_transaction_id' => $invoice->id,
                'recorded_by' => $agentUser->id,
                'approved_by' => $agentUser->id,
                'approved_at' => now(),
                'type' => 'payment',
                'amount' => $amount,
                'currency' => 'IQD',
                'status' => 'approved',
                'posting_status' => 'posted',
                'receipt_number' => $ledger->documentNumber($paymentPayload, 'receipt_number'),
                'reference' => 'Full tuition payment',
                'academic_year' => $semesters[0]->academic_year,
                'transaction_date' => '2026-08-20',
                'balance_after' => 0,
                'payment_status' => 'paid',
            ]);

            return;
        }

        if ($pattern === 1) {
            $total = 2000000;
            $perSemester = round($total / count($semesters), 2);
            $agreement = TuitionAgreement::create([
                'student_id' => $student->id,
                'academic_year_id' => $semesters[0]->academic_year_id,
                'created_by' => $agentUser->id,
                'payment_method' => 'semester',
                'total_amount' => $total,
                'installment_count' => count($semesters),
                'installments_generated' => count($semesters),
                'installment_amount' => $perSemester,
                'currency' => 'IQD',
                'status' => 'active',
                'agreed_at' => '2026-08-20',
            ]);

            foreach ($semesters as $index => $semester) {
                $isOverdue = $index === 0;
                $payload = ['type' => 'invoice', 'transaction_date' => '2026-08-20'];
                FinanceTransaction::create([
                    'student_id' => $student->id,
                    'tuition_agreement_id' => $agreement->id,
                    'semester_id' => $semester->id,
                    'recorded_by' => $agentUser->id,
                    'approved_by' => $agentUser->id,
                    'approved_at' => now(),
                    'type' => 'invoice',
                    'amount' => $perSemester,
                    'currency' => 'IQD',
                    'status' => 'approved',
                    'posting_status' => 'posted',
                    'invoice_number' => $ledger->documentNumber($payload, 'invoice_number'),
                    'reference' => 'Tuition installment - '.$semester->name,
                    'academic_year' => $semester->academic_year,
                    'transaction_date' => '2026-08-20',
                    'due_date' => $isOverdue ? '2026-07-15' : $semester->end_date,
                    'balance_after' => $perSemester,
                    'payment_status' => $isOverdue ? 'overdue' : 'open',
                ]);
            }

            return;
        }

        $installmentCount = $university->expectedProgramSemesterCount();
        $total = 8000000;
        $perInstallment = round($total / $installmentCount, 2);

        $agreement = TuitionAgreement::create([
            'student_id' => $student->id,
            'academic_year_id' => $semesters[0]->academic_year_id,
            'created_by' => $agentUser->id,
            'payment_method' => 'semester',
            'total_amount' => $total,
            'installment_count' => $installmentCount,
            'installments_generated' => 1,
            'installment_amount' => $perInstallment,
            'currency' => 'IQD',
            'status' => 'active',
            'agreed_at' => '2026-08-20',
        ]);

        $payload = ['type' => 'invoice', 'transaction_date' => '2026-08-20'];
        FinanceTransaction::create([
            'student_id' => $student->id,
            'tuition_agreement_id' => $agreement->id,
            'semester_id' => $semesters[0]->id,
            'recorded_by' => $agentUser->id,
            'approved_by' => $agentUser->id,
            'approved_at' => now(),
            'type' => 'invoice',
            'amount' => $perInstallment,
            'currency' => 'IQD',
            'status' => 'approved',
            'posting_status' => 'posted',
            'invoice_number' => $ledger->documentNumber($payload, 'invoice_number'),
            'reference' => "Tuition installment - {$semesters[0]->name} (1 of {$installmentCount})",
            'academic_year' => $semesters[0]->academic_year,
            'transaction_date' => '2026-08-20',
            'due_date' => $semesters[0]->end_date,
            'balance_after' => $perInstallment,
            'payment_status' => 'open',
        ]);
    }

    private function makeStaffAccounts(array $university): void
    {
        /** @var University $uni */
        $uni = $university['university'];
        $college = $university['college'];
        $department = $university['departments'][0]['department'];

        $staff = [
            ['role' => 'university_administrator', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'college_administrator', 'scope' => ['university_id' => $uni->id, 'college_id' => $college->id]],
            ['role' => 'department_administrator', 'scope' => ['university_id' => $uni->id, 'college_id' => $college->id, 'department_id' => $department->id]],
            ['role' => 'examination_administrator', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'examination_committee', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'registrar', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'accountant', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'chief_accountant', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'hr_manager', 'scope' => ['university_id' => $uni->id]],
            ['role' => 'lms_administrator', 'scope' => ['university_id' => $uni->id]],
        ];

        foreach ($staff as $entry) {
            $email = str_replace('_', '.', $entry['role']).'@example.edu';
            $user = User::firstOrCreate(
                ['email' => $email],
                array_merge(['name' => ucwords(str_replace('_', ' ', $entry['role'])), 'password' => 'Password123', 'email_verified_at' => now()], $entry['scope'])
            );
            $this->attachRole($user, $entry['role']);
        }
    }

    private function attachRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucwords(str_replace('_', ' ', $roleName))]);

        if (! $user->hasRole($roleName)) {
            $user->roles()->attach($role->id);
        }
    }
}
