<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TuitionRate;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_student_creates_or_links_student_user_account(): void
    {
        $superAdmin = Role::create([
            'name' => 'super_administrator',
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create([
            'name' => 'BND University',
            'code' => 'BND',
            'email' => 'info@bnd.edu',
        ]);

        $department = Department::create([
            'university_id' => $university->id,
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Student Test',
                'student_id' => 'STD-4001',
                'email' => 'student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
            ])
            ->assertRedirect('/students');

        $studentUser = User::where('email', 'student@example.com')->first();

        $this->assertNotNull($studentUser);
        $this->assertTrue(Hash::check('Temporary123', $studentUser->password));
        $this->assertTrue($studentUser->must_change_password);
        $this->assertTrue($studentUser->roles()->where('name', 'student')->exists());
    }

    public function test_registrar_can_record_the_tuition_plan_agreed_at_intake(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Plan Student',
                'email' => 'plan.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'semester',
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'email' => 'plan.student@example.com',
            'preferred_payment_method' => 'semester',
        ]);
    }

    public function test_full_payment_plan_is_accepted_at_intake(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Full Plan Student',
                'email' => 'full.plan.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'full',
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'email' => 'full.plan.student@example.com',
            'preferred_payment_method' => 'full',
        ]);
    }

    public function test_per_credit_value_is_no_longer_accepted_at_intake(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Per Credit Legacy Student',
                'email' => 'per.credit.legacy.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'per_credit',
            ])
            ->assertSessionHasErrors('preferred_payment_method');

        $this->assertDatabaseMissing('students', [
            'email' => 'per.credit.legacy.student@example.com',
        ]);
    }

    public function test_installment_count_is_not_collected_at_intake(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Installment Student',
                'email' => 'installment.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'semester',
                'preferred_installment_count' => 8,
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'email' => 'installment.student@example.com',
            'preferred_payment_method' => 'semester',
            'preferred_installment_count' => null,
        ]);
    }

    public function test_registrar_can_record_a_scholarship_percentage_at_intake(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Scholarship Student',
                'email' => 'scholarship.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'scholarship_percentage' => 70,
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'email' => 'scholarship.student@example.com',
            'scholarship_percentage' => '70.00',
        ]);
    }

    public function test_registering_a_semester_plan_student_in_a_flat_priced_department_charges_immediately(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Business Administration', 'code' => 'BA']);
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);
        $semester = Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->name,
            'name' => 'Semester 1',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
        ]);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'pricing_type' => 'flat',
            'flat_amount' => 400000,
        ]);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Flat Plan Student',
                'email' => 'flat.plan.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'semester',
            ])
            ->assertRedirect('/students');

        $student = \App\Models\Student::where('email', 'flat.plan.student@example.com')->firstOrFail();
        $invoice = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->first();
        $this->assertNotNull($invoice, 'Registering a semester-plan student in a flat-priced department should immediately create the tuition invoice.');
        // The rate is the full program's tuition (8 semesters for a university), billed as an even share each semester.
        $this->assertSame('50000.00', $invoice->amount);
        $this->assertSame($semester->id, $invoice->semester_id);
    }

    public function test_registering_a_full_plan_student_in_a_flat_priced_department_charges_the_whole_program_amount_once(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Business Administration', 'code' => 'BA']);
        $academicYear = AcademicYear::create(['university_id' => $university->id, 'name' => '2026/2027', 'status' => 'active']);
        Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->name,
            'name' => 'Semester 1',
            'term_type' => 'regular',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
        ]);
        Semester::create([
            'university_id' => $university->id,
            'academic_year_id' => $academicYear->id,
            'academic_year' => $academicYear->name,
            'name' => 'Semester 2',
            'term_type' => 'regular',
            'sequence' => 2,
            'start_date' => '2027-02-01',
            'end_date' => '2027-06-30',
        ]);
        TuitionRate::create([
            'department_id' => $department->id,
            'academic_year_id' => $academicYear->id,
            'currency' => 'IQD',
            'pricing_type' => 'flat',
            'flat_amount' => 400000,
        ]);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Full Plan Flat Student',
                'email' => 'full.plan.flat.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'preferred_payment_method' => 'full',
            ])
            ->assertRedirect('/students');

        $student = \App\Models\Student::where('email', 'full.plan.flat.student@example.com')->firstOrFail();
        $invoices = FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->get();
        $this->assertCount(1, $invoices, 'A full-plan student should get exactly one lump invoice for the whole program, regardless of how many semesters currently exist.');
        // The rate is already the full program's tuition, so it's charged as-is, not multiplied by the semester count.
        $this->assertSame('400000.00', $invoices->first()->amount);
        $this->assertNull($invoices->first()->semester_id);
    }

    public function test_scholarship_percentage_over_100_is_rejected(): void
    {
        $superAdmin = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($superAdmin->id);

        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science', 'code' => 'CS']);

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Invalid Scholarship Student',
                'email' => 'invalid.scholarship.student@example.com',
                'phone' => '123',
                'department_id' => $department->id,
                'status' => 'Active',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
                'scholarship_percentage' => 150,
            ])
            ->assertSessionHasErrors('scholarship_percentage');

        $this->assertDatabaseMissing('students', [
            'email' => 'invalid.scholarship.student@example.com',
        ]);
    }
}
