<?php

namespace Tests\Feature;

use App\Models\AcademicYearClosure;
use App\Models\ActivityLog;
use App\Models\AssessmentItem;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\ClassStreamPost;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Stage;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use App\Services\StudentProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_year_closing_page_is_available_from_academic_setup(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();

        $this->actingAs($user)
            ->get(route('bologna-definition'))
            ->assertOk()
            ->assertSee('Bologna Definition')
            ->assertSee(route('academic-year-closures.index'), false);

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk()
            ->assertSee('Closing Readiness')
            ->assertSee('Missing result rows');
    }

    public function test_closing_is_blocked_until_results_are_complete_and_published(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();

        $this->actingAs($user)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('academic_year_closures', [
            'academic_year' => $setup['semester']->academic_year,
        ]);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 35,
            'first_trial_final_exam' => 10,
            'final_exam' => 10,
            'final_mark' => 45,
            'submission_status' => 'approved',
            'visibility_status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('academic_year_closures', [
            'academic_year' => $setup['semester']->academic_year,
        ]);
    }

    public function test_closing_is_blocked_when_a_module_has_no_stage(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();
        $setup['section']->update(['stage_id' => null]);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 70,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk()
            ->assertSee('Modules without a stage')
            ->assertSee('Modules Requiring a Stage');

        $this->actingAs($user)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $this->assertDatabaseMissing('academic_year_closures', ['academic_year' => $setup['semester']->academic_year]);
    }

    public function test_approved_exception_allows_an_incomplete_student_to_receive_an_explicit_closing_decision(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();

        $this->actingAs($user)->post(route('academic-year-closures.exceptions.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'student_id' => $setup['student']->id,
            'status' => 'incomplete',
            'reason' => 'Approved medical leave requires the final result to remain incomplete.',
        ])->assertRedirect(route('academic-year-closures.index', ['university_id' => $setup['university']->id, 'academic_year' => $setup['semester']->academic_year]));

        $this->assertDatabaseHas('student_progression_exceptions', [
            'student_id' => $setup['student']->id,
            'academic_year' => $setup['semester']->academic_year,
            'status' => 'incomplete',
            'approved_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ])->assertRedirect(route('academic-year-closures.index', ['university_id' => $setup['university']->id, 'academic_year' => $setup['semester']->academic_year]));

        $this->assertDatabaseHas('academic_year_closures', ['academic_year' => $setup['semester']->academic_year]);
        $this->assertSame(Student::STANDING_INCOMPLETE, $setup['student']->fresh()->academic_standing);
        $this->assertDatabaseMissing('student_stage_progressions', [
            'student_id' => $setup['student']->id,
            'academic_year' => $setup['semester']->academic_year,
        ]);
    }

    public function test_missing_current_stage_can_be_resolved_from_the_closing_workspace(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();
        $setup['student']->update(['current_stage_id' => null]);

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk()
            ->assertSee('Students without a current stage')
            ->assertSee('Assign Stage');

        $this->actingAs($user)->patch(route('academic-year-closures.students.stage', $setup['student']), [
            'academic_year' => $setup['semester']->academic_year,
            'stage_id' => $setup['stage']->id,
        ])->assertRedirect(route('academic-year-closures.index', ['university_id' => $setup['university']->id, 'academic_year' => $setup['semester']->academic_year]));

        $this->assertSame($setup['stage']->id, $setup['student']->fresh()->current_stage_id);
    }

    public function test_failed_student_remains_in_the_same_stage_when_the_year_closes(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();
        $stage = Stage::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'name' => 'Stage 2',
            'sequence' => 2,
        ]);
        Stage::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'name' => 'Stage 3',
            'sequence' => 3,
        ]);
        $setup['section']->update(['stage_id' => $stage->id]);
        $setup['student']->update(['current_stage_id' => $stage->id]);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 45,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertRedirect();

        $student = $setup['student']->fresh();
        $this->assertSame($stage->id, $student->current_stage_id);
        $this->assertSame(Student::STANDING_REPEAT_STAGE, $student->academic_standing);
        $this->assertDatabaseHas('student_stage_progressions', [
            'student_id' => $student->id,
            'stage_id' => $stage->id,
            'academic_year' => $setup['semester']->academic_year,
            'outcome' => 'repeat_stage',
            'failed_modules' => 1,
        ]);
    }

    public function test_retake_only_year_preserves_the_students_current_stage(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();
        $lowerStage = Stage::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'name' => 'Stage 3',
            'sequence' => 3,
        ]);
        $retakeSection = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $lowerStage->id,
            'section_code' => 'RETAKE',
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $setup['student']->id,
            'course_section_id' => $retakeSection->id,
            'status' => 'enrolled',
            'is_retake' => true,
            'enrolled_at' => now(),
        ]);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $retakeSection->id,
            'final_mark' => 65,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $report = app(StudentProgressionService::class)->process(
            collect([$retakeSection->id]),
            $setup['semester']->academic_year,
            $admin
        );

        $this->assertSame(1, $report['retake_only']);
        $this->assertSame($setup['stage']->id, $setup['student']->fresh()->current_stage_id);
        $this->assertDatabaseHas('student_stage_progressions', [
            'student_id' => $setup['student']->id,
            'stage_id' => $setup['stage']->id,
            'outcome' => 'retake_only',
        ]);
    }

    public function test_academic_year_can_close_with_published_results_and_open_finance_carried_forward(): void
    {
        $user = $this->adminUser();
        $setup = $this->academicSetup();
        $teacher = Teacher::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'staff_id' => 'T-ARCHIVE-1',
            'full_name' => 'Archive Teacher',
            'email' => 'archive.teacher@example.com',
            'title' => 'Lecturer',
            'status' => 'Active',
        ]);
        $setup['semester']->update([
            'start_date' => '2027-02-01',
            'end_date' => '2027-06-30',
        ]);
        $setup['section']->update([
            'teacher_id' => $teacher->id,
            'capacity' => 35,
            'students_can_post_stream' => false,
            'notes' => 'Final year project module details.',
        ]);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 35,
            'first_trial_final_exam' => 10,
            'final_exam' => 10,
            'final_mark' => 45,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $invoice = FinanceTransaction::create([
            'student_id' => $setup['student']->id,
            'recorded_by' => $user->id,
            'type' => 'invoice',
            'amount' => 500000,
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-CLOSE-001',
            'academic_year' => $setup['semester']->academic_year,
            'transaction_date' => now(),
            'due_date' => now()->addMonth(),
        ]);
        AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'created_by' => $user->id,
            'title' => 'Archived Project',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 20,
            'status' => 'published',
            'allow_submissions' => true,
        ]);
        Attendance::create([
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'student_id' => $setup['student']->id,
            'date' => '2027-05-01',
            'status' => 'present',
        ]);
        Timetable::create([
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => 'A101',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        CourseMaterial::create([
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'title' => 'Archived Lecture File',
            'file_type' => 'pdf',
            'file_path' => 'materials/archive.pdf',
            'visibility' => 'published',
            'uploaded_by' => $user->id,
        ]);
        ClassStreamPost::create([
            'course_section_id' => $setup['section']->id,
            'user_id' => $user->id,
            'body' => 'Archived class stream post',
        ]);
        ClassMessage::create([
            'course_section_id' => $setup['section']->id,
            'sender_id' => $user->id,
            'recipient_id' => $user->id,
            'body' => 'Archived private class message',
        ]);

        $this->actingAs($user)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertRedirect(route('academic-year-closures.index', ['university_id' => $setup['university']->id, 'academic_year' => $setup['semester']->academic_year]));

        $this->assertDatabaseHas('academic_year_closures', [
            'university_id' => $setup['university']->id,
            'academic_year' => $setup['semester']->academic_year,
            'status' => 'closed',
            'closed_by' => $user->id,
        ]);
        $closureSummary = AcademicYearClosure::first()->summary;
        $snapshot = $closureSummary['archive_snapshot'];
        $this->assertSame(1, $closureSummary['archived_modules']);
        $this->assertContains($setup['section']->id, $closureSummary['archived_module_ids']);
        $this->assertCount(1, $snapshot['modules']);
        $this->assertCount(1, $snapshot['enrollments']);
        $this->assertCount(1, $snapshot['marks']);
        $this->assertCount(1, $snapshot['assessments']);
        $this->assertCount(1, $snapshot['attendance']);
        $this->assertCount(1, $snapshot['timetable']);
        $this->assertCount(1, $snapshot['materials']);
        $this->assertCount(1, $snapshot['stream_posts']);
        $this->assertCount(1, $snapshot['class_messages']);
        $this->assertCount(1, $snapshot['finance_transactions']);
        $this->assertSame('Capstone', $snapshot['modules'][0]['course_name']);
        $this->assertSame('closed', $snapshot['modules'][0]['status']);
        $this->assertTrue($snapshot['modules'][0]['is_archived']);
        $this->assertSame('BND University', $snapshot['modules'][0]['details']['university']['name']);
        $this->assertSame('ENG', $snapshot['modules'][0]['details']['college']['code']);
        $this->assertSame('CS', $snapshot['modules'][0]['details']['department']['code']);
        $this->assertSame(4, $snapshot['modules'][0]['details']['course']['credits']);
        $this->assertSame('2027-02-01', $snapshot['modules'][0]['details']['semester']['start_date']);
        $this->assertSame('2027-06-30', $snapshot['modules'][0]['details']['semester']['end_date']);
        $this->assertSame('T-ARCHIVE-1', $snapshot['modules'][0]['details']['teacher']['staff_id']);
        $this->assertSame('Archive Teacher', $snapshot['modules'][0]['details']['teacher']['name']);
        $this->assertSame(35, $snapshot['modules'][0]['details']['module']['capacity']);
        $this->assertFalse($snapshot['modules'][0]['details']['module']['students_can_post_stream']);
        $this->assertSame('Final year project module details.', $snapshot['modules'][0]['details']['module']['notes']);
        $this->assertSame('completed', $snapshot['enrollments'][0]['status']);
        $this->assertSame('Closing Student', $snapshot['marks'][0]['student_name']);
        $archivedEnrollment = Enrollment::withTrashed()
            ->where('student_id', $setup['student']->id)
            ->where('course_section_id', $setup['section']->id)
            ->first();
        $this->assertNotNull($archivedEnrollment);
        $this->assertSame('completed', $archivedEnrollment->status);
        $this->assertSoftDeleted('course_sections', ['id' => $setup['section']->id]);
        $this->assertSoftDeleted('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
        ]);
        $this->assertSoftDeleted('marks', [
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
        ]);
        $this->assertDatabaseHas('enrollment_events', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'action' => 'completed_by_year_closure',
        ]);
        $this->assertSoftDeleted('finance_transactions', ['id' => $invoice->id]);
        $this->assertSame('open', FinanceTransaction::withTrashed()->find($invoice->id)?->payment_status);

        $closureLog = ActivityLog::where('log_name', 'academic_year_closure')->first();
        $this->assertNotNull($closureLog);
        $this->assertSame('year_closed', $closureLog->description);
        $this->assertSame(AcademicYearClosure::class, $closureLog->subject_type);
        $this->assertSame($user->id, $closureLog->causer_id);
        $this->assertIsArray($closureLog->properties);
        $this->assertSame($setup['semester']->academic_year, $closureLog->properties['academic_year']);

        $this->actingAs($user)
            ->get(route('academic-year-closures.archive.show', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk()
            ->assertSee('Archive Snapshot')
            ->assertSee('Roster Rows')
            ->assertSee('Attendance')
            ->assertSee('Timetable')
            ->assertSee('Finance Records');
    }

    public function test_closed_year_classes_are_available_in_student_teacher_and_admin_archives(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();

        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $teacherUser = User::factory()->create(['email' => 'archive.teacher@example.com']);
        $teacherUser->roles()->attach($teacherRole);
        $teacher = Teacher::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'staff_id' => 'ARCH-T1',
            'full_name' => 'Archive Teacher',
            'email' => $teacherUser->email,
            'status' => 'Active',
        ]);
        $setup['section']->update(['teacher_id' => $teacher->id]);

        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => $setup['student']->email]);
        $studentUser->roles()->attach($studentRole);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 35,
            'first_trial_final_exam' => 10,
            'final_exam' => 10,
            'final_mark' => 45,
            'status' => 'Failed',
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $this->actingAs($studentUser)
            ->get(route('archived-classes.index'))
            ->assertOk()
            ->assertSee('Archived Classes')
            ->assertSee('Capstone')
            ->assertSee('45.00')
            ->assertSee('Failed');

        $this->actingAs($teacherUser)
            ->get(route('archived-classes.index'))
            ->assertOk()
            ->assertSee('Archived Classes')
            ->assertSee('Capstone')
            ->assertSee('Read only')
            ->assertSee('Roster');

        $this->actingAs($admin)
            ->get(route('academic-year-closures.archive'))
            ->assertOk()
            ->assertSee('Academic Year Archive')
            ->assertSee($setup['semester']->academic_year)
            ->assertSee('Archived Modules')
            ->assertSee('Closed Not Archived')
            ->assertSee(route('academic-year-closures.archive.show', ['academic_year' => $setup['semester']->academic_year]), false);

        $this->actingAs($admin)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'college_id' => $setup['college']->id,
                'department_id' => $setup['department']->id,
                'stage' => 'Stage 4',
            ]))
            ->assertOk()
            ->assertSee('Engineering')
            ->assertSee('Computer Science')
            ->assertSee('Stage 4')
            ->assertSee('Capstone');

        $this->actingAs($admin)
            ->get('/academic-year-archive/year:academic_year='.urlencode($setup['semester']->academic_year))
            ->assertRedirect(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
            ]));
    }

    public function test_archive_year_page_opens_even_when_semester_definitions_are_missing(): void
    {
        $admin = $this->adminUser();
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Legacy College', 'code' => 'LEG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Legacy Department', 'code' => 'LD']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'LEG401', 'name' => 'Legacy Result', 'credits' => 3, 'status' => 'active']);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'LEG-4001',
            'full_name' => 'Legacy Archive Student',
            'email' => 'legacy.archive@example.com',
            'status' => 'Active',
        ]);

        AcademicYearClosure::create([
            'university_id' => $university->id,
            'academic_year' => '2030/2031',
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => now(),
            'summary' => [
                'semester_count' => 2,
                'section_count' => 1,
                'student_count' => 1,
                'enrollment_count' => 1,
                'entered_marks' => 1,
                'published_marks' => 1,
            ],
        ]);
        Mark::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'course_section_id' => null,
            'final_mark' => 74,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => '2030/2031',
                'students' => '1',
                'q' => 'Legacy',
            ]))
            ->assertOk()
            ->assertSee('2030/2031')
            ->assertSee('The original semester/module records for this year are no longer available.')
            ->assertSee('Legacy Archive Student')
            ->assertSee('Legacy Result')
            ->assertSee('74.00')
            ->assertSee('Passed')
            ->assertSee('No archived modules match these filters.');
    }

    public function test_archive_rebuild_command_backfills_missing_snapshot_data(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();
        $setup['section']->update(['status' => 'closed']);
        $setup['student']->enrollments()->first()->update(['status' => 'completed']);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 72,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        AcademicYearClosure::create([
            'university_id' => $setup['university']->id,
            'academic_year' => $setup['semester']->academic_year,
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => now(),
            'summary' => null,
        ]);

        $this->artisan('academic-years:rebuild-archive', [
            'academic_year' => $setup['semester']->academic_year,
        ])->assertExitCode(0);

        $summary = AcademicYearClosure::firstOrFail()->summary;

        $this->assertIsArray($summary['archive_snapshot'] ?? null);
        $this->assertCount(1, $summary['archive_snapshot']['modules']);
        $this->assertCount(1, $summary['archive_snapshot']['marks']);
    }

    public function test_archive_rebuild_force_preserves_soft_deleted_year_records(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 72,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $this->assertSoftDeleted('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
        ]);
        $this->assertSoftDeleted('marks', [
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
        ]);

        $this->artisan('academic-years:rebuild-archive', [
            'academic_year' => $setup['semester']->academic_year,
            '--force' => true,
        ])->assertExitCode(0);

        $summary = AcademicYearClosure::firstOrFail()->summary;
        $snapshot = $summary['archive_snapshot'] ?? [];

        $this->assertSame(1, $summary['enrollment_count'] ?? null);
        $this->assertSame(1, $summary['entered_marks'] ?? null);
        $this->assertSame(1, $snapshot['counts']['enrollments'] ?? null);
        $this->assertSame(1, $snapshot['counts']['marks'] ?? null);
        $this->assertCount(1, $snapshot['enrollments'] ?? []);
        $this->assertCount(1, $snapshot['marks'] ?? []);
    }

    public function test_closed_year_archive_lists_passed_and_failed_students_sorted_by_marks(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();
        $passedStudent = Student::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'current_stage_id' => $setup['stage']->id,
            'student_id' => 'CS-4002',
            'full_name' => 'Passed Closing Student',
            'email' => 'passed.closing@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $passedStudent->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);
        $secondCourse = Course::create([
            'department_id' => $setup['department']->id,
            'code' => 'CS402',
            'name' => 'Advanced Project',
            'credits' => 3,
            'status' => 'active',
        ]);
        $secondSection = CourseSection::create([
            'course_id' => $secondCourse->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $setup['stage']->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 4',
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $passedStudent->id,
            'course_section_id' => $secondSection->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 35,
            'first_trial_final_exam' => 10,
            'final_exam' => 10,
            'final_mark' => 45,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        Mark::create([
            'student_id' => $passedStudent->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 40,
            'first_trial_final_exam' => 42,
            'final_exam' => 42,
            'final_mark' => 82,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        Mark::create([
            'student_id' => $passedStudent->id,
            'course_id' => $secondCourse->id,
            'course_section_id' => $secondSection->id,
            'prefinal_mark' => 38,
            'first_trial_final_exam' => 40,
            'final_exam' => 40,
            'final_mark' => 78,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $examRole = Role::create(['name' => 'examination_committee', 'display_name' => 'Examination Committee']);
        $examUser = User::factory()->create([
            'university_id' => $setup['university']->id,
            'college_id' => $setup['college']->id,
            'department_id' => $setup['department']->id,
        ]);
        $examUser->roles()->attach($examRole);

        $this->actingAs($examUser)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'students' => '1',
                'result_status' => 'failed',
                'sort' => 'final_asc',
            ]))
            ->assertOk()
            ->assertSee('Passed and Failed Students')
            ->assertSee('1 failed')
            ->assertSee('Closing Student')
            ->assertSee('45.00')
            ->assertSee('Failed')
            ->assertDontSee('Passed Closing Student');

        $response = $this->actingAs($examUser)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'students' => '1',
                'sort' => 'final_desc',
            ]))
            ->assertOk();

        $response->assertSeeInOrder(['Passed Closing Student', '80.00', 'Closing Student', '45.00']);
        $this->assertSame(1, substr_count($response->getContent(), 'Passed Closing Student'));
        $response->assertSee('2 modules');
    }

    public function test_archive_student_results_are_scoped_by_stage_filter(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();

        $stageThree = Stage::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'name' => 'Stage 3',
            'sequence' => 3,
        ]);

        $stageThreeStudent = Student::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'current_stage_id' => $stageThree->id,
            'student_id' => 'CS-3001',
            'full_name' => 'Stage Three Student',
            'email' => 'stage3.student@example.com',
            'status' => 'Active',
        ]);
        $stageThreeCourse = Course::create([
            'department_id' => $setup['department']->id,
            'code' => 'CS301',
            'name' => 'Stage 3 Systems',
            'credits' => 3,
            'status' => 'active',
        ]);
        $stageThreeSection = CourseSection::create([
            'course_id' => $stageThreeCourse->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $stageThree->id,
            'section_code' => 'C',
            'grade_level' => 'Stage 3',
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $stageThreeStudent->id,
            'course_section_id' => $stageThreeSection->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 65,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        Mark::create([
            'student_id' => $stageThreeStudent->id,
            'course_id' => $stageThreeCourse->id,
            'course_section_id' => $stageThreeSection->id,
            'final_mark' => 72,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'students' => '1',
                'stage' => 'Stage 4',
            ]))
            ->assertOk();

        $response->assertSee('Passed and Failed Students');
        $response->assertSee('1 students');
        $response->assertSee('1 passed students');
        $response->assertSee('0 failed students');
        $response->assertSee('Closing Student');
        $response->assertDontSee('Stage Three Student');
    }

    public function test_scoped_archive_users_only_see_their_organization_and_exports_current_results(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();
        $medicine = College::create(['university_id' => $setup['university']->id, 'name' => 'Medicine', 'code' => 'MED']);
        $nursing = Department::create(['university_id' => $setup['university']->id, 'college_id' => $medicine->id, 'name' => 'Nursing', 'code' => 'NUR']);
        $nursingStage = Stage::create([
            'university_id' => $setup['university']->id,
            'department_id' => $nursing->id,
            'name' => 'Stage 4',
            'sequence' => 4,
        ]);
        $otherCourse = Course::create(['department_id' => $nursing->id, 'code' => 'NUR401', 'name' => 'Clinical Archive', 'credits' => 4, 'status' => 'active']);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $setup['semester']->id,
            'stage_id' => $nursingStage->id,
            'section_code' => 'M',
            'grade_level' => 'Stage 4',
            'status' => 'active',
        ]);
        $otherStudent = Student::create([
            'university_id' => $setup['university']->id,
            'department_id' => $nursing->id,
            'current_stage_id' => $nursingStage->id,
            'student_id' => 'NUR-4001',
            'full_name' => 'Nursing Archive Student',
            'email' => 'nursing.archive@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $otherStudent->id,
            'course_section_id' => $otherSection->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 67,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        Mark::create([
            'student_id' => $otherStudent->id,
            'course_id' => $otherCourse->id,
            'course_section_id' => $otherSection->id,
            'final_mark' => 91,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $collegeRole = Role::create(['name' => 'college_administrator', 'display_name' => 'College Administrator']);
        $collegeUser = User::factory()->create([
            'university_id' => $setup['university']->id,
            'college_id' => $setup['college']->id,
        ]);
        $collegeUser->roles()->attach($collegeRole);

        $this->actingAs($collegeUser)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'students' => '1',
                'q' => 'Closing',
            ]))
            ->assertOk()
            ->assertSee('Capstone')
            ->assertSee('Closing Student')
            ->assertDontSee('Clinical Archive')
            ->assertDontSee('Nursing Archive Student');

        $this->actingAs($collegeUser)
            ->get(route('academic-year-closures.archive.show', [
                'academic_year' => $setup['semester']->academic_year,
                'students' => '1',
                'q' => 'Nursing',
            ]))
            ->assertOk()
            ->assertSee('No published results match these filters.')
            ->assertDontSee('Nursing Archive Student');

        $export = $this->actingAs($collegeUser)->get(route('academic-year-closures.archive.export', [
            'academic_year' => $setup['semester']->academic_year,
            'q' => 'Closing',
        ]));
        $export->assertOk();
        $export->assertDownload();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Closing Student', $csv);
        $this->assertStringContainsString('Capstone', $csv);
        $this->assertStringNotContainsString('Nursing Archive Student', $csv);
        $this->assertStringNotContainsString('Clinical Archive', $csv);
    }

    public function test_student_can_register_again_after_old_year_is_archived(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();

        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'prefinal_mark' => 35,
            'first_trial_final_exam' => 10,
            'final_exam' => 10,
            'final_mark' => 45,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'academic_year' => $setup['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ]);

        $nextSemester = Semester::create(['university_id' => $setup['university']->id, 'name' => 'Fall', 'academic_year' => '2027/2028']);
        $nextSection = CourseSection::create([
            'course_id' => $setup['course']->id,
            'semester_id' => $nextSemester->id,
            'stage_id' => $setup['stage']->id,
            'section_code' => 'B',
            'grade_level' => 'Stage 4',
            'status' => 'active',
        ]);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => $setup['student']->email]);
        $studentUser->roles()->attach($studentRole);

        $this->actingAs($studentUser)
            ->get(route('course-registration.index'))
            ->assertOk()
            ->assertSee('Capstone')
            ->assertSee('Retake')
            ->assertSee('Register');

        $this->actingAs($studentUser)
            ->post(route('course-registration.store'), ['course_section_id' => $nextSection->id])
            ->assertRedirect(route('course-registration.index'));

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $setup['section']->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $setup['student']->id,
            'course_section_id' => $nextSection->id,
            'status' => 'enrolled',
            'is_retake' => true,
        ]);
    }

    public function test_already_closed_year_can_archive_remaining_visible_modules(): void
    {
        $admin = $this->adminUser();
        $setup = $this->academicSetup();

        $setup['section']->update(['status' => 'closed']);
        $setup['student']->enrollments()->first()->update(['status' => 'completed']);
        Mark::create([
            'student_id' => $setup['student']->id,
            'course_id' => $setup['course']->id,
            'course_section_id' => $setup['section']->id,
            'final_mark' => 62,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);
        AcademicYearClosure::create([
            'university_id' => $setup['university']->id,
            'academic_year' => $setup['semester']->academic_year,
            'status' => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('academic-year-closures.index', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk()
            ->assertSee('Archive Remaining Data');

        $this->actingAs($admin)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertRedirect(route('academic-year-closures.index', ['university_id' => $setup['university']->id, 'academic_year' => $setup['semester']->academic_year]));

        $this->assertSoftDeleted('course_sections', ['id' => $setup['section']->id]);
    }

    public function test_teachers_cannot_see_or_open_closed_year_class_data(): void
    {
        $setup = $this->academicSetup();
        $teacherRole = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $teacherUser = User::factory()->create(['email' => 'closed.teacher@example.com']);
        $teacherUser->roles()->attach($teacherRole);
        $teacher = Teacher::create([
            'university_id' => $setup['university']->id,
            'department_id' => $setup['department']->id,
            'staff_id' => 'T-CLOSED',
            'full_name' => 'Closed Year Teacher',
            'email' => $teacherUser->email,
            'status' => 'Active',
        ]);
        $setup['section']->update([
            'teacher_id' => $teacher->id,
            'status' => 'closed',
        ]);
        AssessmentItem::create([
            'course_section_id' => $setup['section']->id,
            'title' => 'Old Closed Assessment',
            'type' => 'assignment',
            'max_score' => 20,
            'weight_percent' => 10,
            'status' => 'published',
            'allow_submissions' => true,
        ]);

        $this->actingAs($teacherUser)
            ->get(route('teacher-dashboard'))
            ->assertOk()
            ->assertDontSee('Capstone')
            ->assertDontSee(route('teacher-dashboard', ['section_id' => $setup['section']->id]), false);

        $this->actingAs($teacherUser)
            ->get(route('assessments.index'))
            ->assertOk()
            ->assertDontSee('Old Closed Assessment')
            ->assertDontSee('Capstone');

        $this->actingAs($teacherUser)
            ->get(route('teacher-dashboard', ['section_id' => $setup['section']->id]))
            ->assertNotFound();

        $this->actingAs($teacherUser)
            ->get(route('class-stream.show', $setup['section']))
            ->assertNotFound();
    }

    public function test_view_permission_can_review_but_not_close_academic_year(): void
    {
        $permission = Permission::create(['name' => 'academic_setup.view', 'display_name' => 'View academic setup']);
        $user = User::factory()->create();
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);
        $setup = $this->academicSetup();

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', ['academic_year' => $setup['semester']->academic_year]))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('academic-year-closures.store'), [
                'academic_year' => $setup['semester']->academic_year,
                'confirm_results' => '1',
                'confirm_finance' => '1',
            ])
            ->assertForbidden();
    }

    public function test_closing_one_university_does_not_archive_another_university_with_the_same_year(): void
    {
        $admin = $this->adminUser();
        $first = $this->academicSetup();
        $second = $this->secondAcademicSetup();

        foreach ([$first, $second] as $setup) {
            Mark::create([
                'student_id' => $setup['student']->id,
                'course_id' => $setup['course']->id,
                'course_section_id' => $setup['section']->id,
                'final_mark' => 70,
                'submission_status' => 'approved',
                'visibility_status' => 'published',
                'published_at' => now(),
            ]);
        }
        $firstInvoice = FinanceTransaction::create([
            'student_id' => $first['student']->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => 100,
            'currency' => 'IQD',
            'status' => 'approved',
            'payment_status' => 'open',
            'invoice_number' => 'INV-SCOPE-A',
            'academic_year' => $first['semester']->academic_year,
            'transaction_date' => now(),
        ]);
        $secondInvoice = FinanceTransaction::create([
            'student_id' => $second['student']->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => 200,
            'currency' => 'IQD',
            'status' => 'approved',
            'payment_status' => 'open',
            'invoice_number' => 'INV-SCOPE-B',
            'academic_year' => $second['semester']->academic_year,
            'transaction_date' => now(),
        ]);

        $this->actingAs($admin)->post(route('academic-year-closures.store'), [
            'university_id' => $first['university']->id,
            'academic_year' => $first['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ])->assertRedirect(route('academic-year-closures.index', [
            'university_id' => $first['university']->id,
            'academic_year' => $first['semester']->academic_year,
        ]));

        $this->assertDatabaseHas('academic_year_closures', [
            'university_id' => $first['university']->id,
            'academic_year' => $first['semester']->academic_year,
        ]);
        $this->assertDatabaseMissing('academic_year_closures', [
            'university_id' => $second['university']->id,
            'academic_year' => $second['semester']->academic_year,
        ]);
        $this->assertSoftDeleted('course_sections', ['id' => $first['section']->id]);
        $this->assertNotSoftDeleted('course_sections', ['id' => $second['section']->id]);
        $this->assertSoftDeleted('finance_transactions', ['id' => $firstInvoice->id]);
        $this->assertNotSoftDeleted('finance_transactions', ['id' => $secondInvoice->id]);
        $this->assertNotSoftDeleted('enrollments', [
            'student_id' => $second['student']->id,
            'course_section_id' => $second['section']->id,
        ]);
        $this->assertNotSoftDeleted('marks', [
            'student_id' => $second['student']->id,
            'course_section_id' => $second['section']->id,
        ]);
    }

    public function test_department_scoped_manager_can_resolve_but_cannot_close_the_university_year(): void
    {
        $first = $this->academicSetup();
        $second = $this->secondAcademicSetup();
        $permission = Permission::create(['name' => 'academic_setup.manage', 'display_name' => 'Manage academic setup']);
        $user = User::factory()->create([
            'university_id' => $first['university']->id,
            'college_id' => $first['college']->id,
            'department_id' => $first['department']->id,
        ]);
        $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);
        $first['student']->update(['current_stage_id' => null]);

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', [
                'university_id' => $first['university']->id,
                'academic_year' => $first['semester']->academic_year,
            ]))
            ->assertOk()
            ->assertSee($first['university']->name)
            ->assertDontSee($second['university']->name)
            ->assertSee('final closing requires university-level academic authority');

        $this->actingAs($user)
            ->get(route('academic-year-closures.index', [
                'university_id' => $second['university']->id,
                'academic_year' => $second['semester']->academic_year,
            ]))
            ->assertForbidden();

        $this->actingAs($user)->patch(route('academic-year-closures.students.stage', $first['student']), [
            'academic_year' => $first['semester']->academic_year,
            'stage_id' => $first['stage']->id,
        ])->assertRedirect();
        $this->assertSame($first['stage']->id, $first['student']->fresh()->current_stage_id);

        $this->actingAs($user)->post(route('academic-year-closures.store'), [
            'university_id' => $first['university']->id,
            'academic_year' => $first['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ])->assertForbidden();
    }

    public function test_university_scoped_direct_permission_cannot_close_or_archive_another_university(): void
    {
        $first = $this->academicSetup();
        $second = $this->secondAcademicSetup();
        $manage = Permission::create(['name' => 'academic_setup.manage', 'display_name' => 'Manage academic setup']);
        $user = User::factory()->create(['university_id' => $first['university']->id]);
        $user->permissionOverrides()->attach($manage, ['effect' => 'grant']);
        Mark::create([
            'student_id' => $first['student']->id,
            'course_id' => $first['course']->id,
            'course_section_id' => $first['section']->id,
            'final_mark' => 75,
            'submission_status' => 'approved',
            'visibility_status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($user)->post(route('academic-year-closures.store'), [
            'university_id' => $first['university']->id,
            'academic_year' => $first['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_year_closures', ['university_id' => $first['university']->id]);

        $this->actingAs($user)->post(route('academic-year-closures.store'), [
            'university_id' => $second['university']->id,
            'academic_year' => $second['semester']->academic_year,
            'confirm_results' => '1',
            'confirm_finance' => '1',
        ])->assertForbidden();

        AcademicYearClosure::create([
            'university_id' => $second['university']->id,
            'academic_year' => $second['semester']->academic_year,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $this->actingAs($user)
            ->get(route('academic-year-closures.archive'))
            ->assertOk()
            ->assertSee($first['university']->name)
            ->assertDontSee($second['university']->name);
    }

    private function adminUser(): User
    {
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function academicSetup(): array
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Computer Science', 'code' => 'CS']);
        $stage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 4',
            'sequence' => 4,
        ]);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Spring', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'CS401', 'name' => 'Capstone', 'credits' => 4, 'status' => 'active']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 4',
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'current_stage_id' => $stage->id,
            'student_id' => 'CS-4001',
            'full_name' => 'Closing Student',
            'email' => 'closing.student@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return compact('university', 'college', 'department', 'stage', 'semester', 'course', 'section', 'student');
    }

    private function secondAcademicSetup(): array
    {
        $university = University::create(['name' => 'Second University', 'code' => 'SUN']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Second College', 'code' => 'SC']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Second Department', 'code' => 'SD']);
        $stage = Stage::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'name' => 'Stage 4',
            'sequence' => 4,
        ]);
        $semester = Semester::create(['university_id' => $university->id, 'name' => 'Spring', 'academic_year' => '2026/2027']);
        $course = Course::create(['department_id' => $department->id, 'code' => 'SD401', 'name' => 'Second Capstone', 'credits' => 4, 'status' => 'active']);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'stage_id' => $stage->id,
            'section_code' => 'A',
            'grade_level' => 'Stage 4',
            'status' => 'active',
        ]);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'current_stage_id' => $stage->id,
            'student_id' => 'SD-4001',
            'full_name' => 'Second Closing Student',
            'email' => 'second.closing@example.com',
            'status' => 'Active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return compact('university', 'college', 'department', 'stage', 'semester', 'course', 'section', 'student');
    }
}
