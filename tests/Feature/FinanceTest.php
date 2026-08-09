<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AppNotification;
use App\Models\College;
use App\Models\Department;
use App\Models\FinanceTransaction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionAgreement;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceTest extends TestCase
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

    private function makeStudent(array $attributes = []): Student
    {
        $university = University::create(['name' => 'BND University', 'code' => 'BND']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Computer Science']);

        return Student::create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'BND-4001',
            'full_name' => 'Sara Finance',
            'email' => 'sara.finance@example.com',
            'phone' => '07701234567',
            'status' => 'Active',
        ], $attributes));
    }

    private function makeFinanceUser(string $roleName, array $permissionNames): User
    {
        $role = Role::create(['name' => $roleName, 'display_name' => str($roleName)->replace('_', ' ')->title()]);

        $user = User::factory()->create();
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

    public function test_finance_can_search_students_by_name_email_or_id(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->get('/finance?q=sara.finance@example.com')
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee($student->student_id)
            ->assertSee($student->email);
    }

    public function test_finance_can_record_student_transaction(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->post('/finance/transactions', [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '1250000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'INV-2026-001',
                'academic_year' => '2026/2027',
                'transaction_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => 'Fall tuition',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000001',
            'reference' => 'INV-2026-001',
            'balance_after' => '1250000.00',
        ]);

        $this->assertSame('1250000.00', FinanceTransaction::first()->amount);
    }

    public function test_finance_can_split_tuition_invoice_by_semesters(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $fall = Semester::create([
            'university_id' => $student->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
        ]);
        $spring = Semester::create([
            'university_id' => $student->university_id,
            'name' => 'Spring',
            'academic_year' => '2026/2027',
            'start_date' => '2027-02-01',
            'end_date' => '2027-06-30',
        ]);

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '1200000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'Annual tuition',
                'payment_plan' => 'semester',
                'semester_ids' => [$fall->id, $spring->id],
                'transaction_date' => '2026-07-18',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('success', '2 semester invoices created: 600,000.00 IQD due 2026-12-31; 600,000.00 IQD due 2027-06-30.');

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '600000.00',
            'currency' => 'IQD',
            'reference' => 'Annual tuition - Fall 2026/2027',
            'academic_year' => '2026/2027',
            'due_date' => '2026-12-31 00:00:00',
        ]);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '600000.00',
            'currency' => 'IQD',
            'reference' => 'Annual tuition - Spring 2026/2027',
            'academic_year' => '2026/2027',
            'due_date' => '2027-06-30 00:00:00',
            'balance_after' => '1200000.00',
        ]);
        $this->assertSame(2, FinanceTransaction::where('student_id', $student->id)->where('type', 'invoice')->count());

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('2 semester installments: 0 paid / 2 open / 0 overdue')
            ->assertSee('Semester tuition installment');
    }

    public function test_semester_tuition_split_requires_semester_end_dates(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $semester = Semester::create([
            'university_id' => $student->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '600000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'Annual tuition',
                'payment_plan' => 'semester',
                'semester_ids' => [$semester->id],
                'transaction_date' => '2026-07-18',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHasErrors('semester_ids');

        $this->assertDatabaseMissing('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'reference' => 'Annual tuition - Fall 2026/2027',
        ]);
    }

    public function test_finance_generates_receipts_and_updates_student_balance(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post('/finance/transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1250000',
            'currency' => 'IQD',
            'status' => 'pending',
            'transaction_date' => '2026-07-09',
            'due_date' => '2026-08-09',
        ]);

        $this->actingAs($admin)
            ->post('/finance/transactions', [
                'student_id' => $student->id,
                'type' => 'payment',
                'amount' => '450000',
                'currency' => 'IQD',
                'status' => 'paid',
                'reference' => 'Cash desk',
                'transaction_date' => '2026-07-10',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'payment',
            'payment_status' => 'paid',
            'receipt_number' => 'RCT-2026-000001',
            'balance_after' => '800000.00',
        ]);
    }

    public function test_finance_statement_is_printable(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '750000',
            'balance_after' => '750000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000022',
            'transaction_date' => '2026-07-10',
        ]);

        $this->actingAs($admin)
            ->get(route('finance.statement', $student))
            ->assertOk()
            ->assertSee('Student Finance Statement')
            ->assertSee('INV-2026-000022')
            ->assertSee('750,000.00 IQD');
    }

    public function test_finance_can_export_csv_for_student(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000033',
            'reference' => 'Tuition',
            'transaction_date' => '2026-07-10',
        ]);

        $response = $this->actingAs($admin)->get(route('finance.export', ['student_id' => $student->id]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Invoice Number', $csv);
        $this->assertStringContainsString('INV-2026-000033', $csv);
        $this->assertStringContainsString('Sara Finance', $csv);
        $this->assertStringContainsString('500000.00', $csv);
    }

    public function test_finance_keeps_currency_balances_separate_and_filters_transactions(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000044',
            'academic_year' => '2026/2027',
            'transaction_date' => '2026-07-10',
            'due_date' => '2026-08-10',
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '300',
            'balance_after' => '300',
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000045',
            'academic_year' => '2026/2027',
            'transaction_date' => '2026-07-11',
            'due_date' => '2026-08-11',
        ]);

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('Outstanding Tuition')
            ->assertSee('Next Due')
            ->assertSee('Due 2026-08-10')
            ->assertSee('Filtered balance: 500,000.00 IQD / 300.00 USD')
            ->assertSee('500,000.00 IQD')
            ->assertSee('300.00 USD');

        $this->actingAs($admin)
            ->get(route('finance.students.show', [$student, 'currency' => 'USD']))
            ->assertOk()
            ->assertSee('300.00 USD')
            ->assertSee('INV-2026-000045')
            ->assertDontSee('<div class="font-medium text-gray-900">INV-2026-000044</div>', false);
    }

    public function test_finance_view_and_export_require_view_permission(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)->get(route('finance'))->assertForbidden();
        $this->actingAs($accountant)->get(route('finance.export'))->assertForbidden();

        $this->actingAs($accountant)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '100000',
                'currency' => 'IQD',
                'status' => 'pending',
                'transaction_date' => '2026-07-09',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
        ]);
    }

    public function test_finance_allocates_payments_to_invoices_and_approves_pending_records(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
        $invoice = FinanceTransaction::where('type', 'invoice')->first();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'type' => 'payment',
            'amount' => '400',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => now()->toDateString(),
        ]);
        $payment = FinanceTransaction::where('type', 'payment')->first();

        $this->actingAs($admin)
            ->post(route('finance.transactions.approve', $payment))
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'id' => $payment->id,
            'status' => 'approved',
            'payment_status' => 'paid',
            'invoice_transaction_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('finance_transactions', [
            'id' => $invoice->id,
            'payment_status' => 'partial',
        ]);
    }

    public function test_finance_void_creates_reversal_and_recalculates_invoice_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => '2026-07-09',
        ]);
        $invoice = FinanceTransaction::where('type', 'invoice')->first();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'type' => 'payment',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'paid',
            'transaction_date' => '2026-07-10',
        ]);
        $payment = FinanceTransaction::where('type', 'payment')->first();
        $this->assertSame('paid', $invoice->fresh()->payment_status);

        $this->actingAs($admin)
            ->post(route('finance.transactions.void', $payment), ['notes' => 'Wrong receipt'])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'id' => $payment->id,
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'original_transaction_id' => $payment->id,
            'type' => 'refund',
            'amount' => '1000.00',
            'currency' => 'USD',
        ]);
        $this->assertSame('open', $invoice->fresh()->payment_status);
    }

    public function test_accountant_can_send_tuition_charge_reminder_to_student(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);
        $studentUser = User::factory()->create(['email' => $student->email]);

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $accountant->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000088',
            'transaction_date' => '2026-07-18',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($accountant)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.tuition-reminders.store'), [
                'scope' => 'selected',
                'student_id' => $student->id,
                'message' => 'Please bring your tuition payment this week.',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $notification = AppNotification::where('student_id', $student->id)
            ->where('type', 'tuition_charge_reminder')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($studentUser->id, $notification->user_id);
        $this->assertSame('Tuition payment reminder', $notification->title);
        $this->assertSame(route('student.finance'), $notification->action_url);
        $this->assertStringContainsString('Please bring your tuition payment this week.', $notification->body);
        $this->assertStringContainsString('500,000.00 IQD', $notification->body);
    }

    public function test_finance_can_block_and_unblock_unpaid_student_login(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create([
            'email' => $student->email,
            'password' => 'Temporary123',
        ]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $accountant->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000089',
            'transaction_date' => '2026-07-18',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($accountant)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('Block Account');

        $this->actingAs($accountant)
            ->post(route('finance.students.account-block.store', $student), [
                'reason' => 'Tuition invoice is overdue.',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $studentUser->refresh();
        $this->assertNotNull($studentUser->account_blocked_at);
        $this->assertSame($accountant->id, $studentUser->account_blocked_by);
        $this->assertSame('Tuition invoice is overdue.', $studentUser->account_block_reason);
        $this->assertTrue(Hash::check('Temporary123', $studentUser->password));

        auth()->logout();

        $this->post(route('login'), [
            'email' => $studentUser->email,
            'password' => 'Temporary123',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->actingAs($accountant)
            ->delete(route('finance.students.account-block.destroy', $student))
            ->assertRedirect(route('finance.students.show', $student));

        $studentUser->refresh();
        $this->assertNull($studentUser->account_blocked_at);
        $this->assertNull($studentUser->account_blocked_by);
        $this->assertNull($studentUser->account_block_reason);

        auth()->logout();

        $this->post(route('login'), [
            'email' => $studentUser->email,
            'password' => 'Temporary123',
        ]);

        $this->assertAuthenticatedAs($studentUser);
    }

    public function test_finance_cannot_block_student_without_unpaid_balance(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => $student->email]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);

        $this->actingAs($accountant)
            ->post(route('finance.students.account-block.store', $student))
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error', 'This student has no overdue tuition installment to justify a finance block.');

        $this->assertNull($studentUser->fresh()->account_blocked_at);
    }

    public function test_academic_admin_needs_direct_finance_permission_for_account_block_controls(): void
    {
        $academicAdminRole = Role::create(['name' => 'administrator', 'display_name' => 'Academic Administrator']);
        $academicAdmin = User::factory()->create();
        $academicAdmin->roles()->attach($academicAdminRole);
        $viewPermission = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $blockPermission = Permission::create(['name' => 'finance.create_invoice', 'display_name' => 'Create invoices']);
        $student = $this->makeStudent();
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => $student->email]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $academicAdmin->id,
            'type' => 'invoice',
            'amount' => '500000',
            'balance_after' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000090',
            'transaction_date' => '2026-07-18',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($academicAdmin)
            ->get(route('finance.students.show', $student))
            ->assertForbidden();

        $academicAdmin->permissionOverrides()->attach($viewPermission, ['effect' => 'grant']);
        $academicAdmin->permissionOverrides()->attach($blockPermission, ['effect' => 'grant']);
        $academicAdmin->update([
            'university_id' => $student->university_id,
            'department_id' => $student->department_id,
        ]);

        $this->actingAs($academicAdmin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('Block Account');

        $this->actingAs($academicAdmin)
            ->post(route('finance.students.account-block.store', $student), [
                'reason' => 'Academic office approved finance hold.',
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $studentUser->refresh();
        $this->assertNotNull($studentUser->account_blocked_at);
        $this->assertSame($academicAdmin->id, $studentUser->account_blocked_by);

        $this->actingAs($academicAdmin)
            ->delete(route('finance.students.account-block.destroy', $student))
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertNull($studentUser->fresh()->account_blocked_at);
    }

    public function test_finance_rejects_cancelled_status_on_create(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '100000',
                'currency' => 'IQD',
                'status' => 'cancelled',
                'transaction_date' => '2026-07-09',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_finance_approve_requires_pending_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'payment',
            'amount' => '100',
            'balance_after' => '-100',
            'currency' => 'USD',
            'status' => 'approved',
            'payment_status' => 'paid',
            'receipt_number' => 'RCT-2026-009900',
            'transaction_date' => '2026-07-10',
        ]);

        $transaction = FinanceTransaction::firstOrFail();

        $this->actingAs($admin)
            ->post(route('finance.transactions.approve', $transaction))
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error', 'This finance record is no longer waiting for approval.');
    }

    public function test_finance_list_only_offers_approval_for_pending_unposted_records(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $postedInvoice = FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-TEST01',
            'transaction_date' => '2026-07-10',
        ]);
        $pendingPayment = FinanceTransaction::create([
            'student_id' => $student->id,
            'invoice_transaction_id' => $postedInvoice->id,
            'recorded_by' => $admin->id,
            'type' => 'payment',
            'amount' => '100',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'pending',
            'payment_status' => 'open',
            'receipt_number' => 'RCT-2026-TEST01',
            'transaction_date' => '2026-07-10',
        ]);

        $this->actingAs($admin)
            ->get(route('finance'))
            ->assertOk()
            ->assertDontSee(route('finance.transactions.approve', $postedInvoice), false)
            ->assertSee(route('finance.transactions.approve', $pendingPayment), false);
    }

    public function test_finance_rejects_over_allocated_invoice_payment(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => '2026-07-09',
            'due_date' => '2026-08-09',
        ]);
        $invoice = FinanceTransaction::where('type', 'invoice')->firstOrFail();

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'type' => 'payment',
            'amount' => '800',
            'currency' => 'USD',
            'status' => 'paid',
            'transaction_date' => '2026-07-10',
        ]);

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'invoice_transaction_id' => $invoice->id,
                'type' => 'payment',
                'amount' => '300',
                'currency' => 'USD',
                'status' => 'paid',
                'transaction_date' => '2026-07-11',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_accountant_can_send_tuition_reminders_to_checked_students(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $studentOne = $this->makeStudent();
        $accountant->update(['university_id' => $studentOne->university_id]);
        $studentTwo = Student::create([
            'university_id' => $studentOne->university_id,
            'department_id' => $studentOne->department_id,
            'student_id' => 'BND-4002',
            'full_name' => 'Omar Finance',
            'email' => 'omar.finance@example.com',
            'phone' => '07707654321',
            'status' => 'Active',
        ]);
        $paidStudent = Student::create([
            'university_id' => $studentOne->university_id,
            'department_id' => $studentOne->department_id,
            'student_id' => 'BND-4003',
            'full_name' => 'Paid Student',
            'email' => 'paid.finance@example.com',
            'status' => 'Active',
        ]);

        User::factory()->create(['email' => $studentOne->email]);
        User::factory()->create(['email' => $studentTwo->email]);
        User::factory()->create(['email' => $paidStudent->email]);

        foreach ([$studentOne, $studentTwo] as $student) {
            FinanceTransaction::create([
                'student_id' => $student->id,
                'recorded_by' => $accountant->id,
                'type' => 'invoice',
                'amount' => '250000',
                'balance_after' => '250000',
                'currency' => 'IQD',
                'status' => 'pending',
                'payment_status' => 'open',
                'invoice_number' => 'INV-2026-00009'.$student->id,
                'transaction_date' => '2026-07-18',
            ]);
        }

        FinanceTransaction::create([
            'student_id' => $paidStudent->id,
            'recorded_by' => $accountant->id,
            'type' => 'invoice',
            'amount' => '250000',
            'balance_after' => '0',
            'currency' => 'IQD',
            'status' => 'approved',
            'payment_status' => 'paid',
            'invoice_number' => 'INV-2026-000099',
            'transaction_date' => '2026-07-18',
        ]);

        $this->actingAs($accountant)
            ->post(route('finance.tuition-reminders.store'), [
                'scope' => 'selected_students',
                'student_ids' => [$studentOne->id, $studentTwo->id, $paidStudent->id],
                'message' => 'Please visit accounting.',
            ])
            ->assertRedirect(route('finance'));

        $this->assertDatabaseHas('app_notifications', [
            'student_id' => $studentOne->id,
            'type' => 'tuition_charge_reminder',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'student_id' => $studentTwo->id,
            'type' => 'tuition_charge_reminder',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'student_id' => $paidStudent->id,
            'type' => 'tuition_charge_reminder',
        ]);
    }

    public function test_tuition_reminder_page_filters_students_and_sends_back_to_page(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $studentOne = $this->makeStudent();
        $accountant->update(['university_id' => $studentOne->university_id]);
        $studentTwo = Student::create([
            'university_id' => $studentOne->university_id,
            'department_id' => $studentOne->department_id,
            'student_id' => 'BND-4010',
            'full_name' => 'Dollar Balance',
            'email' => 'dollar.balance@example.com',
            'status' => 'Active',
        ]);

        User::factory()->create(['email' => $studentTwo->email]);

        FinanceTransaction::create([
            'student_id' => $studentOne->id,
            'recorded_by' => $accountant->id,
            'type' => 'invoice',
            'amount' => '300000',
            'balance_after' => '300000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000120',
            'academic_year' => '2026/2027',
            'transaction_date' => '2026-07-18',
            'due_date' => '2026-08-01',
        ]);
        FinanceTransaction::create([
            'student_id' => $studentTwo->id,
            'recorded_by' => $accountant->id,
            'type' => 'invoice',
            'amount' => '900',
            'balance_after' => '900',
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'open',
            'invoice_number' => 'INV-2026-000121',
            'academic_year' => '2026/2027',
            'transaction_date' => '2026-07-18',
            'due_date' => '2026-08-02',
        ]);

        $this->actingAs($accountant)
            ->get(route('finance'))
            ->assertOk()
            ->assertSee(route('finance.tuition-reminders.index'), false);

        $this->actingAs($accountant)
            ->get(route('finance.tuition-reminders.index', ['currency' => 'USD']))
            ->assertOk()
            ->assertSee('Tuition Reminder')
            ->assertSee('Dollar Balance')
            ->assertSee('900.00 USD')
            ->assertDontSee('Sara Finance');

        $this->actingAs($accountant)
            ->post(route('finance.tuition-reminders.store'), [
                'return_to' => 'tuition-reminders',
                'scope' => 'selected_students',
                'student_ids' => [$studentTwo->id],
                'currency' => 'USD',
                'message' => 'Bring the USD tuition payment.',
            ])
            ->assertRedirect(route('finance.tuition-reminders.index', ['currency' => 'USD']));

        $this->assertDatabaseHas('app_notifications', [
            'student_id' => $studentTwo->id,
            'type' => 'tuition_charge_reminder',
            'title' => 'Tuition payment reminder',
        ]);
    }

    public function test_pending_payment_does_not_change_balance_until_another_user_approves_it(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $recorder = $this->makeFinanceUser('accountant', ['finance.view', 'finance.record_payment', 'finance.approve_payment']);
        $approver = $this->makeFinanceUser('chief_accountant', ['finance.view', 'finance.approve_payment']);
        $recorder->update(['university_id' => $student->university_id]);
        $approver->update(['university_id' => $student->university_id]);

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => now()->toDateString(),
        ]);
        $invoice = FinanceTransaction::where('type', 'invoice')->firstOrFail();

        $this->actingAs($recorder)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'type' => 'payment',
            'amount' => '400',
            'currency' => 'USD',
            'status' => 'pending',
            'transaction_date' => now()->toDateString(),
        ]);
        $payment = FinanceTransaction::where('type', 'payment')->firstOrFail();

        $this->assertSame('pending', $payment->posting_status);
        $this->assertNull($payment->balance_after);
        $this->assertSame('open', $invoice->fresh()->payment_status);

        $this->actingAs($recorder)
            ->post(route('finance.transactions.approve', $payment))
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error', 'A finance record must be approved by a different authorized user.');

        $this->actingAs($approver)
            ->post(route('finance.transactions.approve', $payment))
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'id' => $payment->id,
            'posting_status' => 'posted',
            'balance_after' => '600.00',
        ]);
        $this->assertSame('partial', $invoice->fresh()->payment_status);
    }

    public function test_finance_approvals_page_is_scoped_and_enforces_separation_of_duties(): void
    {
        $student = $this->makeStudent();
        $approver = $this->makeFinanceUser('chief_accountant', ['finance.view', 'finance.approve_payment']);
        $approver->update(['university_id' => $student->university_id]);
        $recorder = User::factory()->create(['university_id' => $student->university_id]);

        $visiblePayment = FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $recorder->id,
            'type' => 'payment',
            'amount' => '250',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'pending',
            'payment_status' => 'open',
            'receipt_number' => 'RCT-APPROVAL-VISIBLE',
            'transaction_date' => now()->toDateString(),
        ]);
        $ownPayment = FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $approver->id,
            'type' => 'payment',
            'amount' => '100',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'pending',
            'payment_status' => 'open',
            'receipt_number' => 'RCT-APPROVAL-OWN',
            'transaction_date' => now()->toDateString(),
        ]);

        $hiddenUniversity = University::create(['name' => 'Hidden Finance University', 'code' => 'HFU']);
        $hiddenDepartment = Department::create(['university_id' => $hiddenUniversity->id, 'name' => 'Hidden Finance Department']);
        $hiddenStudent = Student::create([
            'university_id' => $hiddenUniversity->id,
            'department_id' => $hiddenDepartment->id,
            'student_id' => 'HIDDEN-APPROVAL',
            'full_name' => 'Hidden Approval Student',
            'email' => 'hidden.approval@example.com',
            'status' => 'Active',
        ]);
        $hiddenPayment = FinanceTransaction::create([
            'student_id' => $hiddenStudent->id,
            'recorded_by' => $recorder->id,
            'type' => 'payment',
            'amount' => '900',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'pending',
            'payment_status' => 'open',
            'receipt_number' => 'RCT-APPROVAL-HIDDEN',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($approver)
            ->get(route('finance.approvals.index'))
            ->assertOk()
            ->assertSee('Finance Approvals')
            ->assertSee($student->full_name)
            ->assertSee(route('finance.transactions.approve', $visiblePayment), false)
            ->assertDontSee(route('finance.transactions.approve', $ownPayment), false)
            ->assertSee('Different approver required')
            ->assertDontSee($hiddenStudent->full_name)
            ->assertDontSee(route('finance.transactions.approve', $hiddenPayment), false);

        $this->actingAs($approver)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertSee(route('finance.approvals.index'), false);

        $this->actingAs($approver)
            ->post(route('finance.transactions.approve', $visiblePayment), [
                'return_to' => 'approvals',
                'currency' => 'USD',
                'sort' => 'oldest',
            ])
            ->assertRedirect(route('finance.approvals.index', ['currency' => 'USD', 'sort' => 'oldest']))
            ->assertSessionHas('success', 'Finance record approved.');

        $this->assertDatabaseHas('finance_transactions', [
            'id' => $visiblePayment->id,
            'status' => 'approved',
            'posting_status' => 'posted',
        ]);
    }

    public function test_finance_approvals_page_requires_approval_permission(): void
    {
        $student = $this->makeStudent();
        $accountant = $this->makeFinanceUser('accountant', ['finance.view']);
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)
            ->get(route('finance.approvals.index'))
            ->assertForbidden();
    }

    public function test_full_tuition_payment_creates_agreement_invoice_and_receipt(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $academicYear = AcademicYear::create([
            'university_id' => $student->university_id,
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '1500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'payment_plan' => 'full',
            'collect_now' => '1',
            'academic_year_id' => $academicYear->id,
            'transaction_date' => '2026-09-01',
        ])->assertRedirect(route('finance.students.show', $student));

        $agreement = TuitionAgreement::firstOrFail();
        $this->assertSame('full', $agreement->payment_method);
        $this->assertSame('completed', $agreement->status);
        $this->assertSame(2, $agreement->transactions()->count());
        $this->assertDatabaseHas('finance_transactions', [
            'tuition_agreement_id' => $agreement->id,
            'type' => 'payment',
            'posting_status' => 'posted',
            'receipt_number' => 'RCT-2026-000001',
        ]);
        $this->assertSame('paid', $agreement->transactions()->where('type', 'invoice')->firstOrFail()->payment_status);
    }

    public function test_semester_tuition_plan_can_span_multiple_academic_years(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $first = Semester::create([
            'university_id' => $student->university_id,
            'name' => 'Fall',
            'academic_year' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
        ]);
        $second = Semester::create([
            'university_id' => $student->university_id,
            'name' => 'Spring',
            'academic_year' => '2027/2028',
            'start_date' => '2028-02-01',
            'end_date' => '2028-06-30',
        ]);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => '1200000',
                'currency' => 'IQD',
                'status' => 'pending',
                'reference' => 'Whole-program tuition',
                'payment_plan' => 'semester',
                'semester_ids' => [$first->id, $second->id],
                'transaction_date' => '2026-08-01',
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '600000.00',
            'reference' => 'Whole-program tuition - Fall 2026/2027',
            'academic_year' => '2026/2027',
        ]);
        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '600000.00',
            'reference' => 'Whole-program tuition - Spring 2027/2028',
            'academic_year' => '2027/2028',
        ]);

        $agreement = TuitionAgreement::firstOrFail();
        $this->assertSame('semester', $agreement->payment_method);
        $this->assertSame('2026/2027', $agreement->academicYear?->name);
        $this->assertSame(2, $agreement->transactions()->count());
    }

    public function test_student_can_only_print_own_posted_receipt(): void
    {
        $student = $this->makeStudent();
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => $student->email]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);
        $payment = FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'payment',
            'amount' => '100000',
            'currency' => 'IQD',
            'status' => 'paid',
            'posting_status' => 'posted',
            'payment_status' => 'paid',
            'receipt_number' => 'RCT-2026-000900',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($studentUser)
            ->get(route('student.finance.receipt', $payment))
            ->assertOk()
            ->assertSee('RCT-2026-000900');

        $otherUser = User::factory()->create();
        $otherUser->roles()->attach($studentRole);
        $this->actingAs($otherUser)
            ->get(route('student.finance.receipt', $payment))
            ->assertNotFound();
    }

    public function test_scoped_accountant_only_sees_students_in_assigned_university(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $visible = $this->makeStudent();
        $otherUniversity = University::create(['name' => 'Other University', 'code' => 'OTHER']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Law']);
        $hidden = Student::create([
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'OTHER-1',
            'full_name' => 'Hidden Finance Student',
            'email' => 'hidden.finance@example.com',
            'status' => 'Active',
        ]);
        $accountant->update(['university_id' => $visible->university_id]);

        $this->actingAs($accountant)
            ->get(route('finance', ['q' => 'Finance']))
            ->assertOk()
            ->assertSee($visible->full_name)
            ->assertDontSee($hidden->full_name);

        $this->actingAs($accountant)
            ->get(route('finance.students.show', $hidden))
            ->assertNotFound();
    }

    public function test_administrator_with_direct_finance_grant_and_no_assigned_organization_sees_no_students(): void
    {
        $role = Role::create(['name' => 'administrator', 'display_name' => 'Administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);
        $permission = Permission::firstOrCreate(['name' => 'finance.view'], ['display_name' => 'View finance']);
        $admin->permissionOverrides()->attach($permission, ['effect' => 'grant']);

        $studentA = $this->makeStudent();
        $otherUniversity = University::create(['name' => 'Other University', 'code' => 'OTHER-ADM']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Law']);
        $studentB = Student::create([
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'OTHER-ADM-1',
            'full_name' => 'Second University Student',
            'email' => 'second.university.student@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)->get(route('finance.students.show', $studentA))->assertNotFound();
        $this->actingAs($admin)->get(route('finance.students.show', $studentB))->assertNotFound();
    }

    public function test_overall_payment_status_does_not_mix_currency_balances(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => '500000',
            'currency' => 'IQD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'open',
            'transaction_date' => now()->toDateString(),
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'scholarship',
            'amount' => '1000',
            'currency' => 'USD',
            'status' => 'approved',
            'posting_status' => 'posted',
            'payment_status' => 'paid',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSeeInOrder(['Payment Status', 'Open']);
    }

    public function test_posted_payment_automatically_clears_only_a_finance_hold_when_overdue_is_resolved(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create([
            'email' => $student->email,
            'account_blocked_at' => now(),
            'account_blocked_by' => $admin->id,
            'account_block_reason' => 'Overdue tuition',
            'account_block_type' => 'finance',
        ]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);
        $invoice = FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => '500',
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'overdue',
            'transaction_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'type' => 'payment',
            'amount' => '500',
            'currency' => 'USD',
            'status' => 'paid',
            'transaction_date' => now()->toDateString(),
        ])->assertRedirect(route('finance.students.show', $student));

        $studentUser->refresh();
        $this->assertNull($studentUser->account_blocked_at);
        $this->assertNull($studentUser->account_block_type);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
    }

    public function test_student_finance_workspace_does_not_show_another_students_transactions(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $otherStudent = Student::create([
            'university_id' => $student->university_id,
            'department_id' => $student->department_id,
            'student_id' => 'BND-4002',
            'full_name' => 'Other Finance Student',
            'email' => 'other.finance@example.com',
            'status' => 'Active',
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'open',
            'reference' => 'VISIBLE-STUDENT-RECORD',
            'transaction_date' => now()->toDateString(),
        ]);
        FinanceTransaction::create([
            'student_id' => $otherStudent->id,
            'type' => 'invoice',
            'amount' => 200,
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'open',
            'reference' => 'HIDDEN-STUDENT-RECORD',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee('VISIBLE-STUDENT-RECORD')
            ->assertDontSee('HIDDEN-STUDENT-RECORD');
    }

    public function test_scoped_finance_user_cannot_block_student_in_another_university(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $visible = $this->makeStudent();
        $accountant->update(['university_id' => $visible->university_id]);
        $otherUniversity = University::create(['name' => 'Scoped Out University', 'code' => 'SOU']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Scoped Out Department']);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create(['email' => 'scoped.out@example.com']);
        $studentUser->roles()->attach($studentRole);
        $hidden = Student::create([
            'user_id' => $studentUser->id,
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'SOU-1',
            'full_name' => 'Scoped Out Student',
            'email' => $studentUser->email,
            'status' => 'Active',
        ]);

        $this->actingAs($accountant)
            ->post(route('finance.students.account-block.store', $hidden), ['reason' => 'Overdue tuition'])
            ->assertNotFound();

        $this->assertNull($studentUser->fresh()->account_blocked_at);
    }

    public function test_finance_workspace_does_not_remove_a_non_finance_account_hold(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);
        $studentRole = Role::create(['name' => 'student', 'display_name' => 'Student User']);
        $studentUser = User::factory()->create([
            'email' => $student->email,
            'account_blocked_at' => now(),
            'account_block_reason' => 'Academic conduct hold',
            'account_block_type' => 'academic',
        ]);
        $studentUser->roles()->attach($studentRole);
        $student->update(['user_id' => $studentUser->id]);

        $this->actingAs($accountant)
            ->from(route('finance.students.show', $student))
            ->delete(route('finance.students.account-block.destroy', $student))
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHas('error');

        $studentUser->refresh();
        $this->assertNotNull($studentUser->account_blocked_at);
        $this->assertSame('academic', $studentUser->account_block_type);
    }

    public function test_non_super_finance_user_cannot_post_a_payment_without_approval(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.record_payment']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'payment',
                'amount' => 100,
                'currency' => 'USD',
                'status' => 'paid',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'payment',
        ]);
    }

    public function test_collecting_tuition_now_requires_payment_recording_permission(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => 500,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_plan' => 'full',
                'collect_now' => 1,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHasErrors('collect_now');

        $this->assertDatabaseMissing('tuition_agreements', ['student_id' => $student->id]);
    }

    public function test_non_super_collected_tuition_payment_waits_for_independent_approval(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice', 'finance.record_payment']);
        $student = $this->makeStudent();
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => 500,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_plan' => 'full',
                'collect_now' => 1,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'payment',
            'status' => 'pending',
            'posting_status' => 'pending',
            'approved_by' => null,
        ]);
        $this->assertDatabaseHas('tuition_agreements', [
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_finance_administrator_can_post_a_payment_immediately_without_approval(): void
    {
        $financeAdministrator = $this->makeFinanceUser('chief_accountant', ['finance.view', 'finance.record_payment']);
        $student = $this->makeStudent();
        $financeAdministrator->update(['university_id' => $student->university_id]);

        $this->actingAs($financeAdministrator)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'payment',
                'amount' => 100,
                'currency' => 'USD',
                'status' => 'paid',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'payment',
            'status' => 'paid',
            'posting_status' => 'posted',
            'payment_status' => 'paid',
        ]);
    }

    public function test_finance_administrator_collected_tuition_payment_posts_immediately(): void
    {
        $financeAdministrator = $this->makeFinanceUser('chief_accountant', ['finance.view', 'finance.create_invoice', 'finance.record_payment']);
        $student = $this->makeStudent();
        $financeAdministrator->update(['university_id' => $student->university_id]);

        $this->actingAs($financeAdministrator)
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'invoice',
                'amount' => 500,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_plan' => 'full',
                'collect_now' => 1,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.show', $student));

        $this->assertDatabaseHas('finance_transactions', [
            'student_id' => $student->id,
            'type' => 'payment',
            'status' => 'approved',
            'posting_status' => 'posted',
            'payment_status' => 'paid',
            'approved_by' => $financeAdministrator->id,
        ]);
        $this->assertDatabaseHas('tuition_agreements', [
            'student_id' => $student->id,
            'status' => 'completed',
        ]);
    }

    public function test_invoice_with_allocated_payment_cannot_be_voided(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $invoice = FinanceTransaction::create([
            'student_id' => $student->id,
            'recorded_by' => $admin->id,
            'type' => 'invoice',
            'amount' => 500,
            'currency' => 'USD',
            'status' => 'pending',
            'posting_status' => 'posted',
            'payment_status' => 'partial',
            'transaction_date' => now()->toDateString(),
        ]);
        FinanceTransaction::create([
            'student_id' => $student->id,
            'invoice_transaction_id' => $invoice->id,
            'recorded_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'type' => 'payment',
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'approved',
            'posting_status' => 'posted',
            'payment_status' => 'paid',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.void', $invoice), ['notes' => 'Incorrect invoice'])
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHasErrors('transaction');

        $this->assertNotSame('cancelled', $invoice->fresh()->status);
        $this->assertDatabaseMissing('finance_transactions', ['original_transaction_id' => $invoice->id]);
    }

    public function test_student_cannot_receive_duplicate_tuition_agreements_for_one_academic_year(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();
        $academicYear = AcademicYear::create([
            'university_id' => $student->university_id,
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'status' => 'active',
        ]);
        $payload = [
            'student_id' => $student->id,
            'type' => 'invoice',
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_plan' => 'full',
            'academic_year_id' => $academicYear->id,
            'transaction_date' => now()->toDateString(),
        ];

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), $payload)
            ->assertRedirect(route('finance.students.show', $student));

        $this->actingAs($admin)
            ->from(route('finance.students.show', $student))
            ->post(route('finance.transactions.store'), $payload)
            ->assertRedirect(route('finance.students.show', $student))
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(1, TuitionAgreement::where('student_id', $student->id)->count());
    }

    public function test_finance_navigation_separates_student_finance_and_tuition_reminders(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('finance'))
            ->assertOk()
            ->assertSee('Accounting &amp; Finance', false)
            ->assertSee('Student Finance')
            ->assertSee('Tuition Reminders');
    }

    public function test_direct_finance_view_denial_overrides_finance_role_everywhere(): void
    {
        $university = University::create(['name' => 'Denied Finance University', 'code' => 'DFU']);
        $department = Department::create(['university_id' => $university->id, 'name' => 'Denied Finance Department']);
        $student = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'DFU-1',
            'full_name' => 'Denied Finance Student',
            'email' => 'denied.finance.student@example.com',
            'status' => 'Active',
        ]);
        $role = Role::create(['name' => 'accountant', 'display_name' => 'Finance Officer']);
        $viewPermission = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $invoicePermission = Permission::create(['name' => 'finance.create_invoice', 'display_name' => 'Create invoices']);
        $role->permissions()->attach([$viewPermission->id, $invoicePermission->id]);
        $accountant = User::factory()->create(['university_id' => $university->id]);
        $accountant->roles()->attach($role);
        $accountant->permissionOverrides()->attach($viewPermission, ['effect' => 'deny']);

        $this->actingAs($accountant)->get(route('finance'))->assertForbidden();
        $this->actingAs($accountant)->get(route('finance.students.show', $student))->assertForbidden();
        $this->actingAs($accountant)->get(route('finance.statement', $student))->assertForbidden();
        $this->actingAs($accountant)
            ->post(route('finance.students.account-block.store', $student), ['reason' => 'Should not be allowed'])
            ->assertForbidden();
        $this->actingAs($accountant)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Accounting &amp; Finance', false)
            ->assertDontSee('Student Finance');
    }

    public function test_direct_global_finance_grant_can_view_students_across_universities(): void
    {
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.view_global']);
        $firstStudent = $this->makeStudent();
        $otherUniversity = University::create(['name' => 'Global Finance University', 'code' => 'GFU']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Global Finance Department']);
        $otherStudent = Student::create([
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'GFU-1',
            'full_name' => 'Global Finance Student',
            'email' => 'global.finance.student@example.com',
            'status' => 'Active',
        ]);

        $this->actingAs($accountant)
            ->get(route('finance', ['q' => 'Finance']))
            ->assertOk()
            ->assertSee($firstStudent->full_name)
            ->assertSee($otherStudent->full_name);

        $this->actingAs($accountant)
            ->get(route('finance.students.show', $otherStudent))
            ->assertOk();
    }

    public function test_role_assigned_global_finance_permission_does_not_bypass_organization_scope(): void
    {
        $firstStudent = $this->makeStudent();
        $otherUniversity = University::create(['name' => 'Outside Finance University', 'code' => 'OFU']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Outside Finance Department']);
        $otherStudent = Student::create([
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'OFU-1',
            'full_name' => 'Outside Finance Student',
            'email' => 'outside.finance.student@example.com',
            'status' => 'Active',
        ]);
        $role = Role::create(['name' => 'accountant', 'display_name' => 'Finance Officer']);
        $viewPermission = Permission::create(['name' => 'finance.view', 'display_name' => 'View finance']);
        $globalPermission = Permission::firstOrCreate(['name' => 'finance.view_global'], ['display_name' => 'View global finance']);
        $role->permissions()->attach([$viewPermission->id, $globalPermission->id]);
        $accountant = User::factory()->create(['university_id' => $firstStudent->university_id]);
        $accountant->roles()->attach($role);

        $this->actingAs($accountant)
            ->get(route('finance', ['q' => 'Finance']))
            ->assertOk()
            ->assertSee($firstStudent->full_name)
            ->assertDontSee($otherStudent->full_name);
        $this->actingAs($accountant)
            ->get(route('finance.students.show', $otherStudent))
            ->assertNotFound();
    }

    public function test_college_scoped_finance_user_only_sees_students_in_assigned_college(): void
    {
        $university = University::create(['name' => 'College Scope University', 'code' => 'CSU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Science', 'code' => 'SCI']);
        $otherCollege = College::create(['university_id' => $university->id, 'name' => 'Law', 'code' => 'LAW']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Physics']);
        $otherDepartment = Department::create(['university_id' => $university->id, 'college_id' => $otherCollege->id, 'name' => 'Public Law']);
        $visible = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'CSU-1',
            'full_name' => 'Scoped College Visible',
            'email' => 'college.visible@example.com',
            'status' => 'Active',
        ]);
        $hidden = Student::create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'CSU-2',
            'full_name' => 'Scoped College Hidden',
            'email' => 'college.hidden@example.com',
            'status' => 'Active',
        ]);
        $accountant = $this->makeFinanceUser('accountant', ['finance.view']);
        $accountant->update(['university_id' => $university->id, 'college_id' => $college->id]);

        $this->actingAs($accountant)
            ->get(route('finance', ['q' => 'Scoped College']))
            ->assertOk()
            ->assertSee($visible->full_name)
            ->assertDontSee($hidden->full_name);
        $this->actingAs($accountant)
            ->get(route('finance.students.show', $hidden))
            ->assertNotFound();
    }

    public function test_department_scoped_finance_user_only_sees_students_in_assigned_department(): void
    {
        $university = University::create(['name' => 'Department Scope University', 'code' => 'DSU']);
        $college = College::create(['university_id' => $university->id, 'name' => 'Engineering', 'code' => 'ENG']);
        $department = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Software']);
        $otherDepartment = Department::create(['university_id' => $university->id, 'college_id' => $college->id, 'name' => 'Civil']);
        $visible = Student::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'DSU-1',
            'full_name' => 'Scoped Department Visible',
            'email' => 'department.visible@example.com',
            'status' => 'Active',
        ]);
        $hidden = Student::create([
            'university_id' => $university->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'DSU-2',
            'full_name' => 'Scoped Department Hidden',
            'email' => 'department.hidden@example.com',
            'status' => 'Active',
        ]);
        $accountant = $this->makeFinanceUser('accountant', ['finance.view']);
        $accountant->update([
            'university_id' => $university->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($accountant)
            ->get(route('finance', ['q' => 'Scoped Department']))
            ->assertOk()
            ->assertSee($visible->full_name)
            ->assertDontSee($hidden->full_name);
        $this->actingAs($accountant)
            ->get(route('finance.students.show', $hidden))
            ->assertNotFound();
    }

    public function test_add_finance_record_opens_on_a_dedicated_student_subpage(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->get(route('finance.students.show', $student))
            ->assertOk()
            ->assertSee(route('finance.students.records.create', $student))
            ->assertDontSee(route('finance.transactions.store'));

        $this->actingAs($admin)
            ->get(route('finance.students.records.create', $student))
            ->assertOk()
            ->assertSee('Add Finance Record')
            ->assertSee($student->full_name)
            ->assertSee('Save Finance Record')
            ->assertSee(route('finance.transactions.store'));
    }

    public function test_finance_viewer_without_write_permission_cannot_open_record_subpage(): void
    {
        $student = $this->makeStudent();
        $accountant = $this->makeFinanceUser('accountant', ['finance.view']);
        $accountant->update(['university_id' => $student->university_id]);

        $this->actingAs($accountant)
            ->get(route('finance.students.records.create', $student))
            ->assertForbidden();
    }

    public function test_finance_record_subpage_enforces_student_organization_scope(): void
    {
        $visible = $this->makeStudent();
        $otherUniversity = University::create(['name' => 'Other Record University', 'code' => 'ORU']);
        $otherDepartment = Department::create(['university_id' => $otherUniversity->id, 'name' => 'Other Record Department']);
        $hidden = Student::create([
            'university_id' => $otherUniversity->id,
            'department_id' => $otherDepartment->id,
            'student_id' => 'ORU-1',
            'full_name' => 'Hidden Finance Record Student',
            'email' => 'hidden.finance.record@example.com',
            'status' => 'Active',
        ]);
        $accountant = $this->makeFinanceUser('accountant', ['finance.view', 'finance.create_invoice']);
        $accountant->update(['university_id' => $visible->university_id]);

        $this->actingAs($accountant)
            ->get(route('finance.students.records.create', $visible))
            ->assertOk();
        $this->actingAs($accountant)
            ->get(route('finance.students.records.create', $hidden))
            ->assertNotFound();
    }

    public function test_finance_record_validation_returns_to_the_record_subpage(): void
    {
        $admin = $this->makeSuperAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->from(route('finance.students.records.create', $student))
            ->post(route('finance.transactions.store'), [
                'student_id' => $student->id,
                'type' => 'payment',
                'amount' => 0,
                'currency' => 'IQD',
                'status' => 'paid',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('finance.students.records.create', $student))
            ->assertSessionHasErrors('amount');
    }
}
