<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
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

class ClassMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $teacherUser;

    private User $studentUser;

    private User $otherStudentUser;

    private User $outsideUser;

    private CourseSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student']);
        $this->teacherUser = User::factory()->create(['name' => 'Message Teacher', 'email' => 'message.teacher@example.com']);
        $this->studentUser = User::factory()->create(['name' => 'First Student', 'email' => 'first.message@example.com']);
        $this->otherStudentUser = User::factory()->create(['name' => 'Second Student', 'email' => 'second.message@example.com']);
        $this->outsideUser = User::factory()->create(['name' => 'Outside Student', 'email' => 'outside.message@example.com']);
        $this->teacherUser->roles()->attach($teacherRole);
        foreach ([$this->studentUser, $this->otherStudentUser, $this->outsideUser] as $user) {
            $user->roles()->attach($studentRole);
        }

        $university = University::create(['name' => 'Message University', 'code' => 'MSG']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computing', 'code' => 'CMP']);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Fall', 'academic_year' => '2026/2027']);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'MSG-T1',
            'full_name' => 'Message Teacher',
            'email' => $this->teacherUser->email,
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'semester_id' => $semester->id,
            'code' => 'MSG101',
            'name' => 'Class Messaging',
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

        foreach ([
            [$this->studentUser, 'MSG-S1', 'First Student'],
            [$this->otherStudentUser, 'MSG-S2', 'Second Student'],
            [$this->outsideUser, 'MSG-S3', 'Outside Student'],
        ] as [$user, $studentId, $name]) {
            $student = Student::create([
                'university_id' => $university->id,
                'department_id' => $department->id,
                'student_id' => $studentId,
                'full_name' => $name,
                'email' => $user->email,
                'status' => 'Active',
            ]);
            if ($user->id !== $this->outsideUser->id) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_section_id' => $this->section->id,
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ]);
            }
        }
    }

    public function test_teacher_can_privately_message_enrolled_student_with_file(): void
    {
        $this->actingAs($this->teacherUser)
            ->get(route('class-messages.index', $this->section))
            ->assertOk()
            ->assertSee('First Student')
            ->assertSee('Second Student')
            ->assertSee('No conversation selected')
            ->assertDontSee('Outside Student');

        $this->actingAs($this->teacherUser)
            ->post(route('class-messages.store', $this->section), [
                'recipient_id' => $this->studentUser->id,
                'body' => 'Please review your progress.',
                'attachment' => UploadedFile::fake()->create('progress.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('class-messages.index', ['courseSection' => $this->section, 'recipient_id' => $this->studentUser->id]));

        $message = ClassMessage::firstOrFail();
        Storage::disk('public')->assertExists($message->attachment_path);
        $this->actingAs($this->studentUser)
            ->get(route('class-messages.index', $this->section))
            ->assertOk()
            ->assertSee('Please review your progress.')
            ->assertSee('progress.pdf');
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_student_can_message_teacher_but_not_another_student(): void
    {
        $this->actingAs($this->studentUser)
            ->post(route('class-messages.store', $this->section), [
                'recipient_id' => $this->teacherUser->id,
                'body' => 'I have a question.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('class_messages', [
            'sender_id' => $this->studentUser->id,
            'recipient_id' => $this->teacherUser->id,
            'body' => 'I have a question.',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->teacherUser->id,
            'type' => 'class_message',
            'title' => 'New class message from First Student',
        ]);

        $this->actingAs($this->teacherUser)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Class submissions, student messages, deadlines, and teaching alerts.')
            ->assertSee('I have a question.');

        $notification = AppNotification::where('user_id', $this->teacherUser->id)
            ->where('type', 'class_message')
            ->firstOrFail();
        $message = ClassMessage::where('recipient_id', $this->teacherUser->id)->firstOrFail();

        $this->actingAs($this->teacherUser)
            ->get(route('class-messages.index', ['courseSection' => $this->section, 'recipient_id' => $this->studentUser->id]))
            ->assertOk()
            ->assertSee('I have a question.');
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($this->studentUser)
            ->post(route('class-messages.store', $this->section), [
                'recipient_id' => $this->otherStudentUser->id,
                'body' => 'This should be blocked.',
            ])
            ->assertForbidden();
    }

    public function test_message_and_attachment_are_private_to_thread_members(): void
    {
        $path = UploadedFile::fake()->create('private.pdf', 50)->store('class-messages', 'public');
        $message = ClassMessage::create([
            'course_section_id' => $this->section->id,
            'sender_id' => $this->teacherUser->id,
            'recipient_id' => $this->studentUser->id,
            'body' => 'Private message',
            'attachment_path' => $path,
            'attachment_name' => 'private.pdf',
        ]);

        $this->actingAs($this->otherStudentUser)
            ->get(route('class-messages.attachment', [$this->section, $message]))
            ->assertForbidden();
        $this->actingAs($this->outsideUser)
            ->get(route('class-messages.index', $this->section))
            ->assertForbidden();
    }

    public function test_teacher_sees_the_latest_one_hundred_messages_in_a_conversation(): void
    {
        foreach (range(1, 101) as $number) {
            ClassMessage::create([
                'course_section_id' => $this->section->id,
                'sender_id' => $this->studentUser->id,
                'recipient_id' => $this->teacherUser->id,
                'body' => 'Thread message '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($this->teacherUser)
            ->get(route('class-messages.index', ['courseSection' => $this->section, 'recipient_id' => $this->studentUser->id]))
            ->assertOk()
            ->assertDontSee('Thread message 001')
            ->assertSee('Thread message 002')
            ->assertSee('Thread message 101');
    }

    public function test_teacher_students_are_sorted_by_newest_received_message(): void
    {
        ClassMessage::create([
            'course_section_id' => $this->section->id,
            'sender_id' => $this->studentUser->id,
            'recipient_id' => $this->teacherUser->id,
            'body' => 'Earlier student message',
        ]);
        ClassMessage::create([
            'course_section_id' => $this->section->id,
            'sender_id' => $this->otherStudentUser->id,
            'recipient_id' => $this->teacherUser->id,
            'body' => 'Newest student message',
        ]);

        $this->actingAs($this->teacherUser)
            ->get(route('class-messages.index', $this->section))
            ->assertOk()
            ->assertSeeInOrder(['Second Student', 'First Student']);
    }

    public function test_messages_can_be_sent_and_refreshed_without_a_page_reload(): void
    {
        $this->actingAs($this->studentUser)
            ->postJson(route('class-messages.store', $this->section), [
                'recipient_id' => $this->teacherUser->id,
                'body' => 'Live classroom message',
            ])
            ->assertCreated()
            ->assertJsonStructure(['message_id']);

        $response = $this->actingAs($this->teacherUser)
            ->getJson(route('class-messages.thread', [
                'courseSection' => $this->section,
                'recipient_id' => $this->studentUser->id,
            ]));

        $response->assertOk()->assertJsonStructure(['messages_html', 'participants_html', 'signature']);
        $this->assertStringContainsString('Live classroom message', $response->json('messages_html'));
        $this->assertNotNull(ClassMessage::firstOrFail()->fresh()->read_at);
    }
}
