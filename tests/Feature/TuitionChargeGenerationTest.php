<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\FinanceTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionAgreement;
use App\Models\TuitionChargeLine;
use App\Models\TuitionRate;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuitionChargeGenerationTest extends TestCase
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

    private function makeFinanceUser(string $roleName, array $permissionNames, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => str($roleName)->replace('_', ' ')->title()]);

        $user = User::factory()->create($attributes);
        $user->roles()->attach($role);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => str($permissionName)->replace('.', ' ')->title()]
            );
            $user->permissionOverrides()->attach($permission, ['effect' => 'grant']);
        }

        return $user;
    }

    private function organization(string $suffix): array
    {
        $university = University::create(['name' => "University {$suffix}", 'code' => "U{$suffix}"]);
        $department = Department::create(['university_id' => $university->id, 'name' => "Department {$suffix}"]);

        return [$university, $department];
    }

    private function makeStudent(University $university, Department $department, array $attributes = []): Student
    {
        return Student::factory()->create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'status' => 'Active',
        ], $attributes));
    }

    private function makeAcademicYear(University $university, array $attributes = []): AcademicYear
    {
        return AcademicYear::create(array_merge([
            'university_id' => $university->id,
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ], $attributes));
    }

    private function makeSemester(University $university, AcademicYear $academicYear, array $attributes = []): Semester
    {
        return Semester::create(array_merge([
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Fall',
            'academic_year' => $academicYear->name,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
        ], $attributes));
    }

    private function enroll(Student $student, Semester $semester, float $credits, array $enrollmentAttributes = []): Enrollment
    {
        $course = Course::factory()->create([
            'department_id' => $student->department_id,
            'credits' => $credits,
        ]);
        $section = CourseSection::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'section_code' => 'S'.random_int(1000, 9999),
            'capacity' => 30,
            'status' => 'active',
        ]);

        return Enrollment::create(array_merge([
            'student_id' => $student->id,
            'course_section_id' => $section->id,
            'status' => 'enrolled',
            'enrolled_at' => now()->toDateString(),
        ], $enrollmentAttributes));
    }

    public function test_generates_invoice_from_active_enrollments_using_department_rate(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('A');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 50000,
        ]);
        $this->enroll($student, $semester, 3.0);
        $this->enroll($student, $semester, 4.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('350000.00', $invoice->amount);
        $this->assertSame('posted', $invoice->posting_status);
        $this->assertSame($semester->id, $invoice->semester_id);
        $this->assertSame(2, TuitionChargeLine::where('finance_transaction_id', $invoice->id)->count());

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('per_credit', $agreement->payment_method);
        $this->assertSame('350000.00', $agreement->total_amount);
    }

    public function test_generating_invoice_for_a_scholarship_student_automatically_applies_the_discount(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('SCH');
        $student = $this->makeStudent($university, $department, ['scholarship_percentage' => 70]);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 50000,
        ]);
        $this->enroll($student, $semester, 4.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('200000.00', $invoice->amount);

        $scholarship = FinanceTransaction::where('invoice_transaction_id', $invoice->id)
            ->where('type', 'scholarship')
            ->where('reference', 'AUTO-SCHOLARSHIP')
            ->firstOrFail();
        $this->assertSame('140000.00', $scholarship->amount);
        $this->assertSame('posted', $scholarship->posting_status);
        $this->assertSame('60000.00', $scholarship->balance_after);

        $this->assertSame('partial', $invoice->fresh()->payment_status);
    }

    public function test_student_without_a_scholarship_gets_no_automatic_credit(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('NOSCH');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 50000,
        ]);
        $this->enroll($student, $semester, 4.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertSame(0, FinanceTransaction::where('student_id', $student->id)->where('type', 'scholarship')->count());
    }

    public function test_flat_rate_department_charges_a_fixed_amount_regardless_of_credits(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('FLAT');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'pricing_type' => 'flat',
            'flat_amount' => 900000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        // The rate is the full program's tuition (8 semesters for a university), billed as an even share each semester.
        $this->assertSame('112500.00', $invoice->amount);
        $this->assertSame('posted', $invoice->posting_status);
        $this->assertSame(0, TuitionChargeLine::where('finance_transaction_id', $invoice->id)->count());
    }

    public function test_flat_rate_is_not_charged_twice_for_the_same_semester(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('FLATTWICE');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'pricing_type' => 'flat',
            'flat_amount' => 900000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $chargeRoute = fn () => $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ]);

        $chargeRoute()->assertRedirect(route('finance.students.show', $student));
        $this->enroll($student, $semester, 4.0);
        $chargeRoute()->assertRedirect(route('finance.students.show', $student));

        $this->assertSame(1, FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->count());
    }

    public function test_retake_enrollment_is_billed_like_any_other_course(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('RETAKE');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $retakeEnrollment = $this->enroll($student, $semester, 3.0, ['is_retake' => true]);

        $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('30000.00', $invoice->amount);
        $line = TuitionChargeLine::where('finance_transaction_id', $invoice->id)->firstOrFail();
        $this->assertSame($retakeEnrollment->id, $line->enrollment_id);
        $this->assertTrue($line->is_retake);
    }

    public function test_regenerating_does_not_double_charge_already_billed_enrollments(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('DUP');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin);
        $this->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect();

        $this->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-06',
        ])->assertRedirect();

        $this->assertSame(1, FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->count());
    }

    public function test_regenerating_after_late_enrollment_add_creates_supplemental_invoice_for_delta_only(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('DELTA');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin);
        $this->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect();

        $this->enroll($student, $semester, 4.0);

        $this->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-10',
        ])->assertRedirect();

        $invoices = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->orderBy('id')->get();
        $this->assertCount(2, $invoices);
        $this->assertSame('30000.00', $invoices->first()->amount);
        $this->assertSame('40000.00', $invoices->last()->amount);

        $agreement = TuitionAgreement::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('70000.00', $agreement->total_amount);
    }

    public function test_missing_tuition_rate_fails_with_clear_message(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('NORATE');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_financer_can_enter_a_manual_amount_when_no_tuition_rate_is_configured(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('MANUAL');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        $this->enroll($student, $semester, 3.0);
        $this->enroll($student, $semester, 4.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
                'amount' => '500000',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('500000.00', $invoice->amount);

        $lines = TuitionChargeLine::where('finance_transaction_id', $invoice->id)->get();
        $this->assertSame(2, $lines->count());
        $this->assertSame('500000.00', number_format((float) $lines->sum('amount'), 2, '.', ''));
        $this->assertNull($lines->first()->tuition_rate_id);
    }

    public function test_manual_amount_overrides_the_computed_rate_when_both_are_available(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('OVERRIDE');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 50000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
            'amount' => '999999.99',
        ])->assertRedirect(route('finance.students.show', $student));

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('999999.99', $invoice->amount);
    }

    public function test_department_scoped_rate_is_selected_over_other_department_rate(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('DEPTA');
        $otherDepartment = Department::create(['university_id' => $university->id, 'name' => 'Other Department']);
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 50000,
        ]);
        TuitionRate::create([
            'department_id' => $otherDepartment->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 999999,
        ]);
        $this->enroll($student, $semester, 2.0);

        $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect();

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('100000.00', $invoice->amount);
    }

    public function test_cannot_generate_charges_for_closed_academic_year(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('CLOSED');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university, ['status' => 'closed']);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_waitlisted_and_dropped_enrollments_are_excluded_from_charge(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('EXCL');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($student, $semester, 3.0, ['status' => 'waitlisted']);
        $this->enroll($student, $semester, 5.0, ['status' => 'dropped']);
        $this->enroll($student, $semester, 2.0);

        $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect();

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();
        $this->assertSame('20000.00', $invoice->amount);
    }

    public function test_legacy_manual_agreement_blocks_per_credit_generation_for_same_year(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('LEGACY');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        TuitionAgreement::create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'created_by' => $admin->id,
            'payment_method' => 'full',
            'total_amount' => 1000000,
            'currency' => 'IQD',
            'status' => 'active',
            'agreed_at' => '2026-09-01',
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error');

        $this->assertSame(1, TuitionAgreement::where('student_id', $student->id)->count());
    }

    public function test_voiding_generated_invoice_frees_its_enrollments_for_recharge(): void
    {
        $admin = $this->makeSuperAdmin();
        [$university, $department] = $this->organization('VOID');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($student, $semester, 3.0);

        $this->actingAs($admin)->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-05',
        ])->assertRedirect();

        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->firstOrFail();

        $this->post(route('finance.transactions.void', $invoice), ['notes' => 'Voided for test'])
            ->assertRedirect();

        $this->post(route('finance.students.tuition-charges.store', $student), [
            'semester_id' => $semester->id,
            'currency' => 'IQD',
            'transaction_date' => '2026-09-11',
        ])->assertRedirect();

        $this->assertSame(2, FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->count());
    }

    public function test_department_scoped_accountant_cannot_generate_or_view_charges_outside_their_department(): void
    {
        [$university, $department] = $this->organization('SCOPEA');
        $otherDepartment = Department::create(['university_id' => $university->id, 'name' => 'Other Scoped Department']);
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice'], [
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $outsideStudent = $this->makeStudent($university, $otherDepartment);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        TuitionRate::create([
            'department_id' => $otherDepartment->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'rate_per_credit' => 10000,
        ]);
        $this->enroll($outsideStudent, $semester, 3.0);

        $this->actingAs($accountant)
            ->get(route('finance.students.show', $outsideStudent))
            ->assertNotFound();

        $this->actingAs($accountant)
            ->post(route('finance.students.tuition-charges.store', $outsideStudent), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('finance_transactions', 0);
    }

    public function test_generate_tuition_charge_route_requires_finance_create_invoice_permission(): void
    {
        [$university, $department] = $this->organization('NOPERM');
        $student = $this->makeStudent($university, $department);
        $academicYear = $this->makeAcademicYear($university);
        $semester = $this->makeSemester($university, $academicYear);
        $viewOnlyUser = $this->makeFinanceUser('accountant', ['finance.view'], [
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($viewOnlyUser)
            ->post(route('finance.students.tuition-charges.store', $student), [
                'semester_id' => $semester->id,
                'currency' => 'IQD',
                'transaction_date' => '2026-09-05',
            ])
            ->assertForbidden();
    }
}
