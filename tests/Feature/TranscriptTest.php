<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use App\Services\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::factory()->create();
        $this->student = Student::factory()->create([
            'department_id' => $this->department->id,
        ]);
        $this->user = User::factory()->create(['email' => $this->student->email]);

        $this->createRoles();
        $this->user->roles()->attach($this->getRole('student'));
    }

    private function createRoles()
    {
        if (! Role::where('name', 'student')->exists()) {
            Role::create(['name' => 'student', 'display_name' => 'Student']);
        }
        if (! Role::where('name', 'administrator')->exists()) {
            Role::create(['name' => 'administrator', 'display_name' => 'Administrator']);
        }
    }

    private function getRole($name)
    {
        return Role::where('name', $name)->first();
    }

    public function test_student_can_view_own_transcript()
    {
        $this->actingAs($this->user);

        $response = $this->get("/transcripts/{$this->student->id}");

        $response->assertSuccessful();
        $response->assertViewIs('transcripts.view');
    }

    public function test_student_can_download_transcript()
    {
        $this->actingAs($this->user);

        $response = $this->get("/transcripts/{$this->student->id}/download");

        $response->assertSuccessful();
    }

    public function test_non_owner_student_cannot_view_transcript()
    {
        $other_student = Student::factory()->create();
        $other_user = User::factory()->create(['email' => $other_student->email]);
        $other_user->roles()->attach($this->getRole('student'));

        $this->actingAs($other_user);

        $response = $this->get("/transcripts/{$this->student->id}");

        $response->assertStatus(403);
    }

    private function userWithMarksView(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => str($roleName)->replace('_', ' ')->title()]);
        $permission = Permission::firstOrCreate(['name' => 'marks.view'], ['display_name' => 'View marks']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_registrar_with_marks_view_can_see_transcript_within_scope(): void
    {
        $university = University::create(['name' => 'Transcript Scope University', 'code' => 'TSU']);
        $department = Department::factory()->create(['university_id' => $university->id]);
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $registrar = $this->userWithMarksView('registrar', [
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($registrar)
            ->get(route('transcripts.show', $student))
            ->assertOk();
    }

    public function test_department_scoped_user_cannot_view_transcript_outside_own_department(): void
    {
        $university = University::create(['name' => 'Transcript Outside University', 'code' => 'TOU']);
        $ownDepartment = Department::factory()->create(['university_id' => $university->id]);
        $otherDepartment = Department::factory()->create(['university_id' => $university->id]);
        $outsideStudent = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
        ]);
        $registrar = $this->userWithMarksView('registrar', [
            'university_id' => $university->id,
            'department_id' => $ownDepartment->id,
        ]);

        $this->actingAs($registrar)
            ->get(route('transcripts.show', $outsideStudent))
            ->assertNotFound();
    }

    public function test_user_with_marks_view_but_no_scope_match_cannot_view_transcript(): void
    {
        $university = University::create(['name' => 'Transcript No Scope University', 'code' => 'TNS']);
        $department = Department::factory()->create(['university_id' => $university->id]);
        $student = Student::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $registrar = $this->userWithMarksView('registrar');

        $this->actingAs($registrar)
            ->get(route('transcripts.show', $student))
            ->assertNotFound();
    }

    public function test_transcript_service_calculates_gpa()
    {
        $service = new TranscriptService;

        $course1 = Course::factory()->create(['department_id' => $this->department->id, 'credits' => 3]);
        $course2 = Course::factory()->create(['department_id' => $this->department->id, 'credits' => 6]);

        Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course1->id,
            'final_mark' => 90, // Grade A (4.0)
        ]);
        Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course2->id,
            'final_mark' => 80, // Grade B (3.5)
        ]);

        $marks = $this->student->marks()->get();
        $gpa = $service->calculateGPA($marks);

        $this->assertEquals(3.67, $gpa);
        $this->assertSame(9, $service->calculateTotalCredits($marks));
    }

    public function test_transcript_service_returns_letter_grade()
    {
        $service = new TranscriptService;

        $this->assertEquals('A', $service->getLetterGrade(90));
        $this->assertEquals('B', $service->getLetterGrade(80));
        $this->assertEquals('C', $service->getLetterGrade(70));
        $this->assertEquals('D', $service->getLetterGrade(60));
        $this->assertEquals('E', $service->getLetterGrade(50));
        $this->assertEquals('F', $service->getLetterGrade(40));
    }

    public function test_transcript_includes_only_published_marks()
    {
        $course1 = Course::factory()->create(['department_id' => $this->department->id, 'name' => 'Published Transcript Course']);
        $course2 = Course::factory()->create(['department_id' => $this->department->id, 'name' => 'Draft Transcript Course']);

        Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course1->id,
            'final_mark' => 90,
            'visibility_status' => 'published',
        ]);
        Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course2->id,
            'final_mark' => 80,
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($this->user);
        $response = $this->get("/transcripts/{$this->student->id}");

        $response->assertSuccessful()
            ->assertSee('Published Transcript Course')
            ->assertDontSee('Draft Transcript Course');
    }

    public function test_transcript_pdf_generation()
    {
        $service = new TranscriptService;
        $course = Course::factory()->create(['department_id' => $this->department->id]);

        Mark::factory()->create([
            'student_id' => $this->student->id,
            'course_id' => $course->id,
            'final_mark' => 85,
            'visibility_status' => 'published',
        ]);

        $pdf = $service->generateTranscript($this->student);

        $this->assertNotNull($pdf);
    }

    public function test_gpa_calculation_with_no_marks()
    {
        $service = new TranscriptService;
        $gpa = $service->calculateGPA([]);

        $this->assertEquals(0.0, $gpa);
    }

    public function test_zero_mark_counts_as_a_failed_attempt(): void
    {
        $service = new TranscriptService;
        $passedCourse = Course::factory()->create(['department_id' => $this->department->id, 'credits' => 3]);
        $failedCourse = Course::factory()->create(['department_id' => $this->department->id, 'credits' => 3]);
        $passed = Mark::factory()->create(['student_id' => $this->student->id, 'course_id' => $passedCourse->id, 'final_mark' => 90]);
        $failed = Mark::factory()->create(['student_id' => $this->student->id, 'course_id' => $failedCourse->id, 'final_mark' => 0]);
        $marks = collect([$passed->load('course'), $failed->load('course')]);

        $this->assertSame(2.0, $service->calculateGPA($marks));
        $this->assertSame(6, $service->calculateTotalCredits($marks));
        $this->assertSame(1, $service->calculateCompletedCourses($marks));
    }
}
