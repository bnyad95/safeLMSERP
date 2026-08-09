<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Course $course;

    private Student $student;

    private CourseSection $section;

    private User $examAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::factory()->create();
        $this->course = Course::factory()->create([
            'department_id' => $this->department->id,
            'code' => 'CSV101',
        ]);
        $this->student = Student::factory()->create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'student_id' => 'CSV-1',
        ]);
        $semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Current Semester',
            'academic_year' => '2026/2027',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $semester->id,
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

        $role = Role::create(['name' => 'examination_administrator', 'display_name' => 'Examination Administrator']);
        foreach (['marks.view', 'marks.review', 'marks.approve', 'marks.publish'] as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName], ['display_name' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $this->examAdmin = User::factory()->create(['university_id' => $this->department->university_id]);
        $this->examAdmin->roles()->attach($role);
    }

    private function csvFile(string $finalMark): UploadedFile
    {
        $content = "student_id,course_code,section_code,assignments,quizzes,midterm,practical,final_exam,final_mark\n"
            ."CSV-1,CSV101,A,0,0,0,0,0,{$finalMark}\n";

        return UploadedFile::fake()->createWithContent('marks.csv', $content);
    }

    public function test_csv_import_can_create_a_new_draft_mark(): void
    {
        $this->actingAs($this->examAdmin)
            ->post(route('integrations.import.marks'), ['csv_file' => $this->csvFile('75')])
            ->assertRedirect(route('integrations.index'))
            ->assertSessionHas('success', 'Import complete: 1 marks imported, 0 failed.');

        $this->assertDatabaseHas('marks', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 75,
        ]);
    }

    public function test_csv_import_can_update_a_draft_mark(): void
    {
        Mark::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 40,
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($this->examAdmin)
            ->post(route('integrations.import.marks'), ['csv_file' => $this->csvFile('60')])
            ->assertSessionHas('success', 'Import complete: 1 marks imported, 0 failed.');

        $this->assertDatabaseHas('marks', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 60,
        ]);
    }

    public function test_csv_import_cannot_overwrite_a_published_mark(): void
    {
        $mark = Mark::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 40,
            'first_trial_final_exam' => 45,
            'final_mark' => 85,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($this->examAdmin)
            ->post(route('integrations.import.marks'), ['csv_file' => $this->csvFile('12')])
            ->assertSessionHas('success', 'Import complete: 0 marks imported, 1 failed.');

        $this->assertSame(85.0, (float) $mark->fresh()->final_mark);
        $this->assertSame('published', $mark->fresh()->visibility_status);
    }

    public function test_csv_import_cannot_overwrite_a_submitted_mark(): void
    {
        $mark = Mark::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'final_mark' => 70,
            'submission_status' => 'submitted',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($this->examAdmin)
            ->post(route('integrations.import.marks'), ['csv_file' => $this->csvFile('10')])
            ->assertSessionHas('success', 'Import complete: 0 marks imported, 1 failed.');

        $this->assertSame(70.0, (float) $mark->fresh()->final_mark);
        $this->assertSame('submitted', $mark->fresh()->submission_status);
    }
}
