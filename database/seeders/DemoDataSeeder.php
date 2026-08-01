<?php

namespace Database\Seeders;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $universityId = 1;
        $departmentId = 1;
        $section = CourseSection::with('course')->firstOrFail();
        $studentRole = Role::where('name', 'student')->first();

        $students = [
            ['student_id' => 'STU002', 'full_name' => 'Layan Omar', 'email' => 'layan@student.edu'],
            ['student_id' => 'STU003', 'full_name' => 'Youssef Khaled', 'email' => 'youssef@student.edu'],
            ['student_id' => 'STU004', 'full_name' => 'Noor Fatima', 'email' => 'noor@student.edu'],
            ['student_id' => 'STU005', 'full_name' => 'Omar Hussein', 'email' => 'omar@student.edu'],
            ['student_id' => 'STU006', 'full_name' => 'Sara Mahmoud', 'email' => 'sara@student.edu'],
        ];

        $this->seedMarkForExistingStudent($section);

        foreach ($students as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'password' => Hash::make('Password123'),
                    'email_verified_at' => Carbon::now(),
                    'university_id' => $universityId,
                    'department_id' => $departmentId,
                ]
            );

            if ($studentRole && ! $user->hasRole('student')) {
                $user->roles()->attach($studentRole->id);
            }

            $student = Student::updateOrCreate(
                ['student_id' => $data['student_id']],
                [
                    'university_id' => $universityId,
                    'department_id' => $departmentId,
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'status' => 'Active',
                    'admission_status' => 'Admitted',
                    'admission_date' => '2026-07-31',
                ]
            );

            $enrollment = Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'course_section_id' => $section->id],
                [
                    'status' => 'enrolled',
                    'enrolled_at' => '2026-08-01',
                    'notes' => 'Demo enrollment',
                ]
            );

            $prefinal = $this->prefinalMark();
            $finalExam = $this->finalExamMark($prefinal);

            Mark::updateOrCreate(
                ['student_id' => $student->id, 'course_section_id' => $section->id],
                [
                    'course_id' => $section->course_id,
                    'prefinal_mark' => $prefinal,
                    'first_trial_final_exam' => $finalExam,
                    'final_exam' => $finalExam,
                    'final_mark' => min(100, $prefinal + $finalExam),
                    'status' => 'published',
                    'submission_status' => 'approved',
                    'visibility_status' => 'published',
                    'submitted_at' => Carbon::now(),
                    'reviewed_at' => Carbon::now(),
                    'published_at' => Carbon::now(),
                ]
            );
        }
    }

    private function prefinalMark(): float
    {
        return round(mt_rand(250, 500) / 10, 1);
    }

    private function finalExamMark(float $prefinal): float
    {
        $target = mt_rand(45, 95);
        $needed = max(0, $target - $prefinal);
        $min = (int) ($needed * 10);
        $max = 500;

        if ($min > $max) {
            $min = $max;
        }

        return round(mt_rand($min, $max) / 10, 1);
    }

    private function seedMarkForExistingStudent(CourseSection $section): void
    {
        $student = Student::where('student_id', '1')->first();

        if (! $student) {
            return;
        }

        Enrollment::firstOrCreate(
            ['student_id' => $student->id, 'course_section_id' => $section->id],
            [
                'status' => 'enrolled',
                'enrolled_at' => '2026-08-01',
                'notes' => 'Demo enrollment',
            ]
        );

        $prefinal = $this->prefinalMark();
        $finalExam = $this->finalExamMark($prefinal);

        Mark::updateOrCreate(
            ['student_id' => $student->id, 'course_section_id' => $section->id],
            [
                'course_id' => $section->course_id,
                'prefinal_mark' => $prefinal,
                'first_trial_final_exam' => $finalExam,
                'final_exam' => $finalExam,
                'final_mark' => min(100, $prefinal + $finalExam),
                'status' => 'published',
                'submission_status' => 'approved',
                'visibility_status' => 'published',
                'submitted_at' => Carbon::now(),
                'reviewed_at' => Carbon::now(),
                'published_at' => Carbon::now(),
            ]
        );
    }
}
