<?php

namespace Tests\Feature;

use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\ClassStreamPost;
use App\Models\Course;
use App\Models\CourseMaterial;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private User $studentUser;

    private User $outsideUser;

    private CourseSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $this->teacherUser = User::factory()->create(['name' => 'Stream Teacher', 'email' => 'stream.teacher@example.com']);
        $this->teacherUser->roles()->attach($teacherRole);
        $this->studentUser = User::factory()->create(['name' => 'Stream Student', 'email' => 'stream.student@example.com']);
        $this->studentUser->roles()->attach($studentRole);
        $this->outsideUser = User::factory()->create(['name' => 'Outside Student', 'email' => 'outside.stream@example.com']);
        $this->outsideUser->roles()->attach($studentRole);

        $university = University::create(['name' => 'Stream University', 'code' => 'STR']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'STREAM-T1',
            'full_name' => 'Stream Teacher',
            'email' => $this->teacherUser->email,
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'STREAM101',
            'name' => 'Class Communication',
            'credits' => 3,
            'semester' => 'Fall',
        ]);
        $this->section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A',
            'capacity' => 30,
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STREAM-S1',
            'full_name' => 'Stream Student',
            'email' => $this->studentUser->email,
            'status' => 'Active',
        ]);
        Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STREAM-S2',
            'full_name' => 'Outside Student',
            'email' => $this->outsideUser->email,
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $this->section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
    }

    public function test_teacher_can_post_text_and_file_to_class_stream(): void
    {
        $response = $this->actingAs($this->teacherUser)->post(route('class-stream.posts.store', $this->section), [
            'body' => 'Please review the attached lesson notes.',
            'attachment' => UploadedFile::fake()->create('lesson-notes.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('teacher-dashboard', ['section_id' => $this->section->id, 'tab' => 'stream']));
        $post = ClassStreamPost::firstOrFail();
        $this->assertSame($this->section->id, $post->course_section_id);
        $this->assertSame('lesson-notes.pdf', $post->attachment_name);
        Storage::disk('local')->assertExists($post->attachment_path);

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', $this->section))
            ->assertOk()
            ->assertSee('Please review the attached lesson notes.')
            ->assertSee('lesson-notes.pdf');
    }

    public function test_enrolled_student_can_comment_and_toggle_reaction(): void
    {
        $post = ClassStreamPost::create([
            'course_section_id' => $this->section->id,
            'user_id' => $this->teacherUser->id,
            'body' => 'Welcome to the class.',
        ]);

        $this->actingAs($this->studentUser)
            ->post(route('class-stream.comments.store', [$this->section, $post]), ['body' => 'Thank you, teacher.'])
            ->assertRedirect(route('class-stream.show', $this->section));
        $this->assertDatabaseHas('class_stream_comments', ['class_stream_post_id' => $post->id, 'user_id' => $this->studentUser->id, 'body' => 'Thank you, teacher.']);

        $this->actingAs($this->studentUser)
            ->post(route('class-stream.reactions.toggle', [$this->section, $post]))
            ->assertRedirect(route('class-stream.show', $this->section));
        $this->assertDatabaseHas('class_stream_reactions', ['class_stream_post_id' => $post->id, 'user_id' => $this->studentUser->id]);

        $this->actingAs($this->studentUser)->post(route('class-stream.reactions.toggle', [$this->section, $post]));
        $this->assertDatabaseMissing('class_stream_reactions', ['class_stream_post_id' => $post->id, 'user_id' => $this->studentUser->id]);
    }

    public function test_academic_admin_can_read_stream_attachments_but_cannot_interact(): void
    {
        Storage::disk('local')->put('class-stream/oversight.pdf', 'oversight file');
        $post = ClassStreamPost::create([
            'course_section_id' => $this->section->id,
            'user_id' => $this->teacherUser->id,
            'body' => 'Review material',
            'attachment_path' => 'class-stream/oversight.pdf',
            'attachment_name' => 'oversight.pdf',
            'attachment_mime' => 'application/pdf',
        ]);
        $adminRole = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('class-stream.posts.attachment', [$this->section, $post]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('class-stream.reactions.toggle', [$this->section, $post]))
            ->assertForbidden();
    }

    public function test_teacher_controls_whether_enrolled_students_can_create_posts(): void
    {
        $this->actingAs($this->studentUser)
            ->post(route('class-stream.posts.store', $this->section), ['body' => 'Blocked student post'])
            ->assertForbidden();

        $this->actingAs($this->teacherUser)
            ->patch(route('class-stream.student-posting.toggle', $this->section))
            ->assertRedirect(route('teacher-dashboard', ['section_id' => $this->section->id, 'tab' => 'stream']));
        $this->assertDatabaseHas('course_sections', ['id' => $this->section->id, 'students_can_post_stream' => true]);

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', $this->section))
            ->assertOk()
            ->assertSee('Share with your class');
        $this->actingAs($this->studentUser)
            ->post(route('class-stream.posts.store', $this->section), ['body' => 'Allowed student post'])
            ->assertRedirect(route('class-stream.show', $this->section));
        $this->assertDatabaseHas('class_stream_posts', ['course_section_id' => $this->section->id, 'user_id' => $this->studentUser->id, 'body' => 'Allowed student post']);

        $this->actingAs($this->teacherUser)->patch(route('class-stream.student-posting.toggle', $this->section));
        $this->actingAs($this->studentUser)
            ->post(route('class-stream.posts.store', $this->section), ['body' => 'Blocked again'])
            ->assertForbidden();
    }

    public function test_student_outside_class_cannot_view_or_interact_with_stream(): void
    {
        $post = ClassStreamPost::create([
            'course_section_id' => $this->section->id,
            'user_id' => $this->teacherUser->id,
            'body' => 'Private class update.',
        ]);

        $this->actingAs($this->outsideUser)
            ->get(route('class-stream.show', $this->section))
            ->assertForbidden();
        $this->actingAs($this->outsideUser)
            ->post(route('class-stream.comments.store', [$this->section, $post]), ['body' => 'Not allowed'])
            ->assertForbidden();
        $this->actingAs($this->outsideUser)
            ->post(route('class-stream.reactions.toggle', [$this->section, $post]))
            ->assertForbidden();
    }

    public function test_student_classroom_contains_scoped_classwork_people_grades_and_timetable(): void
    {
        $student = Student::where('email', $this->studentUser->email)->firstOrFail();
        $assessment = AssessmentItem::create([
            'course_section_id' => $this->section->id,
            'created_by' => $this->teacherUser->id,
            'title' => 'Classroom Assignment',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 10,
            'due_at' => now()->addDay(),
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        AssessmentSubmission::create([
            'assessment_item_id' => $assessment->id,
            'student_id' => $student->id,
            'content' => 'Completed work',
            'submitted_at' => now(),
            'score' => 18,
            'status' => 'graded',
            'graded_at' => now(),
        ]);
        CourseMaterial::create([
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'title' => 'Published Classroom Notes',
            'file_type' => 'pdf',
            'visibility' => 'published',
            'uploaded_by' => $this->teacherUser->id,
        ]);
        CourseMaterial::create([
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'title' => 'Hidden Draft Notes',
            'file_type' => 'pdf',
            'visibility' => 'draft',
            'uploaded_by' => $this->teacherUser->id,
        ]);
        Mark::create([
            'student_id' => $student->id,
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'prefinal_mark' => 72,
            'final_mark' => 84,
            'status' => 'Published',
            'visibility_status' => 'published',
        ]);
        Timetable::create([
            'course_id' => $this->section->course_id,
            'course_section_id' => $this->section->id,
            'teacher_id' => $this->section->teacher_id,
            'day_of_week' => 'Wednesday',
            'start_time' => '10:00',
            'end_time' => '11:30',
            'room_number' => 'Student Classroom 4',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', $this->section))
            ->assertOk()
            ->assertSee('Student Classroom')
            ->assertSee('Stream')
            ->assertSee('Classwork')
            ->assertSee('People')
            ->assertSee('Grades')
            ->assertSee('Timetable')
            ->assertSee('Messages');

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', ['courseSection' => $this->section, 'tab' => 'classwork']))
            ->assertOk()
            ->assertSee('Classroom Assignment')
            ->assertSee('Published Classroom Notes')
            ->assertDontSee('Hidden Draft Notes');

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', ['courseSection' => $this->section, 'tab' => 'people']))
            ->assertOk()
            ->assertSee('Stream Teacher');

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', ['courseSection' => $this->section, 'tab' => 'grades']))
            ->assertOk()
            ->assertSee('72.00')
            ->assertSee('84')
            ->assertSee('18.00 / 20.00');

        $this->actingAs($this->studentUser)
            ->get(route('class-stream.show', ['courseSection' => $this->section, 'tab' => 'timetable']))
            ->assertOk()
            ->assertSee('Wednesday')
            ->assertSee('10:00-11:30')
            ->assertSee('Student Classroom 4');
    }
}
