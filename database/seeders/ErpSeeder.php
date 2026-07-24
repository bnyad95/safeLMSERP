<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Course;
use App\Models\Department;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use Illuminate\Database\Seeder;

class ErpSeeder extends Seeder
{
    public function run(): void
    {
        $university = University::firstOrCreate(
            ['code' => 'UOS'],
            ['name' => 'University of Science', 'email' => 'info@uos.edu', 'phone' => '+1-555-0100']
        );

        $department = Department::firstOrCreate(
            ['university_id' => $university->id, 'code' => 'CS'],
            ['name' => 'Computer Science']
        );

        $college = College::firstOrCreate(
            ['university_id' => $university->id, 'code' => 'ENG'],
            ['name' => 'College of Engineering']
        );

        if (! $department->college_id) {
            $department->update(['college_id' => $college->id]);
        }

        $semester = Semester::firstOrCreate(
            ['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027'],
            ['start_date' => '2026-09-01', 'end_date' => '2026-12-31']
        );

        $student = Student::firstOrCreate(
            ['student_id' => 'STU001'],
            [
                'university_id' => $university->id,
                'department_id' => $department->id,
                'full_name' => 'Amina Hassan',
                'email' => 'amina@student.edu',
                'phone' => '+1-555-0101',
                'status' => 'Active',
            ]
        );

        $teacher = Teacher::firstOrCreate(
            ['staff_id' => 'TCH001'],
            [
                'university_id' => $university->id,
                'department_id' => $department->id,
                'full_name' => 'Dr. Daniel Brooks',
                'email' => 'daniel@uos.edu',
                'title' => 'Senior Lecturer',
                'status' => 'Active',
            ]
        );

        $course = Course::firstOrCreate(
            ['university_id' => $university->id, 'code' => 'CS101'],
            [
                'department_id' => $department->id,
                'name' => 'Introduction to Programming',
                'credits' => 3,
                'status' => 'active',
            ]
        );

        Mark::firstOrCreate(
            ['student_id' => $student->id, 'course_id' => $course->id],
            [
                'assignments' => 88,
                'quizzes' => 92,
                'midterm' => 85,
                'practical' => 90,
                'final_exam' => 87,
                'final_mark' => 88.4,
                'status' => 'Published',
            ]
        );
    }
}
