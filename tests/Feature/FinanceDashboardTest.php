<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\FinanceTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_role_is_sent_to_the_focused_finance_dashboard(): void
    {
        [$accountant] = $this->financeUserAndOrganization();

        $this->actingAs($accountant)
            ->get(route('dashboard'))
            ->assertRedirect(route('finance.dashboard'));

        $this->actingAs($accountant)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertSee('Finance Dashboard')
            ->assertSee('Outstanding Tuition')
            ->assertSee('Collections Trend')
            ->assertSee('Outstanding Balance by Department')
            ->assertSee('Invoice Status')
            ->assertSee('Overdue Aging')
            ->assertDontSee('Academic Structure')
            ->assertDontSee('Marks Queue');
    }

    public function test_finance_dashboard_totals_and_lists_respect_organization_scope(): void
    {
        [$accountant, $visibleUniversity, $visibleDepartment] = $this->financeUserAndOrganization();
        $visibleStudent = $this->student($visibleUniversity, $visibleDepartment, 'Visible Finance Student', 'VISIBLE-1');

        $hiddenUniversity = University::create(['name' => 'Hidden University', 'code' => 'HID']);
        $hiddenCollege = College::create(['university_id' => $hiddenUniversity->id, 'name' => 'Hidden College', 'code' => 'HC']);
        $hiddenDepartment = Department::create([
            'university_id' => $hiddenUniversity->id,
            'college_id' => $hiddenCollege->id,
            'name' => 'Hidden Department',
            'code' => 'HD',
        ]);
        $hiddenStudent = $this->student($hiddenUniversity, $hiddenDepartment, 'Hidden Finance Student', 'HIDDEN-1');

        $this->invoice($visibleStudent, 100, 'IQD', 'VISIBLE-INV');
        $this->invoice($hiddenStudent, 999, 'USD', 'HIDDEN-INV');

        $response = $this->actingAs($accountant)->get(route('finance.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Visible Finance Student')
            ->assertSee('100 IQD')
            ->assertDontSee('Hidden Finance Student')
            ->assertDontSee('999.00 USD')
            ->assertDontSee('HIDDEN-INV');
    }

    public function test_super_administrator_keeps_the_institution_dashboard(): void
    {
        $role = Role::create(['name' => 'super_administrator', 'display_name' => 'Super Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ERP Overview')
            ->assertDontSee('Finance Dashboard');
    }

    private function financeUserAndOrganization(): array
    {
        $university = University::create(['name' => 'Scoped University', 'code' => 'SCU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Scoped College', 'code' => 'SC']);
        $department = Department::create([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'name' => 'Scoped Department',
            'code' => 'SD',
        ]);
        $permission = Permission::create(['name' => 'finance.view', 'display_name' => 'View Finance']);
        $role = Role::create(['name' => 'accountant', 'display_name' => 'Finance Officer']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['university_id' => $university->id]);
        $user->roles()->attach($role);

        return [$user, $university, $department];
    }

    private function student(University $university, Department $department, string $name, string $studentId): Student
    {
        return Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => $studentId,
            'full_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'status' => 'Active',
        ]);
    }

    private function invoice(Student $student, float $amount, string $currency, string $number): FinanceTransaction
    {
        return FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'approved',
            'posting_status' => 'posted',
            'payment_status' => 'overdue',
            'invoice_number' => $number,
            'transaction_date' => today()->subMonth(),
            'due_date' => today()->subDay(),
        ]);
    }
}
