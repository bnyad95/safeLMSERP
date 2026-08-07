<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'students.view' => 'View students',
            'students.create' => 'Create students',
            'students.update' => 'Update students',
            'students.archive' => 'Archive students',
            'teachers.view' => 'View teachers',
            'teachers.create' => 'Create teachers',
            'teachers.update' => 'Update teachers',
            'teachers.archive' => 'Archive teachers',
            'courses.view' => 'View courses',
            'courses.create' => 'Create courses',
            'courses.update' => 'Update courses',
            'courses.archive' => 'Archive courses',
            'courses.assign_teacher' => 'Assign teacher to courses',
            'enrollments.view' => 'View enrollments',
            'enrollments.manage' => 'Manage enrollments and course sections',
            'timetable.view' => 'View timetables',
            'timetable.manage' => 'Manage timetables and rooms',
            'attendance.view' => 'View attendance',
            'attendance.create' => 'Create attendance',
            'attendance.update' => 'Update attendance',
            'marks.view' => 'View marks',
            'marks.enter' => 'Enter marks',
            'marks.submit' => 'Submit marks',
            'marks.review' => 'Review marks',
            'marks.approve' => 'Approve marks',
            'marks.publish' => 'Publish marks',
            'marks.request_change' => 'Request mark changes',
            'finance.view' => 'View finance data',
            'finance.view_global' => 'View finance data across all organizations',
            'finance.create_invoice' => 'Create invoices',
            'finance.record_payment' => 'Record payments',
            'finance.approve_payment' => 'Approve payments',
            'finance.record_expense' => 'Record expenses',
            'finance.approve_expense' => 'Approve expenses',
            'finance.refund' => 'Process refunds',
            'lms.view' => 'View LMS',
            'lms.create_content' => 'Create LMS content',
            'lms.create_assignment' => 'Create assignments',
            'lms.grade_assignment' => 'Grade assignments',
            'lms.manage_courses' => 'Manage LMS courses',
            'reports.academic' => 'View academic reports',
            'reports.financial' => 'View financial reports',
            'reports.audit' => 'View audit reports',
            'academic_setup.view' => 'View academic setup',
            'academic_setup.manage' => 'Manage academic setup',
            'users.create' => 'Create users',
            'users.update' => 'Update users',
            'users.assign_roles' => 'Assign user roles',
            'users.reset_password' => 'Reset user passwords',
        ];

        foreach ($permissions as $name => $displayName) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => $displayName,
                ]
            );
        }

        $roles = [
            'super_administrator' => [
                'display_name' => 'Super Administrator',
                'description' => 'Owns full system access across all universities and modules.',
                'permissions' => array_keys($permissions),
            ],
            'administrator' => [
                'display_name' => 'Academic Administrator',
                'description' => 'Manages institution-wide academic operations without system access control.',
                'permissions' => [
                    'students.view', 'students.create', 'students.update', 'students.archive',
                    'teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive',
                    'courses.view', 'courses.create', 'courses.update', 'courses.archive', 'courses.assign_teacher',
                    'enrollments.view', 'enrollments.manage', 'timetable.view', 'timetable.manage',
                    'attendance.view', 'attendance.create', 'attendance.update',
                    'marks.view',
                    'reports.academic', 'academic_setup.view', 'academic_setup.manage',
                    'lms.view',
                ],
            ],
            'university_president' => [
                'display_name' => 'President of University',
                'description' => 'Views institution-level academic analytics for university leadership.',
                'permissions' => [
                    'reports.academic',
                ],
            ],
            'university_administrator' => [
                'display_name' => 'University Administrator',
                'description' => 'Manages one university with broad administrative permissions.',
                'permissions' => [
                    'students.view', 'students.create', 'students.update',
                    'teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive',
                    'courses.view', 'courses.create', 'courses.update', 'courses.archive', 'courses.assign_teacher',
                    'enrollments.view', 'enrollments.manage', 'timetable.view', 'timetable.manage',
                    'attendance.view', 'attendance.update',
                    'marks.view',
                    'reports.academic',
                ],
            ],
            'college_administrator' => [
                'display_name' => 'College Administrator',
                'description' => 'Manages one college, departments, teachers, and students.',
                'permissions' => [
                    'students.view', 'students.create', 'students.update',
                    'teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive',
                    'courses.view', 'courses.create', 'courses.update', 'courses.archive', 'courses.assign_teacher',
                    'enrollments.view', 'enrollments.manage', 'timetable.view', 'timetable.manage',
                    'attendance.view', 'marks.view', 'reports.academic',
                ],
            ],
            'department_administrator' => [
                'display_name' => 'Department Administrator',
                'description' => 'Manages one department and oversees teaching and marks.',
                'permissions' => [
                    'students.view', 'students.update',
                    'teachers.view', 'teachers.update', 'teachers.archive',
                    'courses.view', 'courses.update', 'courses.archive', 'courses.assign_teacher',
                    'enrollments.view', 'enrollments.manage', 'timetable.view', 'timetable.manage',
                    'attendance.view', 'attendance.update',
                    'marks.view', 'reports.academic',
                ],
            ],
            'registrar' => [
                'display_name' => 'Registrar',
                'description' => 'Handles admissions, registration, and academic records.',
                'permissions' => [
                    'students.view', 'students.create', 'students.update', 'students.archive',
                    'courses.view', 'enrollments.view', 'enrollments.manage', 'timetable.view', 'reports.academic',
                ],
            ],
            'admission_officer' => [
                'display_name' => 'Admission Manager',
                'description' => 'Reviews applications and verifies admission documents.',
                'permissions' => [
                    'students.view', 'students.create', 'students.update',
                ],
            ],
            'examination_administrator' => [
                'display_name' => 'Examination Administrator',
                'description' => 'Manages examinations and results lifecycle.',
                'permissions' => [
                    'marks.view', 'marks.review', 'marks.approve', 'marks.publish', 'reports.academic',
                ],
            ],
            'examination_committee' => [
                'display_name' => 'Examination Committee',
                'description' => 'Reviews marks, approves valid submissions, and requests changes before publication.',
                'permissions' => [
                    'marks.view', 'marks.review', 'marks.approve', 'marks.request_change',
                ],
            ],
            'teacher' => [
                'display_name' => 'Instructor',
                'description' => 'Manages assigned courses, attendance, and mark entry.',
                'permissions' => [
                    'timetable.view',
                    'attendance.view', 'attendance.create', 'attendance.update',
                    'marks.view', 'marks.enter',
                    'lms.view', 'lms.create_content', 'lms.create_assignment', 'lms.grade_assignment',
                ],
            ],
            'teaching_assistant' => [
                'display_name' => 'Assistant Instructor',
                'description' => 'Assists instructor with practicals, attendance, and assignments.',
                'permissions' => [
                    'attendance.view', 'attendance.create',
                    'marks.view', 'marks.enter',
                    'lms.view', 'lms.create_content', 'lms.grade_assignment',
                ],
            ],
            'student' => [
                'display_name' => 'Student User',
                'description' => 'Views own academic and financial information.',
                'permissions' => [
                    'attendance.view', 'marks.view', 'finance.view', 'lms.view',
                ],
            ],
            'accountant' => [
                'display_name' => 'Finance Officer',
                'description' => 'Records fees, invoices, and payments.',
                'permissions' => [
                    'finance.view', 'finance.create_invoice', 'finance.record_payment', 'finance.record_expense', 'reports.financial',
                ],
            ],
            'chief_accountant' => [
                'display_name' => 'Finance Administrator',
                'description' => 'Approves transactions and manages budgets and closing.',
                'permissions' => [
                    'finance.view', 'finance.create_invoice', 'finance.record_payment',
                    'finance.approve_payment', 'finance.record_expense', 'finance.approve_expense', 'finance.refund',
                    'reports.financial',
                ],
            ],
            'hr_manager' => [
                'display_name' => 'HR Manager',
                'description' => 'Manages employees, contracts, attendance, and leave.',
                'permissions' => [
                    'teachers.view', 'teachers.create', 'teachers.update', 'teachers.archive', 'reports.academic',
                ],
            ],
            'lms_administrator' => [
                'display_name' => 'E-Learning Administrator',
                'description' => 'Administers LMS settings and online course materials.',
                'permissions' => [
                    'lms.view', 'lms.create_content', 'lms.create_assignment', 'lms.grade_assignment', 'lms.manage_courses',
                ],
            ],
            'librarian' => [
                'display_name' => 'Library Administrator',
                'description' => 'Monitors learning resources and academic document availability.',
                'permissions' => [
                    'lms.view', 'reports.academic',
                ],
            ],
            'receptionist' => [
                'display_name' => 'Front Desk User',
                'description' => 'Handles front desk requests and basic student record lookup.',
                'permissions' => [
                    'students.view',
                ],
            ],
            'parent_user' => [
                'display_name' => 'Parent User',
                'description' => 'Read-only access to linked student progress and finance summaries.',
                'permissions' => [
                    'attendance.view', 'marks.view', 'finance.view',
                ],
            ],
            'it_support' => [
                'display_name' => 'Technical Support',
                'description' => 'Supports safe account operations without high-risk role access.',
                'permissions' => [
                    'users.create', 'users.update', 'users.assign_roles', 'users.reset_password', 'reports.audit',
                ],
            ],
        ];

        Role::whereIn('name', ['auditor', 'guest_user'])
            ->get()
            ->each(function (Role $role) {
                $role->permissions()->detach();
                $role->users()->detach();
                $role->delete();
            });

        foreach ($roles as $roleName => $definition) {
            $role = Role::updateOrCreate(
                ['name' => $roleName],
                [
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                ]
            );

            $permissionIds = Permission::whereIn('name', $definition['permissions'])->pluck('id')->all();
            $role->permissions()->sync($permissionIds);
        }

        $admin = User::where('email', 'admin@example.com')->first();

        if ($admin) {
            $superAdminId = Role::where('name', 'super_administrator')->value('id');
            if ($superAdminId) {
                $admin->roles()->syncWithoutDetaching([$superAdminId]);
            }
        }
    }
}
