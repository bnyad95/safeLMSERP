<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_dashboard_can_filter_by_section_group(): void
    {
        $role = Role::create([
            'name' => 'teacher',
            'display_name' => 'Teacher',
            'description' => 'Teacher role',
        ]);
        $user = User::factory()->create(['email' => 'dashboard.teacher@example.com']);
        $user->roles()->attach($role->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering College', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'TD-1',
            'full_name' => 'Dashboard Teacher',
            'email' => $user->email,
            'status' => 'Active',
        ]);

        $courseA = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'DASH101',
            'name' => 'Dashboard Course A',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $courseB = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'DASH102',
            'name' => 'Dashboard Course B',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $sectionA = CourseSection::create([
            'course_id' => $courseA->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 1',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $sectionB = CourseSection::create([
            'course_id' => $courseB->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 2',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $courseC = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'DASH103',
            'name' => 'Dashboard Course C',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $sectionC = CourseSection::create([
            'course_id' => $courseC->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'C',
            'grade_level' => 'First Stage',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'TD-STU-1',
            'full_name' => 'Dashboard Student',
            'email' => 'dashboard.student@example.com',
            'status' => 'Active',
        ]);
        $otherStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'TD-STU-2',
            'full_name' => 'Other Class Student',
            'email' => 'other.dashboard.student@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $sectionA->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'course_section_id' => $sectionB->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        Mark::create([
            'student_id' => $otherStudent->id,
            'course_id' => $courseB->id,
            'final_mark' => 88,
            'status' => 'Draft',
        ]);
        Mark::create([
            'student_id' => $student->id,
            'course_id' => $courseA->id,
            'course_section_id' => $sectionA->id,
            'prefinal_mark' => 75,
            'final_mark' => 82,
            'status' => 'Published',
            'visibility_status' => 'published',
        ]);
        Attendance::create(['course_id' => $courseA->id, 'course_section_id' => $sectionA->id, 'student_id' => $student->id, 'date' => '2026-07-01', 'status' => 'present']);
        Attendance::create(['course_id' => $courseA->id, 'course_section_id' => $sectionA->id, 'student_id' => $student->id, 'date' => '2026-07-02', 'status' => 'absent']);
        Attendance::create(['course_id' => $courseB->id, 'course_section_id' => $sectionB->id, 'student_id' => $otherStudent->id, 'date' => '2026-07-01', 'status' => 'absent']);
        $classRoom = Classroom::create(['name' => 'Class A Room', 'capacity' => 40]);
        $otherClassRoom = Classroom::create(['name' => 'Class B Room', 'capacity' => 40]);
        Timetable::create(['course_id' => $courseA->id, 'course_section_id' => $sectionA->id, 'teacher_id' => $teacher->id, 'classroom_id' => $classRoom->id, 'day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '10:00', 'room_number' => $classRoom->name, 'type' => 'lecture', 'status' => 'scheduled']);
        Timetable::create(['course_id' => $courseB->id, 'course_section_id' => $sectionB->id, 'teacher_id' => $teacher->id, 'classroom_id' => $otherClassRoom->id, 'day_of_week' => 'Tuesday', 'start_time' => '11:00', 'end_time' => '12:00', 'room_number' => $otherClassRoom->name, 'type' => 'lab', 'status' => 'scheduled']);
        $classAssessment = AssessmentItem::create([
            'course_section_id' => $sectionA->id,
            'title' => 'Class Card Assessment',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        AssessmentItem::create([
            'course_section_id' => $sectionB->id,
            'title' => 'Other Class Assessment',
            'type' => 'quiz',
            'max_score' => 20,
            'weight_percent' => 5,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($user)
            ->get(route('teacher-dashboard'))
            ->assertOk()
            ->assertSee(route('teacher-dashboard', ['section_id' => $sectionA->id]), false)
            ->assertSee('My Classes')
            ->assertSee('Dashboard Course A')
            ->assertSee('Dashboard Course B')
            ->assertDontSee(route('attendance'), false)
            ->assertDontSee(route('assessments.index'), false)
            ->assertDontSee(route('integrations.index'), false)
            ->assertDontSee(route('analytics.index'), false)
            ->assertDontSee(route('exams'), false)
            ->assertDontSee('Class Dashboard')
            ->assertDontSee('Dashboard Student')
            ->assertDontSee('Class Card Assessment');

        $this->actingAs($user)
            ->get(route('teacher-dashboard', ['section_id' => $sectionA->id]))
            ->assertOk()
            ->assertSee('Class Dashboard')
            ->assertSee('Stream')
            ->assertSee('Classwork')
            ->assertSee('People')
            ->assertSee('Grades')
            ->assertSee('Students')
            ->assertSee('Assessments')
            ->assertSee('Attendance')
            ->assertDontSee('Assessment Items')
            ->assertDontSee(route('students.index'), false)
            ->assertDontSee(route('course-records.index'), false)
            ->assertDontSee(route('exams'), false)
            ->assertSee('Dashboard Course A')
            ->assertSee('Group A')
            ->assertSee('Stage 1')
            ->assertDontSee('Dashboard Student')
            ->assertDontSee('Dashboard Course B')
            ->assertDontSee('Other Class Student');

        $this->actingAs($user)
            ->get(route('teacher-dashboard', ['section_id' => $sectionA->id, 'tab' => 'people']))
            ->assertOk()
            ->assertSee('Dashboard Student')
            ->assertDontSee('Other Class Student');

        $this->actingAs($user)
            ->get(route('teacher-dashboard', ['section_id' => $sectionA->id, 'tab' => 'assessments']))
            ->assertOk()
            ->assertSee('Class Dashboard')
            ->assertSee('Dashboard Course A')
            ->assertSee('Materials')
            ->assertSee('Class Card Assessment')
            ->assertSee(route('assessments.index', ['section_id' => $sectionA->id, 'assessment_id' => $classAssessment->id]))
            ->assertDontSee('Other Class Assessment')
            ->assertDontSee('Other Class Student');

        $this->actingAs($user)
            ->get(route('assessments.index', ['section_id' => $sectionA->id, 'assessment_id' => $classAssessment->id]))
            ->assertOk()
            ->assertSee('Class Card Assessment')
            ->assertDontSee('Other Class Assessment');

        $this->actingAs($user)
            ->get(route('teacher-dashboard', ['section_id' => $sectionA->id, 'tab' => 'analytics']))
            ->assertOk()
            ->assertSee('Class Analytics')
            ->assertSee('Dashboard Student')
            ->assertSee('50.0%')
            ->assertSee('75.0')
            ->assertSee('82.0')
            ->assertDontSee('Other Class Student');

        $this->actingAs($user)
            ->get(route('teacher-dashboard', ['section_id' => $sectionA->id, 'tab' => 'timetable']))
            ->assertOk()
            ->assertSee('Class Timetable')
            ->assertSee('Class A Room')
            ->assertSee('Monday')
            ->assertDontSee('Class B Room');

        $adminRole = Role::create([
            'name' => 'administrator',
            'display_name' => 'Academic Administrator',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('classrooms.index'))
            ->assertOk()
            ->assertSee('Colleges')
            ->assertSee('Engineering College')
            ->assertDontSee('Dashboard Course A');

        $this->actingAs($admin)
            ->get(route('classrooms.index', ['college_id' => $college->id]))
            ->assertOk()
            ->assertSee('Departments')
            ->assertSee('Computer Science')
            ->assertDontSee('Dashboard Course A');

        $this->actingAs($admin)
            ->get(route('classrooms.index', ['college_id' => $college->id, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Stages')
            ->assertSee('Stage 1')
            ->assertSee('Stage 2')
            ->assertDontSee('Dashboard Course A');

        $classListUrl = route('classrooms.index', [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'stage' => 'Stage 1',
        ]);
        $this->actingAs($admin)
            ->get($classListUrl)
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Dashboard Course A')
            ->assertDontSee('Dashboard Course B')
            ->assertSee(route('classrooms.index', [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'stage' => 'Stage 1',
                'section_id' => $sectionA->id,
            ]));

        $this->actingAs($admin)
            ->get(route('classrooms.index', [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'stage' => '1',
            ]))
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Dashboard Course A')
            ->assertSee('Dashboard Course C')
            ->assertDontSee('Dashboard Course B');

        $this->actingAs($admin)
            ->get(route('classrooms.index', [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'stage' => 'one',
            ]))
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Dashboard Course A')
            ->assertSee('Dashboard Course C')
            ->assertDontSee('Dashboard Course B');

        $this->actingAs($admin)
            ->get(route('classrooms.index', [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'grade' => '1',
            ]))
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Dashboard Course A')
            ->assertDontSee('Dashboard Course B');

        $this->actingAs($admin)
            ->get(route('classrooms.index', ['section_id' => $sectionA->id]))
            ->assertOk()
            ->assertSee('Class Overview')
            ->assertSee('Read-only class oversight')
            ->assertSee('Oversight mode')
            ->assertSee('Read-only classroom review')
            ->assertDontSee('Quick actions')
            ->assertDontSee('Messages')
            ->assertDontSee(route('class-stream.posts.store', $sectionA), false);

        $this->actingAs($admin)
            ->get(route('classrooms.index', ['section_id' => $sectionA->id, 'tab' => 'grades']))
            ->assertOk()
            ->assertSee('Dashboard Student')
            ->assertDontSee('Enter pre-final marks')
            ->assertDontSee(route('teacher.prefinal-marks.store', $sectionA), false);
    }
}
