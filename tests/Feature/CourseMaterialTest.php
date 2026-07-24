<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\CourseMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->department = Department::factory()->create();
        $this->course = Course::factory()->create([
            'department_id' => $this->department->id,
        ]);
        $this->teacher = User::factory()->create(['email' => 'teacher@test.com']);
        $this->teacherRecord = Teacher::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'staff_id' => 'T-MAT-1',
            'full_name' => 'Material Teacher',
            'email' => $this->teacher->email,
            'status' => 'Active',
        ]);
        $this->semester = Semester::create([
            'university_id' => $this->department->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'teacher_id' => $this->teacherRecord->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);

        $this->createRoles();
        $this->teacher->roles()->attach($this->getRole('teacher'));
    }

    private function createRoles()
    {
        if (! Role::where('name', 'teacher')->exists()) {
            Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        }
        if (! Role::where('name', 'student')->exists()) {
            Role::create(['name' => 'student', 'display_name' => 'Student']);
        }
        if (! Role::where('name', 'department_administrator')->exists()) {
            Role::create(['name' => 'department_administrator', 'display_name' => 'Department Administrator']);
        }
    }

    private function getRole($name)
    {
        return Role::where('name', $name)->first();
    }

    public function test_teacher_can_upload_material()
    {
        $this->actingAs($this->teacher);

        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

        $response = $this->postJson("/courses/{$this->course->id}/materials", [
            'title' => 'Test Material',
            'description' => 'A test material',
            'file' => $file,
            'file_type' => 'pdf',
            'visibility' => 'draft',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('course_materials', [
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'title' => 'Test Material',
            'file_type' => 'pdf',
            'visibility' => 'draft',
        ]);
    }

    public function test_class_materials_do_not_include_another_sections_files()
    {
        $otherSection = CourseSection::create([
            'course_id' => $this->course->id,
            'semester_id' => $this->semester->id,
            'teacher_id' => $this->teacherRecord->id,
            'section_code' => 'B',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $classMaterial = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'uploaded_by' => $this->teacher->id,
            'title' => 'Group A Notes',
        ]);
        CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'course_section_id' => $otherSection->id,
            'uploaded_by' => $this->teacher->id,
            'title' => 'Group B Notes',
        ]);

        $this->actingAs($this->teacher)
            ->get(route('materials.index', ['course' => $this->course, 'section_id' => $this->section->id]))
            ->assertOk()
            ->assertSee($classMaterial->title)
            ->assertDontSee('Group B Notes');
    }

    public function test_browser_material_upload_redirects_to_materials_page()
    {
        $this->actingAs($this->teacher)
            ->post("/courses/{$this->course->id}/materials", [
                'title' => 'Browser Material',
                'description' => 'Uploaded from form',
                'file_type' => 'pdf',
                'visibility' => 'published',
            ])
            ->assertRedirect(route('materials.index', $this->course));

        $this->assertDatabaseHas('course_materials', [
            'course_id' => $this->course->id,
            'title' => 'Browser Material',
            'visibility' => 'published',
        ]);
    }

    public function test_teacher_can_publish_material()
    {
        $material = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'uploaded_by' => $this->teacher->id,
            'visibility' => 'draft',
        ]);

        $this->actingAs($this->teacher);

        $response = $this->postJson("/courses/{$this->course->id}/materials/{$material->id}/publish");

        $response->assertSuccessful();
        $this->assertDatabaseHas('course_materials', [
            'id' => $material->id,
            'visibility' => 'published',
        ]);
    }

    public function test_students_can_only_see_published_materials()
    {
        $published = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'visibility' => 'published',
        ]);
        $draft = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'visibility' => 'draft',
        ]);

        $student = User::factory()->create();
        $studentRecord = Student::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'student_id' => 'MAT-1001',
            'full_name' => 'Material Student',
            'email' => $student->email,
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $studentRecord->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        $student->roles()->attach($this->getRole('student'));

        $this->actingAs($student);
        $response = $this->get("/courses/{$this->course->id}/materials");

        $response->assertSuccessful()
            ->assertSee($published->title)
            ->assertDontSee($draft->title);
    }

    public function test_department_admin_can_view_and_download_class_materials()
    {
        Storage::disk('public')->put('course-materials/admin-draft.pdf', 'draft file');

        $material = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'course_section_id' => $this->section->id,
            'file_path' => 'course-materials/admin-draft.pdf',
            'file_type' => 'pdf',
            'visibility' => 'draft',
            'title' => 'Admin Draft Material',
        ]);

        $admin = User::factory()->create([
            'university_id' => $this->department->university_id,
            'college_id' => $this->department->college_id,
            'department_id' => $this->department->id,
        ]);
        $admin->roles()->attach($this->getRole('department_administrator'));

        $this->actingAs($admin)
            ->get(route('materials.index', ['course' => $this->course, 'section_id' => $this->section->id]))
            ->assertOk()
            ->assertSee('Admin Draft Material');

        $this->actingAs($admin)
            ->get(route('materials.download', ['course' => $this->course, 'material' => $material, 'section_id' => $this->section->id]))
            ->assertOk();
    }

    public function test_course_material_service_uploads_material()
    {
        Storage::fake('public');
        $service = new CourseMaterialService;

        $file = UploadedFile::fake()->create('test.pdf', 1024);

        $this->actingAs($this->teacher);
        $material = $service->uploadMaterial($this->course->id, [
            'title' => 'Test Material',
            'description' => 'Test',
            'file' => $file,
            'file_type' => 'pdf',
            'visibility' => 'draft',
        ]);

        $this->assertDatabaseHas('course_materials', [
            'id' => $material->id,
            'title' => 'Test Material',
        ]);
    }

    public function test_material_can_be_deleted()
    {
        $material = CourseMaterial::factory()->create([
            'course_id' => $this->course->id,
            'uploaded_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher);

        $response = $this->deleteJson("/courses/{$this->course->id}/materials/{$material->id}");

        $response->assertSuccessful();
        $this->assertSoftDeleted('course_materials', [
            'id' => $material->id,
        ]);
    }

    public function test_teacher_cannot_manage_unassigned_course_materials()
    {
        $otherCourse = Course::factory()->create([
            'department_id' => $this->department->id,
        ]);

        $this->actingAs($this->teacher)
            ->postJson("/courses/{$otherCourse->id}/materials", [
                'title' => 'No Access',
                'file_type' => 'pdf',
                'visibility' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_unenrolled_student_cannot_view_course_materials()
    {
        $student = User::factory()->create(['email' => 'outside@student.test']);
        $student->roles()->attach($this->getRole('student'));
        Student::create([
            'university_id' => $this->department->university_id,
            'department_id' => $this->department->id,
            'student_id' => 'MAT-9999',
            'full_name' => 'Outside Student',
            'email' => $student->email,
            'status' => 'Active',
        ]);

        $this->actingAs($student)
            ->get("/courses/{$this->course->id}/materials")
            ->assertForbidden();
    }
}
