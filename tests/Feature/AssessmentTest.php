<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentTest extends TestCase
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

    private function makeAcademicAdmin(): User
    {
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $permission = Permission::create(['name' => 'marks.view', 'display_name' => 'View marks']);
        $role->permissions()->attach($permission);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        return $admin;
    }

    private function makeStudentUser(Student $student): User
    {
        $role = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student access',
        ]);

        $user = User::factory()->create([
            'name' => $student->full_name,
            'email' => $student->email,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacherUser(): User
    {
        $role = Role::create([
            'name' => 'teacher',
            'display_name' => 'Instructor',
            'description' => 'Teacher access',
        ]);
        $permission = Permission::create([
            'name' => 'lms.create_assignment',
            'display_name' => 'Create assignments',
            'description' => 'Create and edit assessments',
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'email' => 'teacher.assessment@example.com',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSetup(): array
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-20',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'CS301',
            'name' => 'Algorithms',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'T-200',
            'full_name' => 'Dr. Mina Karim',
            'email' => 'teacher.assessment@example.com',
            'status' => 'Active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'S-200',
            'full_name' => 'Assessment Student',
            'email' => 'assessment.student@example.com',
            'status' => 'Active',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 3',
            'capacity' => 30,
            'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-09-01',
        ]);

        return compact('course', 'section', 'student');
    }

    public function test_admin_can_create_assessment_item_with_grade_weight(): void
    {
        Storage::fake('public');

        $admin = $this->makeSuperAdmin();
        $setup = $this->makeSetup();
        $file = UploadedFile::fake()->create('midterm-instructions.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('assessment-items.store'), [
                'course_section_id' => $setup['section']->id,
                'title' => 'Midterm Exam',
                'type' => 'exam',
                'description' => 'Midterm assessment',
                'max_score' => 100,
                'weight_percent' => 30,
                'status' => 'published',
                'allow_submissions' => 1,
                'instruction_file' => $file,
            ])
            ->assertRedirect(route('assessments.index'));

        $assessment = AssessmentItem::where('title', 'Midterm Exam')->firstOrFail();

        $this->assertDatabaseHas('assessment_items', [
            'course_section_id' => $setup['section']->id,
            'title' => 'Midterm Exam',
            'type' => 'exam',
            'weight_percent' => 30,
            'status' => 'published',
        ]);
        Storage::disk('public')->assertExists($assessment->instruction_file_path);
    }

    public function test_admin_can_add_rubric(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeSetup();
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'created_by' => $admin->id,
            'title' => 'Project',
            'type' => 'project',
            'max_score' => 100,
            'weight_percent' => 20,
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->post(route('assessment-items.rubric.store', $assessment), [
                'title' => 'Project Rubric',
                'criteria_text' => "Correctness|60\nClarity|40",
            ])
            ->assertRedirect(route('assessments.index'));

        $this->assertDatabaseHas('rubrics', [
            'assessment_item_id' => $assessment->id,
            'title' => 'Project Rubric',
            'total_points' => 100,
        ]);
    }

    public function test_teacher_can_edit_assessment_item(): void
    {
        Storage::fake('public');

        $teacher = $this->makeTeacherUser();
        $setup = $this->makeSetup();
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'created_by' => $teacher->id,
            'title' => 'Old Homework',
            'type' => 'assignment',
            'description' => 'Old details',
            'max_score' => 50,
            'weight_percent' => 10,
            'status' => 'draft',
            'allow_submissions' => true,
        ]);
        $file = UploadedFile::fake()->create('updated-instructions.pdf', 80, 'application/pdf');

        $this->actingAs($teacher)
            ->get(route('assessment-items.edit', $assessment))
            ->assertOk()
            ->assertSee('Edit Assessment');

        $this->actingAs($teacher)
            ->patch(route('assessment-items.update', $assessment), [
                'course_section_id' => $setup['section']->id,
                'title' => 'Updated Homework',
                'type' => 'project',
                'description' => 'Updated details',
                'max_score' => 75,
                'weight_percent' => 15,
                'status' => 'published',
                'allow_submissions' => 1,
                'instruction_file' => $file,
            ])
            ->assertRedirect(route('assessments.index'));

        $assessment->refresh();

        $this->assertSame('Updated Homework', $assessment->title);
        $this->assertSame('project', $assessment->type);
        $this->assertSame('published', $assessment->status);
        $this->assertSame('75.00', $assessment->max_score);
        $this->assertSame('15.00', $assessment->weight_percent);
        Storage::disk('public')->assertExists($assessment->instruction_file_path);
    }

    public function test_enrolled_student_can_submit_published_assessment(): void
    {
        Storage::fake('public');

        $teacherUser = $this->makeTeacherUser();
        $setup = $this->makeSetup();
        $studentUser = $this->makeStudentUser($setup['student']);
        $file = UploadedFile::fake()->create('solution.docx', 90, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Homework 1',
            'type' => 'assignment',
            'max_score' => 50,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($studentUser)
            ->post(route('assessment-items.submissions.store', $assessment), [
                'content' => 'My completed solution.',
                'submission_file' => $file,
            ])
            ->assertRedirect(route('assessments.index'));

        $submission = AssessmentSubmission::where('assessment_item_id', $assessment->id)->firstOrFail();

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_item_id' => $assessment->id,
            'student_id' => $setup['student']->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $teacherUser->id,
            'type' => 'assessment_submission',
            'title' => 'New submission: Homework 1',
        ]);
        Storage::disk('public')->assertExists($submission->attachment_path);
    }

    public function test_enrolled_student_can_open_assessments_page(): void
    {
        $setup = $this->makeSetup();
        $studentUser = $this->makeStudentUser($setup['student']);

        AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Visible Student Assessment',
            'type' => 'assignment',
            'max_score' => 50,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($studentUser)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('Visible Student Assessment');
    }

    public function test_late_assessment_submission_is_blocked_after_due_date(): void
    {
        $setup = $this->makeSetup();
        $studentUser = $this->makeStudentUser($setup['student']);
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Closed Homework',
            'type' => 'assignment',
            'max_score' => 50,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
            'due_at' => now()->subMinute(),
        ]);

        $this->actingAs($studentUser)
            ->post(route('assessment-items.submissions.store', $assessment), [
                'content' => 'Late answer',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assessment_submissions', [
            'assessment_item_id' => $assessment->id,
            'student_id' => $setup['student']->id,
        ]);
    }

    public function test_non_enrolled_student_cannot_submit_assessment(): void
    {
        $setup = $this->makeSetup();
        $otherStudent = Student::create([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['student']->department_id,
            'student_id' => 'S-201',
            'full_name' => 'Other Student',
            'email' => 'other.student@example.com',
            'status' => 'Active',
        ]);
        $studentUser = $this->makeStudentUser($otherStudent);
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Homework 1',
            'type' => 'assignment',
            'max_score' => 50,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($studentUser)
            ->post(route('assessment-items.submissions.store', $assessment), [
                'content' => 'Trying to submit.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assessment_submissions', [
            'assessment_item_id' => $assessment->id,
            'student_id' => $otherStudent->id,
        ]);
    }

    public function test_admin_can_grade_submission(): void
    {
        $admin = $this->makeSuperAdmin();
        $setup = $this->makeSetup();
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Quiz 1',
            'type' => 'quiz',
            'max_score' => 20,
            'weight_percent' => 5,
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        $submission = AssessmentSubmission::create([
            'assessment_item_id' => $assessment->id,
            'student_id' => $setup['student']->id,
            'content' => 'Answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($admin)
            ->patch(route('assessment-submissions.grade', $submission), [
                'score' => 18,
                'feedback' => 'Good work',
            ])
            ->assertRedirect(route('assessments.index'));

        $this->assertDatabaseHas('assessment_submissions', [
            'id' => $submission->id,
            'score' => 18,
            'feedback' => 'Good work',
            'status' => 'graded',
            'graded_by' => $admin->id,
        ]);
    }

    public function test_teacher_can_see_student_submission_answer(): void
    {
        $teacher = $this->makeTeacherUser();
        $setup = $this->makeSetup();
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'created_by' => $teacher->id,
            'title' => 'Essay Answer',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 20,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        AssessmentSubmission::create([
            'assessment_item_id' => $assessment->id,
            'student_id' => $setup['student']->id,
            'content' => 'This is the student written answer.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('This is the student written answer.');
    }

    public function test_teacher_assessments_are_grouped_by_course(): void
    {
        $teacher = $this->makeTeacherUser();
        $setup = $this->makeSetup();
        $secondCourse = Course::create([
            'department_id' => $setup['course']->department_id,
            'semester_id' => $setup['course']->semester_id,
            'code' => 'CS302',
            'name' => 'Databases',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $secondSection = CourseSection::create([
            'course_id' => $secondCourse->id,
            'semester_id' => $setup['section']->semester_id,
            'teacher_id' => $setup['section']->teacher_id,
            'section_code' => 'B',
            'capacity' => 30,
            'status' => 'active',
        ]);

        AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'created_by' => $teacher->id,
            'title' => 'Algorithm Homework',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        AssessmentItem::create([
            'course_section_id' => $secondSection->id,
            'created_by' => $teacher->id,
            'title' => 'Database Exam',
            'type' => 'exam',
            'max_score' => 100,
            'weight_percent' => 30,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('CS301 - Algorithms')
            ->assertSee('CS302 - Databases')
            ->assertSee('Algorithm Homework')
            ->assertSee('Database Exam');
    }

    public function test_teacher_cannot_access_unassigned_assessments(): void
    {
        $teacher = $this->makeTeacherUser();
        $setup = $this->makeSetup();
        $otherTeacher = Teacher::create([
            'university_id' => $setup['student']->university_id,
            'department_id' => $setup['student']->department_id,
            'staff_id' => 'T-999',
            'full_name' => 'Other Instructor',
            'email' => 'other.teacher@example.com',
            'status' => 'Active',
        ]);
        $otherCourse = Course::create([
            'department_id' => $setup['course']->department_id,
            'semester_id' => $setup['course']->semester_id,
            'code' => 'CS999',
            'name' => 'Private Course',
            'credits' => 3,
            'semester' => 'Fall 2026',
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $setup['section']->semester_id,
            'teacher_id' => $otherTeacher->id,
            'section_code' => 'Z',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $otherAssessment = AssessmentItem::create([
            'course_section_id' => $otherSection->id,
            'created_by' => $teacher->id,
            'title' => 'Unassigned Assessment',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertDontSee('Unassigned Assessment');

        $this->actingAs($teacher)
            ->get(route('assessment-items.edit', $otherAssessment))
            ->assertForbidden();
    }

    public function test_academic_admin_uses_read_only_assessment_hierarchy(): void
    {
        $setup = $this->makeSetup();
        $department = $setup['course']->department;
        $college = College::create([
            'university_id' => $department->university_id,
            'name' => 'Engineering College',
            'code' => 'ENG',
        ]);
        $department->update(['college_id' => $college->id]);
        $assessment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Admin Oversight Assessment',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 20,
            'status' => 'published',
            'allow_submissions' => true,
            'due_at' => now()->subDay(),
        ]);
        $assessment->rubric()->create([
            'title' => 'Oversight Rubric',
            'criteria' => [['label' => 'Accuracy', 'points' => 100]],
            'total_points' => 100,
        ]);
        $submission = AssessmentSubmission::create([
            'assessment_item_id' => $assessment->id,
            'student_id' => $setup['student']->id,
            'content' => 'Student answer',
            'submitted_at' => now()->subDays(2),
            'status' => 'submitted',
        ]);
        $admin = $this->makeAcademicAdmin();

        $this->actingAs($admin)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('Assessment Analytics')
            ->assertSee('Colleges')
            ->assertSee('Engineering College')
            ->assertSee('Total assessments')
            ->assertSee('Status distribution')
            ->assertSee('Submission pipeline')
            ->assertSee('Assessment mix')
            ->assertSee('Needs attention')
            ->assertSee('Upcoming deadlines')
            ->assertSee('Admin Oversight Assessment')
            ->assertDontSee('Add Assessment');

        $this->actingAs($admin)
            ->get(route('assessments.index', ['college_id' => $college->id]))
            ->assertOk()
            ->assertSee('Departments')
            ->assertSee('Computer Science');

        $this->actingAs($admin)
            ->get(route('assessments.index', ['college_id' => $college->id, 'department_id' => $department->id]))
            ->assertOk()
            ->assertSee('Stages')
            ->assertSee('Stage 3');

        $this->actingAs($admin)
            ->get(route('assessments.index', ['college_id' => $college->id, 'department_id' => $department->id, 'stage' => 'Stage 3']))
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Algorithms');

        $classUrl = route('assessments.index', [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'stage' => 'Stage 3',
            'section_id' => $setup['section']->id,
        ]);
        $this->actingAs($admin)
            ->get($classUrl)
            ->assertOk()
            ->assertSee('Admin Oversight Assessment')
            ->assertSee('Oversight Rubric')
            ->assertSee('Ungraded')
            ->assertSee('100.0%')
            ->assertSee('Past due')
            ->assertDontSee('Edit Assessment')
            ->assertDontSee('Save Rubric')
            ->assertDontSee('Student answer')
            ->assertDontSee(route('assessment-submissions.grade', $submission), false);

        $this->actingAs($admin)
            ->post(route('assessment-items.store'), [
                'course_section_id' => $setup['section']->id,
                'title' => 'Blocked',
                'type' => 'assignment',
                'max_score' => 100,
                'weight_percent' => 10,
                'status' => 'draft',
            ])
            ->assertForbidden();
        $this->actingAs($admin)->get(route('assessment-items.edit', $assessment))->assertForbidden();
        $this->actingAs($admin)->patch(route('assessment-submissions.grade', $submission), ['score' => 80])->assertForbidden();
    }

    public function test_admin_assessment_analytics_filters_drill_links_and_exports_csv(): void
    {
        $setup = $this->makeSetup();
        $department = $setup['course']->department;
        $college = College::create([
            'university_id' => $department->university_id,
            'name' => 'Engineering College',
            'code' => 'ENG',
        ]);
        $department->update(['college_id' => $college->id]);
        $assignment = AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Upcoming Practical',
            'type' => 'assignment',
            'max_score' => 100,
            'weight_percent' => 20,
            'status' => 'published',
            'allow_submissions' => true,
            'due_at' => now()->addDays(3),
        ]);
        AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Draft Exam Hidden',
            'type' => 'exam',
            'max_score' => 100,
            'weight_percent' => 30,
            'status' => 'draft',
            'allow_submissions' => true,
            'due_at' => now()->addDays(5),
        ]);
        AssessmentSubmission::create([
            'assessment_item_id' => $assignment->id,
            'student_id' => $setup['student']->id,
            'content' => 'Filtered answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        $admin = $this->makeAcademicAdmin();
        $query = [
            'college_id' => $college->id,
            'department_id' => $department->id,
            'stage' => '3',
            'section_id' => $setup['section']->id,
            'semester_id' => $setup['section']->semester_id,
            'course_id' => $setup['section']->course_id,
            'teacher_id' => $setup['section']->teacher_id,
            'type' => 'assignment',
            'status' => 'published',
            'q' => 'Upcoming',
        ];
        $this->actingAs($admin)
            ->get(route('assessments.index', $query))
            ->assertOk()
            ->assertSee('Filters applied')
            ->assertSee('Export CSV')
            ->assertSee('Upcoming Practical')
            ->assertSee('assessment_id='.$assignment->id, false)
            ->assertDontSee('Draft Exam Hidden');

        $this->actingAs($admin)
            ->get(route('assessments.index', [
                'college_id' => $college->id,
                'department_id' => $department->id,
                'stage' => 'Third Stage',
            ]))
            ->assertOk()
            ->assertSee('Classes')
            ->assertSee('Algorithms');

        $export = $this->actingAs($admin)->get(route('assessments.export', $query));
        $export->assertOk();
        $export->assertDownload();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Upcoming Practical', $csv);
        $this->assertStringNotContainsString('Draft Exam Hidden', $csv);
    }

    public function test_lms_admin_does_not_receive_inaccessible_results_overview_link(): void
    {
        $role = Role::create(['name' => 'lms_administrator', 'display_name' => 'E-Learning Administrator']);
        $permission = Permission::create(['name' => 'lms.view', 'display_name' => 'View LMS']);
        $role->permissions()->attach($permission);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $this->actingAs($admin)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('Assessments')
            ->assertDontSee(route('exams'), false);
    }

    public function test_super_admin_opens_assessment_analytics_instead_of_management_workspace(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->makeSetup();

        $this->actingAs($admin)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertSee('Assessment Analytics')
            ->assertSee('Status distribution')
            ->assertSee('Submission pipeline')
            ->assertDontSee('Add Assessment')
            ->assertDontSee('Weighted Gradebook');
    }
}
