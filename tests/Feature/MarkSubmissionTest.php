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
use App\Models\User;
use App\Services\MarkSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->department = Department::factory()->create();
        $this->course = Course::factory()->create([
            'department_id' => $this->department->id,
        ]);
        $this->student = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
        ]);
        $this->semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Current Semester',
            'academic_year' => '2026/2027',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'section_code' => 'A',
            'capacity' => 40,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $this->student->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ]);
        Mark::creating(function (Mark $mark) {
            if (! $mark->course_section_id
                && (int) $mark->student_id === (int) $this->student->id
                && (int) $mark->course_id === (int) $this->course->id) {
                $mark->course_section_id = $this->section->id;
            }
        });
        $this->teacher = User::factory()->create(['email' => 'teacher@test.com']);
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'university_id' => $this->department->university_id,
        ]);
        $this->committee = User::factory()->create([
            'email' => 'committee@test.com',
            'university_id' => $this->department->university_id,
        ]);

        // Create roles
        $this->createRoles();
        $this->teacher->roles()->attach($this->getRole('teacher'));
        $this->admin->roles()->attach($this->getRole('examination_administrator'));
        $this->committee->roles()->attach($this->getRole('examination_committee'));
    }

    private function createRoles()
    {
        if (! Role::where('name', 'teacher')->exists()) {
            Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        }
        if (! Role::where('name', 'examination_administrator')->exists()) {
            $role = Role::create(['name' => 'examination_administrator', 'display_name' => 'Examination Administrator']);

            foreach (['marks.review', 'marks.approve', 'marks.publish', 'marks.request_change'] as $permissionName) {
                $permission = Permission::create([
                    'name' => $permissionName,
                    'display_name' => $permissionName,
                ]);
                $role->permissions()->attach($permission);
            }
        }
        if (! Role::where('name', 'examination_committee')->exists()) {
            $role = Role::create(['name' => 'examination_committee', 'display_name' => 'Examination Committee']);

            foreach (['marks.enter_final_exam', 'marks.review', 'marks.approve', 'marks.request_change'] as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName],
                    ['display_name' => $permissionName]
                );
                $role->permissions()->syncWithoutDetaching($permission);
            }
        }
    }

    private function getRole($name)
    {
        return Role::where('name', $name)->first();
    }

    public function test_teacher_cannot_submit_final_marks()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 85,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->teacher);

        $response = $this->postJson('/marks/submit', [
            'mark_ids' => [$mark->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'submission_status' => 'draft',
        ]);
    }

    public function test_exam_committee_enters_first_trial_final_exam_and_submits_passing_mark()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'prefinal_mark' => 42,
            'final_mark' => 0,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'first',
                'score' => 38,
            ])
            ->assertRedirect(route('marks.submission-queue'));

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'first_trial_final_exam' => 38,
            'second_trial_final_exam' => null,
            'final_exam' => 38,
            'final_mark' => 80,
            'submission_status' => 'submitted',
            'final_exam_entered_by' => $this->committee->id,
        ]);
        $this->assertDatabaseHas('mark_score_histories', [
            'mark_id' => $mark->id,
            'actor_id' => $this->committee->id,
            'trial' => 'first',
            'previous_score' => null,
            'new_score' => 38,
        ]);
    }

    public function test_final_exam_entry_cannot_overwrite_a_submitted_mark()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'prefinal_mark' => 40,
            'first_trial_final_exam' => 30,
            'final_mark' => 70,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'first',
                'score' => 35,
            ])
            ->assertSessionHasErrors('score');

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'first_trial_final_exam' => 30,
            'submission_status' => 'submitted',
        ]);
    }

    public function test_examination_administrator_cannot_enter_final_exam_even_with_direct_permission()
    {
        $permission = Permission::where('name', 'marks.enter_final_exam')->firstOrFail();
        $this->admin->permissionOverrides()->attach($permission->id, ['effect' => 'grant']);

        $this->actingAs($this->admin)
            ->get(route('marks.final-exam.index'))
            ->assertForbidden();
    }

    public function test_closed_academic_year_blocks_final_exam_entry()
    {
        $this->semester->academicYear->update(['status' => 'closed']);
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'prefinal_mark' => 42,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'first',
                'score' => 38,
            ])
            ->assertSessionHasErrors('mark_ids');

        $this->assertNull($mark->fresh()->first_trial_final_exam);
    }

    public function test_final_exam_enterer_cannot_approve_the_same_mark()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'prefinal_mark' => 42,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)->post(route('marks.final-exam.store'), [
            'mark_id' => $mark->id,
            'trial' => 'first',
            'score' => 38,
        ]);

        $this->actingAs($this->committee)
            ->postJson(route('marks.approve'), ['mark_ids' => [$mark->id]])
            ->assertUnprocessable();

        $this->actingAs($this->admin)
            ->postJson(route('marks.approve'), ['mark_ids' => [$mark->id]])
            ->assertOk();

        $this->assertSame('approved', $mark->fresh()->submission_status);
    }

    public function test_queue_batch_is_atomic_when_one_mark_has_invalid_relationships()
    {
        $validMark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'submission_status' => 'submitted',
        ]);
        $invalidStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
        ]);
        $invalidMark = Mark::factory()->create([
            'student_id' => $invalidStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => null,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('marks.approve'), ['mark_ids' => [$validMark->id, $invalidMark->id]])
            ->assertUnprocessable();

        $this->assertSame('submitted', $validMark->fresh()->submission_status);
        $this->assertSame('submitted', $invalidMark->fresh()->submission_status);
    }

    public function test_closed_academic_year_blocks_queue_transitions()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'submission_status' => 'submitted',
        ]);
        $this->semester->academicYear->update(['status' => 'archived']);

        $this->actingAs($this->admin)
            ->postJson(route('marks.approve'), ['mark_ids' => [$mark->id]])
            ->assertUnprocessable();

        $this->assertSame('submitted', $mark->fresh()->submission_status);
    }

    public function test_examination_users_cannot_mutate_marks_outside_their_organization_scope()
    {
        $otherDepartment = Department::factory()->create();
        $otherCourse = Course::factory()->create(['department_id' => $otherDepartment->id]);
        $otherStudent = Student::factory()->create([
            'university_id' => $otherDepartment->university_id,
            'department_id' => $otherDepartment->id,
        ]);
        $otherSemester = Semester::create([
            'university_id' => $otherDepartment->university_id,
            'name' => 'Current Semester',
            'academic_year' => '2026/2027',
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $otherSemester->id,
            'section_code' => 'A',
            'capacity' => 40,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'course_section_id' => $otherSection->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ]);
        $outsideMark = Mark::factory()->create([
            'student_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'course_section_id' => $otherSection->id,
            'prefinal_mark' => 40,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $outsideMark->id,
                'trial' => 'first',
                'score' => 35,
            ])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->postJson(route('marks.approve'), ['mark_ids' => [$outsideMark->id]])
            ->assertUnprocessable();

        $this->assertDatabaseHas('marks', [
            'id' => $outsideMark->id,
            'first_trial_final_exam' => null,
            'submission_status' => 'submitted',
        ]);
    }

    public function test_exam_committee_enters_second_trial_only_after_first_trial_failure()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 22,
            'final_mark' => 0,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'first',
                'score' => 20,
            ])
            ->assertRedirect(route('marks.submission-queue'));

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'first_trial_final_exam' => 20,
            'final_mark' => 42,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->get(route('marks.final-exam.course', ['course' => $this->course]))
            ->assertOk()
            ->assertSee('Second trial active')
            ->assertSee('name="trial" value="second"', false)
            ->assertSee('data-final-score-input', false)
            ->assertDontSee('>Save<', false);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'second',
                'score' => 35,
            ])
            ->assertRedirect(route('marks.submission-queue'));

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'first_trial_final_exam' => 20,
            'second_trial_final_exam' => 35,
            'final_exam' => 35,
            'final_mark' => 57,
            'submission_status' => 'submitted',
        ]);
    }

    public function test_second_trial_is_rejected_when_first_trial_passed()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 45,
            'first_trial_final_exam' => 20,
            'final_exam' => 20,
            'final_mark' => 65,
            'submission_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->from(route('marks.submission-queue'))
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'second',
                'score' => 30,
            ])
            ->assertSessionHasErrors('score');

        $this->assertDatabaseMissing('marks', [
            'id' => $mark->id,
            'second_trial_final_exam' => 30,
        ]);
    }

    public function test_final_exam_entry_page_filters_by_academic_hierarchy()
    {
        $this->course->update(['code' => 'MATH301', 'name' => 'Advanced Math']);
        $college = College::create([
            'university_id' => $this->department->university_id,
            'name' => 'Engineering College',
            'code' => 'ENG',
        ]);
        $this->department->update(['college_id' => $college->id]);
        $semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Spring',
            'academic_year' => '2027/2028',
        ]);
        $otherSemester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacher = Teacher::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'staff_id' => 'FEE-100',
            'full_name' => 'Final Entry Teacher',
            'email' => 'final.entry.teacher@example.com',
            'status' => 'Active',
        ]);
        $section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 3',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $hiddenSection = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $otherSemester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 2',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $visibleStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'full_name' => 'Visible Final Entry Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'full_name' => 'Hidden Final Entry Student',
        ]);

        Mark::factory()->create([
            'student_id' => $visibleStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $section->id,
            'prefinal_mark' => 34,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);
        Mark::factory()->create([
            'student_id' => $hiddenStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $hiddenSection->id,
            'prefinal_mark' => 36,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($this->committee)
            ->get(route('marks.final-exam.index', [
                'academic_year' => '2027/2028',
                'college_id' => $college->id,
                'department_id' => $this->department->id,
                'stage' => 'Third Stage',
                'semester_id' => $semester->id,
            ]))
            ->assertOk()
            ->assertSee('MATH301 - Advanced Math')
            ->assertSee('/marks/final-exam/courses/'.$this->course->id, false)
            ->assertSee('Courses Waiting for Final Exam Entry')
            ->assertDontSee('Visible Final Entry Student');

        $this->actingAs($this->committee)
            ->get(route('marks.final-exam.course', [
                'course' => $this->course,
                'academic_year' => '2027/2028',
                'college_id' => $college->id,
                'department_id' => $this->department->id,
                'stage' => 'Third Stage',
                'semester_id' => $semester->id,
                'q' => 'Visible',
            ]))
            ->assertOk()
            ->assertSee('Visible Final Entry Student')
            ->assertSee('Engineering College')
            ->assertSee('Stage 3')
            ->assertSee('Spring / Group A')
            ->assertDontSee('Hidden Final Entry Student');
    }

    public function test_department_scoped_exam_user_sees_only_own_final_exam_entries()
    {
        $this->course->update(['code' => 'SCP101', 'name' => 'Scoped Course']);
        $firstCollege = College::create([
            'university_id' => $this->department->university_id,
            'name' => 'Scoped College',
            'code' => 'SCP',
        ]);
        $this->department->update(['college_id' => $firstCollege->id]);
        $secondDepartment = Department::factory()->create([
            'university_id' => $this->department->university_id,
        ]);
        $secondCollege = College::create([
            'university_id' => $this->department->university_id,
            'name' => 'Other College',
            'code' => 'OTH',
        ]);
        $secondDepartment->update(['college_id' => $secondCollege->id]);
        $semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $firstSection = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
        ]);
        $otherCourse = Course::factory()->create(['department_id' => $secondDepartment->id]);
        $secondSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $semester->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 1',
            'capacity' => 30,
        ]);
        $visibleStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'full_name' => 'Scoped Final Entry Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $secondDepartment->university_id,
            'department_id' => $secondDepartment->id,
            'full_name' => 'Outside Final Entry Student',
        ]);

        Mark::factory()->create([
            'student_id' => $visibleStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $firstSection->id,
            'prefinal_mark' => 40,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);
        Mark::factory()->create([
            'student_id' => $hiddenStudent->id,
            'course_id' => $otherCourse->id,
            'course_section_id' => $secondSection->id,
            'prefinal_mark' => 42,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);

        $scopedUser = User::factory()->create([
            'university_id' => $this->department->university_id,
            'college_id' => $firstCollege->id,
            'department_id' => $this->department->id,
        ]);
        $scopedUser->roles()->attach($this->getRole('examination_committee'));

        $this->actingAs($scopedUser)
            ->get(route('marks.final-exam.index'))
            ->assertOk()
            ->assertSee('SCP101 - Scoped Course')
            ->assertDontSee($otherCourse->code.' - '.$otherCourse->name)
            ->assertDontSee('Scoped Final Entry Student')
            ->assertDontSee('Outside Final Entry Student');
    }

    public function test_final_exam_entry_save_returns_to_filtered_subpage()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'prefinal_mark' => 45,
            'final_mark' => 0,
            'submission_status' => 'draft',
        ]);
        $redirectTo = route('marks.final-exam.course', ['course' => $this->course, 'academic_year' => '2026/2027']);

        $this->actingAs($this->committee)
            ->post(route('marks.final-exam.store'), [
                'mark_id' => $mark->id,
                'trial' => 'first',
                'score' => 25,
                'redirect_to' => $redirectTo,
            ])
            ->assertRedirect($redirectTo);

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'first_trial_final_exam' => 25,
            'submission_status' => 'submitted',
        ]);
    }

    public function test_admin_can_approve_marks()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/marks/approve', [
            'mark_ids' => [$mark->id],
            'notes' => 'Looks good',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'submission_status' => 'approved',
            'reviewer_notes' => 'Looks good',
        ]);
    }

    public function test_admin_can_reject_marks()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/marks/reject', [
            'mark_ids' => [$mark->id],
            'notes' => 'Please recheck',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'submission_status' => 'rejected',
            'reviewer_notes' => 'Please recheck',
        ]);
    }

    public function test_admin_can_publish_marks()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson('/marks/publish', [
            'mark_ids' => [$mark->id],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'visibility_status' => 'published',
        ]);
    }

    public function test_admin_can_approve_marks_from_queue_form()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'submitted',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/marks/approve', [
                'mark_ids' => json_encode([$mark->id]),
                'notes' => 'Approved from queue',
            ]);

        $response->assertRedirect(route('marks.submission-queue'));
        $response->assertSessionHas('success', 'Approved 1 marks');

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'submission_status' => 'approved',
            'reviewer_notes' => 'Approved from queue',
        ]);
    }

    public function test_admin_can_publish_marks_from_queue_form()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/marks/publish', [
                'mark_ids' => json_encode([$mark->id]),
            ]);

        $response->assertRedirect(route('marks.submission-queue'));
        $response->assertSessionHas('success', 'Published 1 marks');

        $this->assertDatabaseHas('marks', [
            'id' => $mark->id,
            'visibility_status' => 'published',
        ]);
    }

    public function test_mark_queue_filters_and_shows_class_context()
    {
        $semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacher = Teacher::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'staff_id' => 'MQ-100',
            'full_name' => 'Dr. Queue Teacher',
            'email' => 'queue.teacher@example.com',
            'status' => 'Active',
        ]);
        $section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'C',
            'grade_level' => 'Stage 2',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $visibleStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'full_name' => 'Filtered Queue Student',
        ]);
        $hiddenStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'full_name' => 'Hidden Queue Filter Student',
        ]);

        Mark::factory()->create([
            'student_id' => $visibleStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $section->id,
            'final_mark' => 91,
            'prefinal_mark' => 41,
            'submission_status' => 'submitted',
        ]);
        Mark::factory()->create([
            'student_id' => $hiddenStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $section->id,
            'final_mark' => 66,
            'submission_status' => 'submitted',
        ]);

        $this->actingAs($this->admin)
            ->get(route('marks.submission-queue', [
                'q' => 'Filtered',
                'stage' => 'Second Stage',
                'semester_id' => $semester->id,
                'course_id' => $this->course->id,
                'teacher_id' => $teacher->id,
                'submission_status' => 'submitted',
            ]))
            ->assertOk()
            ->assertSee('Filtered Queue Student')
            ->assertSee('Group C')
            ->assertSee('Stage 2')
            ->assertSee('Dr. Queue Teacher')
            ->assertDontSee('Hidden Queue Filter Student');
    }

    public function test_admin_can_approve_and_reject_under_review_marks()
    {
        $rejectStudent = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
        ]);
        Enrollment::create([
            'student_id' => $rejectStudent->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ]);
        $approveMark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'under_review',
        ]);
        $rejectMark = Mark::factory()->create([
            'student_id' => $rejectStudent->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 72,
            'submission_status' => 'under_review',
        ]);

        $this->actingAs($this->admin)
            ->post(route('marks.approve'), [
                'mark_ids' => json_encode([$approveMark->id]),
                'notes' => 'Approved under review',
            ])
            ->assertRedirect(route('marks.submission-queue'));

        $this->assertDatabaseHas('marks', [
            'id' => $approveMark->id,
            'submission_status' => 'approved',
            'reviewer_notes' => 'Approved under review',
        ]);

        $this->actingAs($this->admin)
            ->post(route('marks.reject'), [
                'mark_ids' => json_encode([$rejectMark->id]),
                'notes' => 'Rejected under review',
            ])
            ->assertRedirect(route('marks.submission-queue'));

        $this->assertDatabaseHas('marks', [
            'id' => $rejectMark->id,
            'submission_status' => 'rejected',
            'reviewer_notes' => 'Rejected under review',
        ]);
    }

    public function test_non_teacher_cannot_submit_marks()
    {
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/marks/submit', [
            'mark_ids' => [$mark->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_submission_requires_final_mark()
    {
        // Marks with submission_status already 'submitted' or with 0 final_mark won't be updated
        $mark = Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'final_mark' => 85,
            'submission_status' => 'submitted', // Already submitted, can't re-submit
        ]);

        $service = new MarkSubmissionService;
        $result = $service->submitMarks([$mark->id]);

        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['failed']);
    }
}
