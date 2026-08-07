<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationScopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_attendance_and_submissions_follow_the_module_organization(): void
    {
        [$firstUniversity, $firstDepartment] = $this->organization('First', 'ONE');
        [$secondUniversity, $secondDepartment] = $this->organization('Second', 'TWO');
        $firstStudent = $this->student($firstUniversity, $firstDepartment, 'ONE-1');
        $secondStudent = $this->student($secondUniversity, $secondDepartment, 'TWO-1');
        [$firstCourse, $firstSection] = $this->module($firstUniversity, $firstDepartment, 'ONE101');
        [$secondCourse, $secondSection] = $this->module($secondUniversity, $secondDepartment, 'TWO101');

        Mark::create(['student_id' => $secondStudent->id, 'course_id' => $firstCourse->id, 'course_section_id' => $firstSection->id, 'final_mark' => 70]);
        Mark::create(['student_id' => $firstStudent->id, 'course_id' => $secondCourse->id, 'course_section_id' => $secondSection->id, 'final_mark' => 80]);
        Attendance::create(['student_id' => $secondStudent->id, 'course_id' => $firstCourse->id, 'course_section_id' => $firstSection->id, 'date' => now(), 'status' => 'present']);
        Attendance::create(['student_id' => $firstStudent->id, 'course_id' => $secondCourse->id, 'course_section_id' => $secondSection->id, 'date' => now(), 'status' => 'present']);
        $firstAssessment = AssessmentItem::create(['course_section_id' => $firstSection->id, 'title' => 'First assessment', 'type' => 'assignment', 'max_score' => 100]);
        $secondAssessment = AssessmentItem::create(['course_section_id' => $secondSection->id, 'title' => 'Second assessment', 'type' => 'assignment', 'max_score' => 100]);
        AssessmentSubmission::create(['assessment_item_id' => $firstAssessment->id, 'student_id' => $secondStudent->id, 'status' => 'submitted']);
        AssessmentSubmission::create(['assessment_item_id' => $secondAssessment->id, 'student_id' => $firstStudent->id, 'status' => 'submitted']);

        $user = $this->scopedUser('examination_administrator', $firstUniversity, $firstDepartment);
        $this->actingAs($user);

        $this->assertSame([$firstCourse->id], Mark::pluck('course_id')->all());
        $this->assertSame([$firstCourse->id], Attendance::pluck('course_id')->all());
        $this->assertSame([$firstAssessment->id], AssessmentSubmission::pluck('assessment_item_id')->all());
    }

    public function test_rooms_and_structure_models_are_limited_to_the_assigned_university(): void
    {
        [$firstUniversity, $firstDepartment] = $this->organization('First', 'ONE');
        [$secondUniversity, $secondDepartment] = $this->organization('Second', 'TWO');
        Classroom::create(['university_id' => $firstUniversity->id, 'name' => 'Visible Room']);
        Classroom::create(['university_id' => $secondUniversity->id, 'name' => 'Hidden Room']);
        AcademicYear::create(['university_id' => $firstUniversity->id, 'name' => '2026/2027']);
        AcademicYear::create(['university_id' => $secondUniversity->id, 'name' => '2027/2028']);
        Stage::create(['university_id' => $firstUniversity->id, 'department_id' => $firstDepartment->id, 'name' => 'Stage 1', 'sequence' => 1]);
        Stage::create(['university_id' => $secondUniversity->id, 'department_id' => $secondDepartment->id, 'name' => 'Stage 2', 'sequence' => 1]);

        $user = $this->scopedUser('department_administrator', $firstUniversity, $firstDepartment);
        $this->actingAs($user);

        $this->assertSame(['Visible Room'], Classroom::pluck('name')->all());
        $this->assertSame(['2026/2027'], AcademicYear::pluck('name')->all());
        $this->assertSame(['Stage 1'], Stage::pluck('name')->all());
    }

    public function test_student_search_only_returns_enrolled_courses(): void
    {
        [$university, $department] = $this->organization('Search', 'SEA');
        $student = $this->student($university, $department, 'SEA-1');
        [$visibleCourse, $visibleSection] = $this->module($university, $department, 'SEA101', 'Shared Search Visible');
        [$hiddenCourse] = $this->module($university, $department, 'SEA102', 'Shared Search Hidden');
        Enrollment::create(['student_id' => $student->id, 'course_section_id' => $visibleSection->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        $user = $this->portalUser('student', $student->email);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Shared Search']))
            ->assertOk()
            ->assertSee($visibleCourse->name)
            ->assertDontSee($hiddenCourse->name);
    }

    public function test_teacher_search_only_returns_assigned_courses(): void
    {
        [$university, $department] = $this->organization('Teaching', 'TEA');
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'TEA-1',
            'full_name' => 'Scoped Teacher',
            'email' => 'scoped.teacher@example.com',
            'status' => 'Active',
        ]);
        [$visibleCourse, $visibleSection] = $this->module($university, $department, 'TEA101', 'Shared Teaching Visible');
        [$hiddenCourse] = $this->module($university, $department, 'TEA102', 'Shared Teaching Hidden');
        $visibleSection->update(['teacher_id' => $teacher->id]);
        $user = $this->portalUser('teacher', $teacher->email);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'Shared Teaching']))
            ->assertOk()
            ->assertSee($visibleCourse->name)
            ->assertDontSee($hiddenCourse->name);
    }

    private function organization(string $name, string $code): array
    {
        $university = University::create(['name' => $name.' University', 'code' => $code]);
        $department = Department::create(['university_id' => $university->id, 'name' => $name.' Department']);

        return [$university, $department];
    }

    private function student(University $university, Department $department, string $studentId): Student
    {
        return Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => $studentId,
            'full_name' => $studentId.' Student',
            'email' => strtolower($studentId).'@example.com',
            'status' => 'Active',
        ]);
    }

    private function module(University $university, Department $department, string $code, ?string $name = null): array
    {
        $course = Course::create(['department_id' => $department->id, 'code' => $code, 'name' => $name ?? $code, 'credits' => 3]);
        $semester = Semester::firstOrCreate(
            ['university_id' => $university->id, 'name' => 'Semester 1', 'academic_year' => '2026/2027'],
            ['status' => 'active']
        );
        $section = CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'section_code' => $code, 'grade_level' => 'Stage 1', 'status' => 'active']);

        return [$course, $section];
    }

    private function scopedUser(string $roleName, University $university, Department $department): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->headline()]);
        $user = User::factory()->create(['university_id' => $university->id, 'department_id' => $department->id]);
        $user->roles()->attach($role);

        return $user;
    }

    private function portalUser(string $roleName, string $email): User
    {
        $permission = Permission::firstOrCreate(['name' => 'courses.view'], ['display_name' => 'View courses']);
        $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->headline()]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach($role);

        return $user;
    }
}
