<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $role = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_core_erp_pages_are_accessible_for_authenticated_users(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($user)
            ->get('/students')
            ->assertOk();

        $this->actingAs($user)
            ->get('/teachers')
            ->assertOk();

        $this->actingAs($user)
            ->get('/course-records')
            ->assertOk();

        $this->actingAs($user)
            ->get('/timetables')
            ->assertOk();

        $this->actingAs($user)
            ->get('/universities')
            ->assertOk();

        $this->actingAs($user)
            ->get('/departments')
            ->assertOk();

        $this->actingAs($user)
            ->get('/colleges')
            ->assertOk();

        $this->actingAs($user)
            ->get('/semesters')
            ->assertOk();

        $this->actingAs($user)
            ->get('/bologna-definition')
            ->assertOk();

        $this->actingAs($user)
            ->get('/exams')
            ->assertOk();

        $this->actingAs($user)
            ->get('/finance')
            ->assertOk();

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk();
    }

    public function test_bologna_definition_and_setup_are_available_to_academic_administrator(): void
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $engineering = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $science = College::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $cs = Department::create(['university_id' => $university->id, 'college_id' => $engineering->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $math = Department::create(['university_id' => $university->id, 'college_id' => $science->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026']);
        $course = Course::create(['department_id' => $cs->id, 'code' => 'CS101', 'name' => 'Programming', 'credits' => 4]);
        Course::create(['department_id' => $math->id, 'code' => 'MATH101', 'name' => 'Calculus', 'credits' => 4]);
        $section = CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'section_code' => 'A', 'grade_level' => 'Stage 1']);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $cs->id,
            'student_id' => 'S-RANK-1',
            'full_name' => 'Ranking Student',
            'email' => 'ranking.student@example.com',
            'status' => 'Active',
        ]);
        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 91,
            'visibility_status' => 'published',
            'submission_status' => 'approved',
        ]);
        foreach (range(2, 6) as $index) {
            $additionalStudent = Student::create([
                'university_id' => $university->id,
                'department_id' => $cs->id,
                'student_id' => 'S-RANK-'.$index,
                'full_name' => 'Ranking Student '.$index,
                'email' => 'ranking.student'.$index.'@example.com',
                'status' => 'Active',
            ]);
            Mark::create([
                'student_id' => $additionalStudent->id,
                'course_id' => $course->id,
                'course_section_id' => $section->id,
                'final_mark' => 90 - $index,
                'visibility_status' => 'published',
                'submission_status' => 'approved',
            ]);
        }
        $failedStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $cs->id,
            'student_id' => 'S-FAILED-1',
            'full_name' => 'Failed Ranking Student',
            'email' => 'failed.ranking.student@example.com',
            'status' => 'Active',
        ]);
        Mark::create([
            'student_id' => $failedStudent->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 49,
            'visibility_status' => 'published',
            'submission_status' => 'approved',
        ]);
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('bologna-definition'))
            ->assertOk()
            ->assertSee('Bologna Definition')
            ->assertSee('Add Academic Year')
            ->assertSee('Academic Setup')
            ->assertSee('Academic Years')
            ->assertSee('Student Rankings')
            ->assertSee('Open rankings')
            ->assertSee('Stages And Credits')
            ->assertSee('Programming')
            ->assertDontSee('Ranking Student')
            ->assertDontSee('S-RANK-1')
            ->assertSee('Stage 1');

        $this->actingAs($user)
            ->get(route('bologna-definition.student-rankings'))
            ->assertOk()
            ->assertSee('Back to Bologna Definition')
            ->assertSee('Ranking Directory')
            ->assertSee('Ranking Student')
            ->assertSee('S-RANK-1')
            ->assertSee('S-RANK-6')
            ->assertSee('All passed students')
            ->assertDontSee('S-FAILED-1')
            ->assertSee('Stage 1');

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Computer Science')
            ->assertSee('Add Department');

        $this->actingAs($user)->get(route('departments.create'))->assertOk();
        $this->actingAs($user)->get(route('colleges.create'))->assertOk();
        $this->actingAs($user)->get(route('semesters.create'))->assertOk();
    }

    public function test_academic_administrator_can_open_new_academic_year_without_redefining_structure(): void
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'CS']);
        Course::create(['department_id' => $department->id, 'code' => 'CS101', 'name' => 'Programming', 'credits' => 4]);
        Semester::create([
            'university_id' => $university->id,
            'name' => 'Semester 1',
            'academic_year' => '2026/2027',
        ]);
        Semester::create([
            'university_id' => $university->id,
            'name' => 'Semester 2',
            'academic_year' => '2026/2027',
        ]);
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('academic-years.create'))
            ->assertOk()
            ->assertSee('Add Academic Year')
            ->assertSee('Semester 1')
            ->assertSee('Semester 2')
            ->assertSee('BND University');

        $this->actingAs($user)
            ->post(route('academic-years.store'), [
                'academic_year' => '2027/2028',
                'university_ids' => [$university->id],
                'semester_names' => "Semester 1\nSemester 2",
                'first_semester_start_date' => '2027-09-01',
                'semester_length_months' => 5,
            ])
            ->assertRedirect(route('bologna-definition'));

        $this->assertDatabaseHas('semesters', [
            'university_id' => $university->id,
            'name' => 'Semester 1',
            'academic_year' => '2027/2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-01-31',
        ]);
        $this->assertDatabaseHas('semesters', [
            'university_id' => $university->id,
            'name' => 'Semester 2',
            'academic_year' => '2027/2028',
            'start_date' => '2028-02-01',
            'end_date' => '2028-06-30',
        ]);
        $this->assertSame(1, University::count());
        $this->assertSame(1, College::count());
        $this->assertSame(1, Department::count());
        $this->assertSame(1, Course::count());
    }

    public function test_college_administrator_cannot_open_academic_setup_without_direct_permission(): void
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $engineering = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $science = College::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $cs = Department::create(['university_id' => $university->id, 'college_id' => $engineering->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $math = Department::create(['university_id' => $university->id, 'college_id' => $science->id, 'name' => 'Mathematics', 'code' => 'MATH']);
        $role = Role::create(['name' => 'college_administrator', 'display_name' => 'College Administrator']);
        $user = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $engineering->id,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('departments.create'))->assertForbidden();
        $this->actingAs($user)->get(route('colleges.create'))->assertForbidden();
        $this->actingAs($user)->get(route('semesters.create'))->assertForbidden();

        $permission = Permission::create(['name' => 'academic_setup.manage', 'display_name' => 'Manage academic setup']);
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Computer Science')
            ->assertDontSee('Mathematics');
    }

    public function test_students_page_lists_database_records(): void
    {
        $user = $this->makeSuperAdmin();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);
        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-1001',
            'full_name' => 'Amina Rashid',
            'email' => 'amina@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get('/students')
            ->assertOk()
            ->assertSee('Amina Rashid');
    }

    public function test_students_page_can_filter_and_classify_by_college_department_and_grade(): void
    {
        $user = $this->makeSuperAdmin();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $engineering = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $science = College::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $software = Department::create(['university_id' => $university->id, 'college_id' => $engineering->id, 'name' => 'Software Engineering', 'code' => 'SWE']);
        $biology = Department::create(['university_id' => $university->id, 'college_id' => $science->id, 'name' => 'Biology', 'code' => 'BIO']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $software->id, 'code' => 'SWE101', 'name' => 'Programming', 'credits' => 3, 'semester' => 'Fall']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 2',
            'capacity' => 40,
        ]);

        $matchingStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $software->id,
            'student_id' => 'SWE-2001',
            'full_name' => 'Classified Student',
            'email' => 'classified@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $matchingStudent->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Student::create([
            'university_id' => $university->id,
            'department_id' => $biology->id,
            'student_id' => 'BIO-1001',
            'full_name' => 'Other College Student',
            'email' => 'other-college@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get('/students?college_id='.$engineering->id.'&department_id='.$software->id.'&grade_level=Stage%202')
            ->assertOk()
            ->assertSee('Engineering')
            ->assertSee('Software Engineering')
            ->assertSee('Stage 2')
            ->assertSee('Classified Student')
            ->assertDontSee('Other College Student');
    }

    public function test_courses_page_lists_database_records(): void
    {
        $user = $this->makeSuperAdmin();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        Course::create([
            'department_id' => $department->id,
            'code' => 'CS101',
            'name' => 'Introduction to Programming',
            'credits' => 3,
            'semester' => 'Fall',
        ]);

        $this->actingAs($user)
            ->get('/course-records')
            ->assertOk()
            ->assertSee('Introduction to Programming')
            ->assertSee('CS101');
    }

    public function test_teacher_cannot_access_global_exams_even_with_marks_permission(): void
    {
        $permission = Permission::create(['name' => 'marks.view', 'display_name' => 'View marks']);
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $role->permissions()->attach($permission);
        $teacher = User::factory()->create();
        $teacher->roles()->attach($role);

        $this->actingAs($teacher)
            ->get(route('exams'))
            ->assertForbidden();
    }

    public function test_results_overview_filters_hierarchy_and_exports_csv(): void
    {
        $user = $this->makeSuperAdmin();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software Engineering', 'code' => 'SWE']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-900',
            'full_name' => 'Dr. Results Teacher',
            'email' => 'results.teacher@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create(['department_id' => $department->id, 'code' => 'SWE401', 'name' => 'Result Systems', 'credits' => 3, 'status' => 'active']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 2',
            'capacity' => 35,
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9001',
            'full_name' => 'Visible Results Student',
            'email' => 'visible.results@example.com',
            'status' => 'Active',
        ]);
        $hiddenStudent = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9002',
            'full_name' => 'Hidden Results Student',
            'email' => 'hidden.results@example.com',
            'status' => 'Active',
        ]);

        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 88,
            'submission_status' => 'submitted',
            'visibility_status' => 'draft',
            'submitted_at' => now(),
        ]);
        Mark::create([
            'student_id' => $hiddenStudent->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 61,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);

        $query = [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'stage' => 'Second Stage',
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'submission_status' => 'submitted',
            'q' => 'Visible',
        ];

        $this->actingAs($user)
            ->get(route('exams', $query))
            ->assertOk()
            ->assertSee('Results Overview')
            ->assertSee('Export CSV')
            ->assertSee('Result Systems / Group B')
            ->assertSee('Visible Results Student')
            ->assertDontSee('Hidden Results Student');

        $export = $this->actingAs($user)->get(route('exams.export', $query));
        $export->assertOk();
        $export->assertDownload();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Visible Results Student', $csv);
        $this->assertStringNotContainsString('Hidden Results Student', $csv);
    }

    public function test_results_overview_shows_result_analytics_filters_sorting_and_missing_marks(): void
    {
        $user = $this->makeSuperAdmin();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software Engineering', 'code' => 'SWE']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-901',
            'full_name' => 'Dr. Result Analytics',
            'email' => 'analytics.teacher@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create(['department_id' => $department->id, 'code' => 'SWE402', 'name' => 'Analytics Results', 'credits' => 3, 'status' => 'active']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 4',
            'capacity' => 40,
            'status' => 'active',
        ]);

        $highest = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9101',
            'full_name' => 'Highest Mark Student',
            'email' => 'highest@example.com',
            'status' => 'Active',
        ]);
        $ready = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9102',
            'full_name' => 'Ready Publish Student',
            'email' => 'ready@example.com',
            'status' => 'Active',
        ]);
        $lowest = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9103',
            'full_name' => 'Lowest Mark Student',
            'email' => 'lowest@example.com',
            'status' => 'Active',
        ]);
        $missing = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'SWE-9104',
            'full_name' => 'Missing Mark Student',
            'email' => 'missing@example.com',
            'status' => 'Active',
        ]);

        foreach ([$highest, $ready, $lowest, $missing] as $student) {
            Enrollment::create([
                'student_id' => $student->id,
                'course_section_id' => $section->id,
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);
        }

        Mark::create([
            'student_id' => $highest->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 91,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Mark::create([
            'student_id' => $ready->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 73,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);
        Mark::create([
            'student_id' => $lowest->id,
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'final_mark' => 41,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $query = [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'stage' => 'Stage 4',
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
        ];

        $this->actingAs($user)
            ->get(route('exams', $query + ['sort' => 'final_desc']))
            ->assertOk()
            ->assertSee('Ready to Publish')
            ->assertSee('Passed')
            ->assertSee('Failed')
            ->assertSee('Pass Rate')
            ->assertSee('Avg Published Mark')
            ->assertSee('Missing Results')
            ->assertSeeInOrder(['Highest Mark Student', 'Ready Publish Student', 'Lowest Mark Student'])
            ->assertDontSee('Missing Mark Student');

        $this->actingAs($user)
            ->get(route('exams', $query + ['result_status' => 'failed']))
            ->assertOk()
            ->assertSee('Lowest Mark Student')
            ->assertDontSee('Highest Mark Student')
            ->assertDontSee('Ready Publish Student');

        $export = $this->actingAs($user)->get(route('exams.export', $query + ['result_status' => 'failed']));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Lowest Mark Student', $csv);
        $this->assertStringContainsString('Failed', $csv);
        $this->assertStringNotContainsString('Highest Mark Student', $csv);
    }
}
