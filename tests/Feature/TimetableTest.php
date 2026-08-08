<?php

namespace Tests\Feature;

use App\Models\Classroom;
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
use App\Models\Timetable;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
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

    private function makeStudentUser(?string $email = null): User
    {
        $permission = Permission::create([
            'name' => 'courses.view',
            'display_name' => 'View courses',
            'description' => 'View courses',
        ]);
        $role = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student access',
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::factory()->create([
            'email' => $email ?? 'student-timetable@example.com',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacherUser(Teacher $teacher): User
    {
        $permission = Permission::create(['name' => 'timetable.view', 'display_name' => 'View timetable']);
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['email' => $teacher->email]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeSection(array $attributes = []): CourseSection
    {
        $university = University::first() ?: University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::first() ?: Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);
        $semester = Semester::first() ?: Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'TCH-'.str_pad((string) (Teacher::count() + 1), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Dr. Timetable '.Teacher::count(),
            'email' => 'teacher'.(Teacher::count() + 1).'@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'CS'.str_pad((string) (Course::count() + 101), 3, '0', STR_PAD_LEFT),
            'name' => 'Scheduling '.Course::count(),
            'credits' => 3,
            'semester' => 'Fall',
        ]);

        return CourseSection::create(array_merge([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'A'.(CourseSection::count() + 1),
            'grade_level' => 'Stage 1',
            'capacity' => 40,
            'status' => 'active',
        ], $attributes));
    }

    public function test_admin_can_create_room_and_schedule_module_by_department_and_grade(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection();

        $this->actingAs($admin)
            ->post(route('classrooms.store'), [
                'name' => 'Lab 201',
                'building' => 'Science Block',
                'capacity' => 45,
                'type' => 'lab',
                'status' => 'available',
            ])
            ->assertRedirect(route('timetables.index'));

        $room = Classroom::first();

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $section->course->department_id,
                'grade_level' => $section->grade_level,
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Monday',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'type' => 'lab',
                'status' => 'scheduled',
            ])
            ->assertRedirect(route('timetables.index'));

        $this->assertDatabaseHas('timetables', [
            'course_section_id' => $section->id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:30',
            'room_number' => 'Lab 201',
            'type' => 'lab',
        ]);

        $this->actingAs($admin)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('Lab 201')
            ->assertSee($section->section_code);
    }

    public function test_timetable_blocks_room_conflicts(): void
    {
        $admin = $this->makeSuperAdmin();
        $firstSection = $this->makeSection();
        $secondSection = $this->makeSection();
        $room = Classroom::create(['name' => 'Room 101', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $firstSection->course_id,
            'course_section_id' => $firstSection->id,
            'teacher_id' => $firstSection->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'room_number' => 'Room 101',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $secondSection->course->department_id,
                'grade_level' => $secondSection->grade_level,
                'course_section_id' => $secondSection->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '10:30',
                'end_time' => '11:30',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Timetable::count());
    }

    public function test_cancelled_timetable_entries_do_not_block_new_scheduling(): void
    {
        $admin = $this->makeSuperAdmin();
        $firstSection = $this->makeSection();
        $secondSection = $this->makeSection();
        $room = Classroom::create(['name' => 'Room Cancelled', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $firstSection->course_id,
            'course_section_id' => $firstSection->id,
            'teacher_id' => $firstSection->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $secondSection->course->department_id,
                'grade_level' => $secondSection->grade_level,
                'course_section_id' => $secondSection->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '10:30',
                'end_time' => '11:30',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect(route('timetables.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Timetable::count());
    }

    public function test_scheduled_entries_require_available_rooms(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection();
        $room = Classroom::create([
            'name' => 'Room Maintenance',
            'capacity' => 40,
            'status' => 'maintenance',
        ]);

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $section->course->department_id,
                'grade_level' => $section->grade_level,
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Timetable::count());
    }

    public function test_module_must_match_selected_department_and_grade(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection(['grade_level' => 'Stage 2']);
        $otherDepartment = Department::create([
            'university_id' => $section->course->department->university_id,
            'name' => 'Different Department',
        ]);
        $room = Classroom::create(['name' => 'Mismatch Room', 'capacity' => 40]);

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $otherDepartment->id,
                'grade_level' => 'Stage 2',
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $section->course->department_id,
                'grade_level' => 'Stage 3',
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Timetable::count());
    }

    public function test_department_head_can_schedule_only_own_department_module(): void
    {
        $university = University::create(['name' => 'Department Head University', 'code' => 'DHU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Networks']);
        $otherDepartment = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'DH-T1',
            'full_name' => 'Department Teacher',
            'email' => 'department-teacher@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'NET401',
            'name' => 'Networks Scheduling',
            'credits' => 3,
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'D1',
            'grade_level' => 'Stage 4',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $otherCourse = Course::create([
            'department_id' => $otherDepartment->id,
            'code' => 'SWE401',
            'name' => 'Hidden Scheduling',
            'credits' => 3,
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $semester->id,
            'section_code' => 'X1',
            'grade_level' => 'Stage 4',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $room = Classroom::create(['university_id' => $university->id, 'name' => 'Department Room', 'capacity' => 40]);
        $permission = Permission::create(['name' => 'timetable.manage', 'display_name' => 'Manage timetable']);
        $role = Role::create(['name' => 'department_administrator', 'display_name' => 'Department Head']);
        $role->permissions()->attach($permission);
        $head = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $head->roles()->attach($role);

        $this->actingAs($head)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('NET401')
            ->assertDontSee('SWE401');

        $this->actingAs($head)
            ->post(route('timetables.store'), [
                'department_id' => $department->id,
                'grade_level' => 'Stage 4',
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect(route('timetables.index'))
            ->assertSessionHas('success');

        $this->actingAs($head)
            ->post(route('timetables.store'), [
                'department_id' => $otherDepartment->id,
                'grade_level' => 'Stage 4',
                'course_section_id' => $otherSection->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('timetables', ['course_section_id' => $section->id]);
        $this->assertDatabaseMissing('timetables', ['course_section_id' => $otherSection->id]);
    }

    public function test_department_head_conflict_check_sees_a_shared_room_booked_by_another_department(): void
    {
        $university = University::create(['name' => 'Shared Room University', 'code' => 'SRU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Networks']);
        $otherDepartment = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
        ]);
        $teacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'staff_id' => 'SR-T1',
            'full_name' => 'Networks Teacher',
            'email' => 'networks-teacher@example.com',
            'status' => 'Active',
        ]);
        $course = Course::create([
            'department_id' => $department->id,
            'code' => 'NET402',
            'name' => 'Networks Scheduling 2',
            'credits' => 3,
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'section_code' => 'D2',
            'grade_level' => 'Stage 4',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $otherTeacher = Teacher::create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
            'staff_id' => 'SR-T2',
            'full_name' => 'Software Teacher',
            'email' => 'software-teacher@example.com',
            'status' => 'Active',
        ]);
        $otherCourse = Course::create([
            'department_id' => $otherDepartment->id,
            'code' => 'SWE402',
            'name' => 'Shared Room Scheduling',
            'credits' => 3,
        ]);
        $otherSection = CourseSection::create([
            'course_id' => $otherCourse->id,
            'semester_id' => $semester->id,
            'teacher_id' => $otherTeacher->id,
            'section_code' => 'X2',
            'grade_level' => 'Stage 4',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $room = Classroom::create(['university_id' => $university->id, 'name' => 'Shared Hall', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $otherCourse->id,
            'course_section_id' => $otherSection->id,
            'teacher_id' => $otherTeacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Thursday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $permission = Permission::create(['name' => 'timetable.manage', 'display_name' => 'Manage timetable']);
        $role = Role::create(['name' => 'department_administrator', 'display_name' => 'Department Head']);
        $role->permissions()->attach($permission);
        $head = User::factory()->create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
        $head->roles()->attach($role);

        $this->actingAs($head)
            ->post(route('timetables.store'), [
                'department_id' => $department->id,
                'grade_level' => 'Stage 4',
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Thursday',
                'start_time' => '09:30',
                'end_time' => '10:30',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Timetable::withoutGlobalScope('organization')->count());
    }

    public function test_timetable_blocks_student_enrollment_conflicts(): void
    {
        $admin = $this->makeSuperAdmin();
        $firstSection = $this->makeSection();
        $secondSection = $this->makeSection();
        $roomOne = Classroom::create(['name' => 'Room 201', 'capacity' => 40]);
        $roomTwo = Classroom::create(['name' => 'Room 202', 'capacity' => 40]);
        $student = Student::create([
            'university_id' => $firstSection->course->department->university_id,
            'department_id' => $firstSection->course->department_id,
            'student_id' => 'BND-9001',
            'full_name' => 'Overlap Student',
            'email' => 'overlap@example.com',
            'status' => 'Active',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $firstSection->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-07-10',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $secondSection->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-07-10',
        ]);

        Timetable::create([
            'course_id' => $firstSection->course_id,
            'course_section_id' => $firstSection->id,
            'teacher_id' => $firstSection->teacher_id,
            'classroom_id' => $roomOne->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'room_number' => 'Room 201',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->post(route('timetables.store'), [
                'department_id' => $secondSection->course->department_id,
                'grade_level' => $secondSection->grade_level,
                'course_section_id' => $secondSection->id,
                'classroom_id' => $roomTwo->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '12:30',
                'end_time' => '13:30',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, Timetable::count());
    }

    public function test_timetable_can_be_filtered_and_classified_by_department_grade_semester_and_group(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection([
            'section_code' => 'G1',
            'grade_level' => 'Stage 3',
        ]);
        $room = Classroom::create(['name' => 'Room 301', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Thursday',
            'start_time' => '08:30',
            'end_time' => '10:00',
            'room_number' => 'Room 301',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->get(route('timetables.index', [
                'department_id' => $section->course->department_id,
                'grade_level' => 'Stage 3',
                'semester_id' => $section->semester_id,
                'group' => 'G1',
            ]))
            ->assertOk()
            ->assertSee('Classified Timetable')
            ->assertSee('Computer Science')
            ->assertSee('Stage 3')
            ->assertSee('Fall 2026/2027')
            ->assertSee('Group G1')
            ->assertSee('Thursday 08:30-10:00');
    }

    public function test_admin_can_filter_by_teacher_room_day_type_and_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection(['section_code' => 'F1']);
        $otherSection = $this->makeSection(['section_code' => 'F2']);
        $room = Classroom::create(['name' => 'Filter Room', 'capacity' => 40]);
        $otherRoom = Classroom::create(['name' => 'Hidden Filter Room', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Friday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'room_number' => $room->name,
            'type' => 'lab',
            'status' => 'scheduled',
        ]);
        Timetable::create([
            'course_id' => $otherSection->course_id,
            'course_section_id' => $otherSection->id,
            'teacher_id' => $otherSection->teacher_id,
            'classroom_id' => $otherRoom->id,
            'day_of_week' => 'Saturday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'room_number' => $otherRoom->name,
            'type' => 'lecture',
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin)
            ->get(route('timetables.index', [
                'teacher_id' => $section->teacher_id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Friday',
                'type' => 'lab',
                'status' => 'scheduled',
            ]))
            ->assertOk()
            ->assertSee('Weekly Grid')
            ->assertSee('Filter Room')
            ->assertDontSee('Saturday 13:00-14:00');
    }

    public function test_admin_can_update_timetable_entry(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection();
        $room = Classroom::create(['name' => 'Original Room', 'capacity' => 40]);
        $newRoom = Classroom::create(['name' => 'Updated Room', 'capacity' => 40]);
        $entry = Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->patch(route('timetables.update', $entry), [
                'department_id' => $section->course->department_id,
                'grade_level' => $section->grade_level,
                'course_section_id' => $section->id,
                'classroom_id' => $newRoom->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'type' => 'lab',
                'status' => 'scheduled',
            ])
            ->assertRedirect(route('timetables.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('timetables', [
            'id' => $entry->id,
            'classroom_id' => $newRoom->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'room_number' => 'Updated Room',
            'type' => 'lab',
        ]);
    }

    public function test_closed_academic_year_timetable_entries_are_read_only(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection();
        $room = Classroom::create(['name' => 'Archive Room', 'capacity' => 40]);
        $entry = Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        $section->semester->academicYear->update(['status' => 'closed']);

        $this->actingAs($admin)
            ->patch(route('timetables.update', $entry), [
                'department_id' => $section->course->department_id,
                'grade_level' => $section->grade_level,
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'type' => 'lab',
                'status' => 'scheduled',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('timetables', [
            'id' => $entry->id,
            'day_of_week' => 'Monday',
            'type' => 'lecture',
        ]);
    }

    public function test_admin_timetable_handles_rows_without_module(): void
    {
        $admin = $this->makeSuperAdmin();
        $section = $this->makeSection();
        $room = Classroom::create(['name' => 'Legacy Room', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => null,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '15:00',
            'end_time' => '16:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('No stage')
            ->assertSee('No group')
            ->assertSee('Legacy Room');
    }

    public function test_student_can_view_timetable_but_cannot_add_entries(): void
    {
        $section = $this->makeSection([
            'section_code' => 'S1',
            'grade_level' => 'Stage 1',
        ]);
        $otherSection = $this->makeSection([
            'section_code' => 'S2',
            'grade_level' => 'Stage 1',
        ]);
        $room = Classroom::create(['name' => 'Student View Room', 'capacity' => 40]);
        $otherRoom = Classroom::create(['name' => 'Other Student Room', 'capacity' => 40]);
        $student = Student::create([
            'university_id' => $section->course->department->university_id,
            'department_id' => $section->course->department_id,
            'student_id' => 'BND-TIME-1',
            'full_name' => 'Timetable Student',
            'email' => 'student-timetable@example.com',
            'status' => 'Active',
        ]);
        $studentUser = $this->makeStudentUser($student->email);

        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => '2026-07-10',
        ]);

        Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Saturday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => 'Student View Room',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        Timetable::create([
            'course_id' => $otherSection->course_id,
            'course_section_id' => $otherSection->id,
            'teacher_id' => $otherSection->teacher_id,
            'classroom_id' => $otherRoom->id,
            'day_of_week' => 'Saturday',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'room_number' => 'Other Student Room',
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);

        $this->actingAs($studentUser)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('My Timetable')
            ->assertSee('Student View Room')
            ->assertSee('Saturday')
            ->assertSee('09:00-10:00')
            ->assertDontSee('Other Student Room')
            ->assertDontSee('Classified Timetable')
            ->assertDontSee('Add Timetable Entry')
            ->assertDontSee('Save Room')
            ->assertDontSee('Save Time Slot')
            ->assertDontSee('Remove');

        $this->actingAs($studentUser)
            ->post(route('timetables.store'), [
                'department_id' => $section->course->department_id,
                'grade_level' => $section->grade_level,
                'course_section_id' => $section->id,
                'classroom_id' => $room->id,
                'day_of_week' => 'Monday',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'type' => 'lecture',
                'status' => 'scheduled',
            ])
            ->assertForbidden();
    }

    public function test_teacher_sidebar_timetable_only_shows_assigned_schedule(): void
    {
        $section = $this->makeSection(['section_code' => 'T1']);
        $otherSection = $this->makeSection(['section_code' => 'T2']);
        $teacherUser = $this->makeTeacherUser($section->teacher);
        $room = Classroom::create(['name' => 'Teacher Room', 'capacity' => 40]);
        $otherRoom = Classroom::create(['name' => 'Other Teacher Room', 'capacity' => 40]);

        Timetable::create([
            'course_id' => $section->course_id,
            'course_section_id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'classroom_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_number' => $room->name,
            'type' => 'lecture',
            'status' => 'scheduled',
        ]);
        Timetable::create([
            'course_id' => $otherSection->course_id,
            'course_section_id' => $otherSection->id,
            'teacher_id' => $otherSection->teacher_id,
            'classroom_id' => $otherRoom->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'room_number' => $otherRoom->name,
            'type' => 'lab',
            'status' => 'scheduled',
        ]);

        $this->actingAs($teacherUser)
            ->get(route('timetables.index'))
            ->assertOk()
            ->assertSee('My Teaching Timetable')
            ->assertSee('Teacher Room')
            ->assertSee('Monday')
            ->assertDontSee('Other Teacher Room')
            ->assertDontSee('Classified Timetable')
            ->assertDontSee('Schedule Module')
            ->assertDontSee('Remove');

        $this->actingAs($teacherUser)
            ->post(route('timetables.store'), [])
            ->assertForbidden();
    }
}
