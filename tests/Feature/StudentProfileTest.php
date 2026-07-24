<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_admissions_and_emergency_profile_fields(): void
    {
        $admin = $this->makeAdmin();
        [$university, $department] = $this->makeStructure();

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Expanded Profile Student',
                'student_id' => 'STD-7001',
                'email' => 'expanded@example.com',
                'phone' => '0770000000',
                'department_id' => $department->id,
                'status' => 'Active',
                'admission_status' => 'Enrolled',
                'admission_date' => '2026-07-10',
                'admission_type' => 'Scholarship',
                'previous_school' => 'Central High School',
                'address' => 'Campus Street',
                'emergency_contact_name' => 'Dana Parent',
                'emergency_contact_relationship' => 'Mother',
                'emergency_contact_phone' => '0771234567',
                'password' => 'Temporary123',
                'password_confirmation' => 'Temporary123',
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('students', [
            'university_id' => $university->id,
            'student_id' => 'STD-7001',
            'admission_status' => 'Enrolled',
            'admission_type' => 'Scholarship',
            'previous_school' => 'Central High School',
            'emergency_contact_name' => 'Dana Parent',
        ]);
    }

    public function test_admin_can_manage_guardians_and_documents(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)
            ->post(route('students.guardians.store', $student), [
                'full_name' => 'Primary Guardian',
                'relationship' => 'Father',
                'phone' => '0777654321',
                'email' => 'guardian@example.com',
                'occupation' => 'Engineer',
                'address' => 'Family address',
                'is_primary' => '1',
            ])
            ->assertRedirect(route('students.edit', $student));

        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'full_name' => 'Primary Guardian',
            'is_primary' => true,
        ]);

        $file = UploadedFile::fake()->create('admission.pdf', 64, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('students.documents.store', $student), [
                'type' => 'Admission form',
                'title' => 'Signed admission form',
                'status' => 'Verified',
                'notes' => 'Checked by registrar',
                'document' => $file,
            ])
            ->assertRedirect(route('students.edit', $student));

        $document = StudentDocument::firstOrFail();

        $this->assertDatabaseHas('student_documents', [
            'student_id' => $student->id,
            'title' => 'Signed admission form',
            'status' => 'Verified',
            'original_name' => 'admission.pdf',
        ]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_student_can_view_and_download_own_profile_document(): void
    {
        Storage::fake('local');

        $student = $this->makeStudent(['email' => 'portal-profile@example.com']);
        $student->guardians()->create([
            'full_name' => 'Portal Guardian',
            'relationship' => 'Mother',
            'phone' => '0771111111',
            'is_primary' => true,
        ]);

        Storage::disk('local')->put('student-documents/profile.pdf', 'profile document');
        $document = $student->documents()->create([
            'type' => 'ID document',
            'title' => 'National ID',
            'file_path' => 'student-documents/profile.pdf',
            'original_name' => 'profile.pdf',
            'status' => 'Verified',
        ]);

        $studentRole = Role::create([
            'name' => 'student',
            'display_name' => 'Student User',
            'description' => 'Student role',
        ]);
        $user = User::factory()->create(['email' => $student->email]);
        $user->roles()->attach($studentRole->id);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Academic Profile')
            ->assertSee('Portal Guardian')
            ->assertSee('National ID');

        $this->actingAs($user)
            ->get(route('student-documents.download', $document))
            ->assertOk()
            ->assertDownload('profile.pdf');
    }

    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'super_administrator',
        ], [
            'display_name' => 'Super Administrator',
            'description' => 'Full access',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStructure(): array
    {
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

        return [$university, $department];
    }

    private function makeStudent(array $attributes = []): Student
    {
        [$university, $department] = $this->makeStructure();

        return Student::create(array_merge([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'student_id' => 'STD-9001',
            'full_name' => 'Profile Student',
            'email' => 'profile@example.com',
            'status' => 'Active',
            'admission_status' => 'Enrolled',
            'emergency_contact_name' => 'Emergency Person',
            'emergency_contact_relationship' => 'Parent',
            'emergency_contact_phone' => '0772222222',
        ], $attributes));
    }
}
