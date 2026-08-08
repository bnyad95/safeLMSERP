<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\AcademicYearClosure;
use App\Models\AssessmentItem;
use App\Models\AssessmentSubmission;
use App\Models\Attendance;
use App\Models\ClassMessage;
use App\Models\Classroom;
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
use App\Models\SemesterCreditPolicy;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\StudentProgressionException;
use App\Models\StudentStageProgression;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\TuitionAgreement;
use App\Models\TuitionChargeLine;
use App\Models\TuitionRate;
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
            AcademicYear::class => 'direct_university',
            AcademicYearClosure::class => 'semester',
            College::class => 'college',
            Department::class => 'department',
            Semester::class => 'semester',
            SemesterCreditPolicy::class => 'direct_university',
            Stage::class => 'stage',
            Student::class => 'student',
            StudentDocument::class => 'student_record',
            StudentGuardian::class => 'student_record',
            StudentProgressionException::class => 'student_record',
            StudentStageProgression::class => 'student_record',
            Teacher::class => 'teacher',
            Course::class => 'course',
            CourseSection::class => 'section',
            Enrollment::class => 'section_record',
            EnrollmentEvent::class => 'section_record',
            Timetable::class => 'section_record',
            Classroom::class => 'direct_university',
            AssessmentItem::class => 'section_record',
            AssessmentSubmission::class => 'assessment_record',
            ClassStreamPost::class => 'section_record',
            ClassMessage::class => 'section_record',
            CourseMaterial::class => 'course_record',
            Mark::class => 'mark_record',
            Attendance::class => 'course_record',
            FinanceTransaction::class => 'finance_record',
            TuitionAgreement::class => 'finance_record',
            TuitionRate::class => 'course',
            TuitionChargeLine::class => 'section_record',
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
