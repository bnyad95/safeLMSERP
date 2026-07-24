<?php

namespace App\Providers;

use App\Models\AcademicYearClosure;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\ClassStreamPost;
use App\Models\College;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\FinanceTransaction;
use App\Models\Mark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use App\Support\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $scopes = [
            University::class => 'university',
            AcademicYearClosure::class => 'semester',
            College::class => 'college',
            Department::class => 'department',
            Semester::class => 'semester',
            Student::class => 'student',
            StudentDocument::class => 'student_record',
            StudentGuardian::class => 'student_record',
            Teacher::class => 'teacher',
            Course::class => 'course',
            CourseSection::class => 'section',
            Enrollment::class => 'section_record',
            EnrollmentEvent::class => 'section_record',
            Timetable::class => 'section_record',
            AssessmentItem::class => 'section_record',
            AssessmentSubmission::class => 'student_record',
            ClassStreamPost::class => 'section_record',
            ClassMessage::class => 'section_record',
            CourseMaterial::class => 'course_record',
            Mark::class => 'student_record',
            Attendance::class => 'student_record',
            FinanceTransaction::class => 'student_record',
        ];

        foreach ($scopes as $model => $type) {
            $model::addGlobalScope('organization', function (Builder $query) use ($type) {
                if ($user = auth()->user()) {
                    OrganizationScope::apply($query, $user, $type);
                }
            });
        }
    }
}
