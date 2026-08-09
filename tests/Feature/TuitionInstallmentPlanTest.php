<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionAgreement;
use App\Models\University;
use App\Models\User;
use App\Services\TuitionChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuitionInstallmentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $role = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeUniversity(string $type = University::TYPE_UNIVERSITY): University
    {
        return University::create([
            'name' => 'Multi Year Uni '.$type,
            'code' => 'MYU-'.strtoupper($type),
            'institution_type' => $type,
        ]);
    }

    private static int $studentSequence = 0;

    private function makeStudentFor(University $university): Student
    {
        $department = Department::create(['university_id' => $university->id, 'name' => 'CS Dept']);
        $sequence = ++self::$studentSequence;

        return Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => "MY-{$sequence}",
            'full_name' => 'Multi Year Student',
            'email' => "multi.year.student{$sequence}@example.com",
            'phone' => '0770000010',
            'status' => 'Active',
        ]);
    }

    private function makeAcademicYear(University $university, string $name, string $startsOn, string $endsOn, string $status = 'active'): AcademicYear
    {
        return AcademicYear::create([
            'university_id' => $university->id,
            'name' => $name,
            'status' => $status,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    private function makeSemester(University $university, AcademicYear $academicYear, string $name, int $sequence, string $startDate, string $endDate): Semester
    {
        return Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->name,
            'name' => $name,
            'term_type' => 'regular',
            'sequence' => $sequence,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function test_create_record_form_defaults_installment_count_to_program_length(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);

        $this->actingAs($admin)
            ->get(route('finance.students.records.create', $student))
            ->assertOk()
            ->assertSee("Defaults to 8 for this student's program.", false);

        $institute = $this->makeUniversity(University::TYPE_INSTITUTE);
        $instituteStudent = $this->makeStudentFor($institute);

        $this->actingAs($admin)
            ->get(route('finance.students.records.create', $instituteStudent))
            ->assertOk()
            ->assertSee("Defaults to 4 for this student's program.", false);
    }

    public function test_a_multi_semester_plan_can_start_before_future_semesters_exist(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '8000000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'Full program tuition',
                'payment_plan' => 'semester',
                'semester_ids' => [$semester1->id],
                'installment_count' => 8,
                'transaction_date' => '2026-08-01',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertSame(1, FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->count());
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'semester_id' => $semester1->id,
            'amount' => '1000000.00',
        ]);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(8, $agreement->installment_count);
        $this->assertSame(1, $agreement->installments_generated);
        $this->assertSame(1000000.0, (float) $agreement->installment_amount);
        $this->assertSame(8000000.0, (float) $agreement->total_amount);
    }

    public function test_creating_a_new_semester_automatically_bills_the_next_installment(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '8000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Full program tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id],
            'installment_count' => 8,
            'transaction_date' => '2026-08-01',
        ]);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(1, $agreement->installments_generated);

        $year2 = $this->makeAcademicYear($university, '2027/2028', '2027-09-01', '2028-06-30', 'upcoming');

        $this->actingAs($admin)->post(route('semesters.store'), [
            'name' => 'Semester 1',
            'term_type' => 'regular',
            'sequence' => 1,
            'academic_year_id' => $year2->id,
            'start_date' => '2027-09-01',
            'end_date' => '2027-12-31',
            'university_id' => $university->id,
        ])->assertRedirect(route('semesters.index'));

        $newSemester = Semester::where('academic_year_id', $year2->id)->where('sequence', 1)->firstOrFail();

        $this->assertSame(2, $agreement->fresh()->installments_generated);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'tuition_agreement_id' => $agreement->id,
            'type' => 'invoice',
            'semester_id' => $newSemester->id,
            'amount' => '1000000.00',
        ]);
        $this->assertSame(2, FinanceTransaction::where('tuition_agreement_id', $agreement->id)->where('type', 'invoice')->count());
    }

    public function test_final_installment_absorbs_rounding_remainder(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');
        $semester2 = $this->makeSemester($university, $year1, 'Semester 2', 2, '2027-02-01', '2027-06-30');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Annual tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id, $semester2->id],
            'installment_count' => 3,
            'transaction_date' => '2026-08-01',
        ]);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(3, $agreement->installment_count);
        $this->assertSame(2, $agreement->installments_generated);
        $this->assertSame(333333.33, (float) $agreement->installment_amount);

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'semester_id' => $semester1->id,
            'amount' => '333333.33',
        ]);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'semester_id' => $semester2->id,
            'amount' => '333333.33',
        ]);

        $year2 = $this->makeAcademicYear($university, '2027/2028', '2027-09-01', '2028-06-30', 'upcoming');
        $semester3 = $this->makeSemester($university, $year2, 'Semester 3', 1, '2027-09-01', '2027-12-31');
        app(TuitionChargeService::class)->generateNextInstallmentsForSemester($semester3, $admin);

        $this->assertSame(3, $agreement->fresh()->installments_generated);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'semester_id' => $semester3->id,
            'amount' => '333333.34',
        ]);
    }

    public function test_no_further_installments_are_generated_once_the_plan_is_complete(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Annual tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id],
            'installment_count' => 1,
            'transaction_date' => '2026-08-01',
        ]);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(1, $agreement->installment_count);
        $this->assertSame(1, $agreement->installments_generated);

        $year2 = $this->makeAcademicYear($university, '2027/2028', '2027-09-01', '2028-06-30', 'upcoming');
        $semester2 = $this->makeSemester($university, $year2, 'Semester 1', 1, '2027-09-01', '2027-12-31');
        app(TuitionChargeService::class)->generateNextInstallmentsForSemester($semester2, $admin);

        $this->assertSame(1, $agreement->fresh()->installments_generated);
        $this->assertSame(1, FinanceTransaction::where('tuition_agreement_id', $agreement->id)->where('type', 'invoice')->count());
    }

    public function test_generating_installments_twice_for_the_same_semester_does_not_duplicate_the_invoice(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '8000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Full program tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id],
            'installment_count' => 8,
            'transaction_date' => '2026-08-01',
        ]);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $year2 = $this->makeAcademicYear($university, '2027/2028', '2027-09-01', '2028-06-30', 'upcoming');
        $semester2 = $this->makeSemester($university, $year2, 'Semester 1', 1, '2027-09-01', '2027-12-31');

        $service = app(TuitionChargeService::class);
        $service->generateNextInstallmentsForSemester($semester2, $admin);
        $service->generateNextInstallmentsForSemester($semester2, $admin);

        $this->assertSame(2, $agreement->fresh()->installments_generated);
        $this->assertSame(1, FinanceTransaction::where('tuition_agreement_id', $agreement->id)->where('semester_id', $semester2->id)->count());
    }

    private function enrollStudentIn(Student $student, Semester $semester): void
    {
        $department = Department::where('id', $student->department_id)->firstOrFail();
        $course = Course::create([
            'university_id' => $semester->university_id,
            'department_id' => $department->id,
            'code' => 'EXTRA'.$semester->id,
            'name' => 'Extra Semester Course',
            'credits' => 3,
            'status' => 'active',
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'A',
            'capacity' => 40,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ]);
    }

    public function test_finance_page_warns_when_a_student_is_enrolled_beyond_a_completed_installment_plan(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');
        $semester2 = $this->makeSemester($university, $year1, 'Semester 2', 2, '2027-02-01', '2027-06-30');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '2000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Annual tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id, $semester2->id],
            'installment_count' => 2,
            'transaction_date' => '2026-08-01',
        ]);

        $year2 = $this->makeAcademicYear($university, '2027/2028', '2027-09-01', '2028-06-30', 'upcoming');
        $semester3 = $this->makeSemester($university, $year2, 'Semester 1', 1, '2027-09-01', '2027-12-31');
        $this->enrollStudentIn($student, $semester3);

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('fully invoiced, but they have an active enrollment in a semester beyond that plan', false);
    }

    public function test_finance_page_does_not_warn_when_the_student_has_not_exceeded_the_plan(): void
    {
        $admin = $this->makeSuperAdmin();
        $university = $this->makeUniversity(University::TYPE_UNIVERSITY);
        $student = $this->makeStudentFor($university);
        $year1 = $this->makeAcademicYear($university, '2026/2027', '2026-09-01', '2027-06-30');
        $semester1 = $this->makeSemester($university, $year1, 'Semester 1', 1, '2026-09-01', '2026-12-31');

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '8000000',
            'currency' => 'IQD',
            'status' => 'pending',
            'reference' => 'Full program tuition',
            'payment_plan' => 'semester',
            'semester_ids' => [$semester1->id],
            'installment_count' => 8,
            'transaction_date' => '2026-08-01',
        ]);
        $this->enrollStudentIn($student, $semester1);

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertDontSee('fully invoiced, but they have an active enrollment', false);
    }
}
