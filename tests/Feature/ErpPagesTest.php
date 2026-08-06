<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\SemesterCreditPolicy;
use App\Models\Stage;
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
            ->get(route('academic-years.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('stages.index'))
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

    public function test_institution_type_enforces_its_fixed_academic_structure(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('universities.create'))
            ->assertOk()
            ->assertSee('Automatic academic structure')
            ->assertSee('Standard program semesters');

        $this->actingAs($admin)
            ->post(route('universities.store'), [
                'name' => 'Technical Institute',
                'code' => 'TI',
                'institution_type' => 'institute',
                'expected_stage_count' => 9,
                'expected_semesters_per_year' => 4,
            ])
            ->assertRedirect(route('universities.index'));

        $institute = University::where('code', 'TI')->firstOrFail();
        $this->assertSame(2, $institute->expected_stage_count);
        $this->assertSame(2, $institute->expected_semesters_per_year);
        $this->assertSame(4, $institute->expectedProgramSemesterCount());

        $this->actingAs($admin)
            ->post(route('universities.store'), [
                'name' => 'Structure University',
                'code' => 'SU',
                'institution_type' => 'university',
                'expected_stage_count' => 1,
                'expected_semesters_per_year' => 1,
            ])
            ->assertRedirect(route('universities.index'));

        $university = University::where('code', 'SU')->firstOrFail();
        $this->assertSame(4, $university->expected_stage_count);
        $this->assertSame(2, $university->expected_semesters_per_year);
        $this->assertSame(8, $university->expectedProgramSemesterCount());
    }

    public function test_university_with_advanced_stages_cannot_be_changed_to_an_institute(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Four Stage University', 'code' => 'FSU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $department = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Computing',
            'code' => 'COMP',
        ]);
        Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 3',
            'sequence' => 3,
        ]);

        $this->actingAs($admin)
            ->put(route('universities.update', $university), [
                'name' => $university->name,
                'code' => $university->code,
                'institution_type' => 'institute',
            ])
            ->assertSessionHasErrors('institution_type');

        $this->assertSame('university', $university->fresh()->institution_type);
    }

    public function test_institute_rejects_a_third_stage(): void
    {
        $admin = $this->makeSuperAdmin();
        $institute = University::create(['name' => 'Two Stage Institute', 'code' => 'TSI', 'institution_type' => 'institute']);
        $college = College::create(['university_id' => $institute->id, 'name' => 'Technical College', 'code' => 'TC']);
        $department = Department::create([
            'university_id' => $institute->id,
            'college_id' => $college->id,
            'name' => 'Information Technology',
            'code' => 'IT',
        ]);

        $this->actingAs($admin)
            ->post(route('stages.store'), [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'name' => 'Stage 3',
                'code' => 'S3',
                'sequence' => 3,
            ])
            ->assertSessionHasErrors('sequence');

        $this->assertDatabaseMissing('stages', [
            'department_id' => $department->id,
            'sequence' => 3,
        ]);
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
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
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
            Enrollment::create([
                'student_id' => $additionalStudent->id,
                'course_section_id' => $section->id,
                'status' => 'enrolled',
                'enrolled_at' => now()->toDateString(),
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
        Enrollment::create([
            'student_id' => $failedStudent->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ]);
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get(route('bologna-definition'))
            ->assertOk()
            ->assertSee('Bologna Definition')
            ->assertSee('Academic Setup')
            ->assertSee('Academic Years')
            ->assertSee('Semester Credit Policy')
            ->assertSee('Module Offerings')
            ->assertSeeInOrder([
                'Universities',
                'Colleges',
                'Departments',
                'Stages',
                'Semester Credit Policy',
                'Course Catalog',
                'Academic Years',
                'Semesters',
                'Module Offerings',
            ])
            ->assertSee('Student Rankings')
            ->assertSee('Open rankings')
            ->assertSee('Stages And Credits')
            ->assertSee('Programming')
            ->assertDontSee('Ranking Student')
            ->assertDontSee('S-RANK-1')
            ->assertSee('Stage 1');

        $this->actingAs($user)
            ->get(route('module-offerings.index'))
            ->assertOk()
            ->assertSee('Module Offerings')
            ->assertSee('Programming');

        $this->actingAs($user)
            ->get(route('bologna-definition.semester-credit-policy'))
            ->assertOk()
            ->assertSee('Semester Credit Policy')
            ->assertSee('ECTS/Credits Per Semester')
            ->assertSee('Credits Required To Progress')
            ->assertSee('Credits Required To Graduate');

        $this->actingAs($user)
            ->post(route('bologna-definition.semester-credit-policy.store'), [
                'policies' => [
                    $university->id => [
                        'semester_credits' => 30,
                        'passing_credits' => 18,
                        'graduation_credits' => 240,
                    ],
                ],
            ])
            ->assertRedirect(route('bologna-definition.semester-credit-policy'));

        $this->assertDatabaseHas('semester_credit_policies', [
            'university_id' => $university->id,
            'semester_credits' => 30,
            'passing_credits' => 18,
            'graduation_credits' => 240,
        ]);

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

    public function test_bologna_definition_is_available_without_academic_data(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->get(route('bologna-definition'))
            ->assertOk()
            ->assertSee('Bologna Definition');
    }

    public function test_rankings_only_include_students_who_passed_every_enrolled_module(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Ranking University', 'code' => 'RU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Ranking College', 'code' => 'RC']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Ranking Department', 'code' => 'RD']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2027/2028']);
        $firstCourse = Course::create(['department_id' => $department->id, 'code' => 'R101', 'name' => 'Weighted Major', 'credits' => 4]);
        $secondCourse = Course::create(['department_id' => $department->id, 'code' => 'R102', 'name' => 'Weighted Minor', 'credits' => 2]);
        $firstSection = CourseSection::create(['course_id' => $firstCourse->id, 'semester_id' => $semester->id, 'section_code' => 'A', 'grade_level' => 'Stage 1']);
        $secondSection = CourseSection::create(['course_id' => $secondCourse->id, 'semester_id' => $semester->id, 'section_code' => 'A', 'grade_level' => 'Stage 1']);

        $students = collect([
            ['id' => 'R-1', 'name' => 'Weighted Winner', 'marks' => [100, 50]],
            ['id' => 'R-2', 'name' => 'Complete Student', 'marks' => [80, 80]],
            ['id' => 'R-3', 'name' => 'Missing Result Student', 'marks' => [95, null]],
            ['id' => 'R-4', 'name' => 'Failed Module Student', 'marks' => [95, 49]],
            ['id' => 'R-5', 'name' => 'Tied Student', 'marks' => [80, 80]],
        ])->map(function (array $record) use ($university, $department, $firstCourse, $secondCourse, $firstSection, $secondSection) {
            $student = Student::create([
                'university_id' => $university->id,
                'department_id' => $department->id,
                'student_id' => $record['id'],
                'full_name' => $record['name'],
                'email' => strtolower(str_replace(' ', '.', $record['name'])).'@example.com',
                'status' => 'Active',
            ]);

            foreach ([[$firstCourse, $firstSection], [$secondCourse, $secondSection]] as $index => [$course, $section]) {
                Enrollment::create(['student_id' => $student->id, 'course_section_id' => $section->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
                if (! is_null($record['marks'][$index])) {
                    Mark::create([
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'course_section_id' => $section->id,
                        'final_mark' => $record['marks'][$index],
                        'visibility_status' => 'published',
                        'submission_status' => 'approved',
                    ]);
                }
            }

            return $student;
        });

        $secondSection->delete();
        SemesterCreditPolicy::create([
            'university_id' => $university->id,
            'semester_credits' => 6,
            'passing_credits' => 6,
            'graduation_credits' => 12,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('bologna-definition.student-rankings'))
            ->assertOk()
            ->assertSeeInOrder(['Weighted Winner', 'Complete Student', 'Tied Student'])
            ->assertSee('83.3')
            ->assertDontSee($students[2]->full_name)
            ->assertDontSee($students[3]->full_name)
            ->assertSee('6 this stage')
            ->assertSee('Eligible');

        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), '#2'));

        $this->actingAs($admin)
            ->get(route('bologna-definition.student-rankings.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
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
            ->assertDontSee('Semester Names')
            ->assertSee('BND University');

        $this->actingAs($user)
            ->post(route('academic-years.store'), [
                'academic_year' => '2027/2028',
                'university_ids' => [$university->id],
                'starts_on' => '2027-09-01 00:00:00',
                'ends_on' => '2028-06-30 00:00:00',
                'status' => 'upcoming',
            ])
            ->assertRedirect(route('academic-years.index'));

        $this->assertDatabaseHas('academic_years', [
            'university_id' => $university->id,
                'name' => '2027/2028',
                'status' => 'upcoming',
                'starts_on' => '2027-09-01 00:00:00',
                'ends_on' => '2028-06-30 00:00:00',
        ]);

        $this->assertDatabaseMissing('semesters', [
            'university_id' => $university->id,
            'academic_year' => '2027/2028',
        ]);
        $this->assertSame(1, University::count());
        $this->assertSame(1, College::count());
        $this->assertSame(1, Department::count());
        $this->assertSame(1, Course::count());
    }

    public function test_academic_year_and_stage_validation_protects_the_structure(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Structure University', 'code' => 'STRU']);
        $institute = University::create(['name' => 'Structure Institute', 'code' => 'INST', 'institution_type' => 'institute']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software', 'code' => 'SWE']);

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'academic_year' => '2027/2029',
                'university_ids' => [$university->id],
                'starts_on' => '2027-09-01',
                'ends_on' => '2028-06-30',
                'status' => 'upcoming',
            ])
            ->assertSessionHasErrors('academic_year');

        $this->actingAs($admin)
            ->post(route('stages.store'), [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'name' => 'Stage 1',
                'code' => 'S1',
                'sequence' => 1,
            ])
            ->assertRedirect(route('stages.index'));

        $stage = Stage::firstOrFail();
        $this->assertSame($university->id, $stage->university_id);

        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Semester 1', 'academic_year' => '2027/2028']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'SWE101', 'name' => 'Software Design', 'credits' => 3]);
        CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'section_code' => 'A',
        ]);

        $this->actingAs($admin)
            ->delete(route('stages.destroy', $stage))
            ->assertSessionHas('error', 'This stage is used by modules and cannot be deleted.');
        $this->assertDatabaseHas('stages', ['id' => $stage->id]);

        AcademicYear::create(['university_id' => $university->id, 'name' => '2028/2029', 'status' => 'active']);
        AcademicYear::create(['university_id' => $institute->id, 'name' => '2028/2029', 'status' => 'active']);

        foreach (range(1, 2) as $index) {
            $this->actingAs($admin)
                ->post(route('semesters.store'), [
                    'name' => 'Semester '.$index,
                    'academic_year' => '2028/2029',
                    'university_id' => $university->id,
                    'term_type' => 'regular',
                    'sequence' => $index,
                    'start_date' => $index === 1 ? '2028-09-01' : '2029-02-01',
                    'end_date' => $index === 1 ? '2029-01-31' : '2029-06-30',
                ])
                ->assertRedirect(route('semesters.index'));
        }

        $this->actingAs($admin)->post(route('semesters.store'), [
            'name' => 'Summer', 'academic_year' => '2028/2029', 'university_id' => $university->id,
            'term_type' => 'summer', 'sequence' => 3, 'start_date' => '2029-07-01', 'end_date' => '2029-08-31',
        ])->assertRedirect(route('semesters.index'));

        $this->actingAs($admin)
            ->post(route('semesters.store'), [
                'name' => 'Semester 4',
                'academic_year' => '2028/2029',
                'university_id' => $university->id,
                'term_type' => 'regular',
                'sequence' => 4,
                'start_date' => '2029-09-01',
                'end_date' => '2029-10-01',
            ])
            ->assertSessionHasErrors('term_type');

        foreach (range(1, 2) as $index) {
            $this->actingAs($admin)
                ->post(route('semesters.store'), [
                    'name' => 'Semester '.$index,
                    'academic_year' => '2028/2029',
                    'university_id' => $institute->id,
                    'term_type' => 'regular',
                    'sequence' => $index,
                    'start_date' => $index === 1 ? '2028-09-01' : '2029-02-01',
                    'end_date' => $index === 1 ? '2029-01-31' : '2029-06-30',
                ])
                ->assertRedirect(route('semesters.index'));
        }

        $this->actingAs($admin)->post(route('semesters.store'), [
            'name' => 'Summer', 'academic_year' => '2028/2029', 'university_id' => $institute->id,
            'term_type' => 'summer', 'sequence' => 3, 'start_date' => '2029-07-01', 'end_date' => '2029-08-31',
        ])->assertRedirect(route('semesters.index'));

        $this->actingAs($admin)
            ->post(route('semesters.store'), [
                'name' => 'Semester 4',
                'academic_year' => '2028/2029',
                'university_id' => $institute->id,
                'term_type' => 'summer',
                'sequence' => 4,
                'start_date' => '2029-09-01',
                'end_date' => '2029-10-01',
            ])
            ->assertSessionHasErrors('term_type');
    }

    public function test_parent_academic_records_with_children_cannot_be_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Protected University', 'code' => 'PROT']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Protected College', 'code' => 'PC']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Protected Department', 'code' => 'PD']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2027/2028']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'PRO101', 'name' => 'Protected Module', 'credits' => 3]);
        CourseSection::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'section_code' => 'A']);

        $this->actingAs($admin)->delete(route('universities.destroy', $university))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('colleges.destroy', $college))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('departments.destroy', $department))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('semesters.destroy', $semester))->assertSessionHas('error');

        $this->assertDatabaseHas('universities', ['id' => $university->id]);
        $this->assertDatabaseHas('colleges', ['id' => $college->id]);
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('semesters', ['id' => $semester->id]);
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

    public function test_direct_academic_permission_is_limited_to_the_assigned_organization(): void
    {
        $firstUniversity = University::create(['name' => 'Scoped University', 'code' => 'SCU']);
        $secondUniversity = University::create(['name' => 'Hidden University', 'code' => 'HDU']);
        $firstCollege = College::create(['university_id' => $firstUniversity->id, 'name' => 'Scoped College', 'code' => 'SCC']);
        $secondCollege = College::create(['university_id' => $secondUniversity->id, 'name' => 'Hidden College', 'code' => 'HDC']);
        $firstDepartment = Department::create(['university_id' => $firstUniversity->id, 'college_id' => $firstCollege->id, 'name' => 'Scoped Department', 'code' => 'SCD']);
        Department::create(['university_id' => $secondUniversity->id, 'college_id' => $secondCollege->id, 'name' => 'Hidden Department', 'code' => 'HDD']);
        $role = Role::create(['name' => 'custom_academic_user', 'display_name' => 'Custom Academic User']);
        $permission = Permission::create(['name' => 'academic_setup.view', 'display_name' => 'View academic setup']);
        $user = User::factory()->create([
            'university_id' => $firstUniversity->id,
            'college_id' => $firstCollege->id,
            'department_id' => $firstDepartment->id,
        ]);
        $user->roles()->attach($role);
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);

        $this->actingAs($user)
            ->get(route('universities.index'))
            ->assertOk()
            ->assertSee('Scoped University')
            ->assertDontSee('Hidden University');

        $this->actingAs($user)
            ->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Scoped Department')
            ->assertDontSee('Hidden Department');
    }

    public function test_academic_year_dates_cannot_overlap_and_open_years_can_be_edited(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Calendar University', 'code' => 'CAL']);
        $year = AcademicYear::create([
            'university_id' => $university->id,
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ]);
        Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $year->id,
            'name' => 'Semester 1',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-15',
        ]);
        Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $year->id,
            'name' => 'Semester 2',
            'term_type' => 'regular',
            'sequence' => 2,
            'start_date' => '2027-01-16',
            'end_date' => '2027-06-20',
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'academic_year' => '2027/2028',
                'university_ids' => [$university->id],
                'starts_on' => '2027-06-01',
                'ends_on' => '2028-05-31',
                'status' => 'upcoming',
            ])
            ->assertSessionHasErrors('starts_on');

        $this->actingAs($admin)
            ->put(route('academic-years.update', $year), [
                'name' => '2026/2027',
                'starts_on' => '2026-08-20',
                'ends_on' => '2027-06-20',
                'status' => 'active',
            ])
            ->assertRedirect(route('academic-years.index'));

        $this->assertDatabaseHas('academic_years', [
            'id' => $year->id,
            'starts_on' => '2026-08-20 00:00:00',
            'ends_on' => '2027-06-20 00:00:00',
        ]);
    }

    public function test_active_academic_year_can_generate_its_regular_semesters(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create([
            'name' => 'Generated Calendar University',
            'code' => 'GCU',
            'expected_semesters_per_year' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('academic-years.store'), [
                'academic_year' => '2028/2029',
                'university_ids' => [$university->id],
                'starts_on' => '2028-09-01',
                'ends_on' => '2029-06-30',
                'status' => 'active',
                'generate_semesters' => 1,
            ])
            ->assertRedirect(route('academic-years.index'));

        $academicYear = AcademicYear::where('university_id', $university->id)
            ->where('name', '2028/2029')
            ->firstOrFail();

        $this->assertSame('active', $academicYear->status);
        $this->assertSame([1, 2], $academicYear->semesters()->orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame(2, $academicYear->semesters()->where('term_type', 'regular')->count());
    }

    public function test_semester_dates_cannot_overlap_and_closed_years_are_read_only(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = University::create(['name' => 'Locked Calendar University', 'code' => 'LCU']);
        $academicYear = AcademicYear::create([
            'university_id' => $university->id,
            'name' => '2028/2029',
            'starts_on' => '2028-09-01',
            'ends_on' => '2029-06-30',
            'status' => 'upcoming',
        ]);
        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester 1',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2028-09-01',
            'end_date' => '2029-01-31',
        ]);

        $semesterPayload = [
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester 2',
            'term_type' => 'regular',
            'sequence' => 2,
            'start_date' => '2029-01-15',
            'end_date' => '2029-06-30',
        ];

        $this->actingAs($admin)
            ->post(route('semesters.store'), $semesterPayload)
            ->assertSessionHasErrors('start_date');

        $academicYear->update(['status' => 'closed']);

        $this->actingAs($admin)
            ->post(route('semesters.store'), [
                ...$semesterPayload,
                'start_date' => '2029-02-01',
            ])
            ->assertSessionHasErrors('academic_year_id');
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
