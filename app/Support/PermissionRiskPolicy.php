<?php

namespace App\Support;

final class PermissionRiskPolicy
{
    private const CRITICAL = [
        'users.assign_roles' => 'Can change role-based authority across the system.',
        'users.reset_password' => 'Can take control of another account by replacing its password.',
        'academic_setup.manage' => 'Can change the institution structure and academic lifecycle.',
        'marks.publish' => 'Can make official student results visible.',
        'marks.approve' => 'Can approve marks before they become official results.',
        'finance.approve_payment' => 'Can approve recorded student payments.',
        'finance.approve_expense' => 'Can approve institutional expenses.',
        'finance.refund' => 'Can reverse or refund financial transactions.',
    ];

    private const HIGH = [
        'students.view' => 'Can view personal and academic student records.',
        'students.create' => 'Can create student identities and records.',
        'students.update' => 'Can change student identity and academic records.',
        'students.archive' => 'Can remove student records from active operations.',
        'teachers.view' => 'Can view teacher identity and employment-related records.',
        'teachers.create' => 'Can create teacher identities and accounts.',
        'teachers.update' => 'Can change teacher identity and organization placement.',
        'teachers.archive' => 'Can remove teacher records from active operations.',
        'courses.create' => 'Can add courses to the academic catalog.',
        'courses.update' => 'Can change course definitions.',
        'courses.archive' => 'Can remove courses from active operations.',
        'courses.assign_teacher' => 'Can change teaching responsibility.',
        'enrollments.manage' => 'Can add, transfer, drop, or promote enrollments.',
        'timetable.manage' => 'Can change institutional schedules and rooms.',
        'attendance.create' => 'Can create official attendance records.',
        'attendance.update' => 'Can change official attendance records.',
        'marks.view' => 'Can view protected student result data.',
        'marks.enter' => 'Can enter marks used in student results.',
        'marks.submit' => 'Can submit marks into the review workflow.',
        'marks.review' => 'Can review submitted marks.',
        'marks.request_change' => 'Can return marks for correction.',
        'finance.view' => 'Can view protected student and institutional finance data.',
        'finance.view_global' => 'Can view finance data across all organizations.',
        'finance.create_invoice' => 'Can create student financial obligations.',
        'finance.record_payment' => 'Can record tuition payments.',
        'finance.record_expense' => 'Can record institutional expenses.',
        'lms.create_content' => 'Can publish learning content to classes.',
        'lms.create_assignment' => 'Can create graded student work.',
        'lms.grade_assignment' => 'Can grade submitted student work.',
        'lms.manage_courses' => 'Can administer LMS course spaces.',
        'reports.academic' => 'Can view institution-level academic reporting.',
        'reports.financial' => 'Can view institution-level financial reporting.',
        'reports.audit' => 'Can view sensitive security and audit activity.',
        'users.create' => 'Can create login accounts.',
        'users.update' => 'Can change account identity and role placement.',
    ];

    public static function metadata(string $permission): array
    {
        if (isset(self::CRITICAL[$permission])) {
            return ['level' => 'critical', 'label' => 'Critical', 'reason' => self::CRITICAL[$permission]];
        }

        if (isset(self::HIGH[$permission])) {
            return ['level' => 'high', 'label' => 'High risk', 'reason' => self::HIGH[$permission]];
        }

        return [
            'level' => 'standard',
            'label' => 'Standard',
            'reason' => 'Read-only or routine access with limited direct system impact.',
        ];
    }

    public static function isSensitive(string $permission): bool
    {
        return self::metadata($permission)['level'] !== 'standard';
    }
}
