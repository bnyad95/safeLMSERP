<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'semester_id',
        'teacher_id',
        'section_code',
        'grade_level',
        'capacity',
        'status',
        'students_can_post_stream',
        'notes',
    ];

    protected $casts = [
        'students_can_post_stream' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'enrolled');
    }

    public function waitlistedEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'waitlisted');
    }

    public function enrollmentEvents()
    {
        return $this->hasMany(EnrollmentEvent::class)->orderByDesc('occurred_at');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')->withPivot(['status', 'enrolled_at', 'dropped_at', 'notes'])->withTimestamps();
    }

    public function assessmentItems()
    {
        return $this->hasMany(AssessmentItem::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function streamPosts()
    {
        return $this->hasMany(ClassStreamPost::class);
    }

    public function messages()
    {
        return $this->hasMany(ClassMessage::class);
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    public function hasAvailableSeat(?int $ignoredEnrollmentId = null): bool
    {
        $count = $this->activeEnrollments()
            ->when($ignoredEnrollmentId, fn ($query) => $query->whereKeyNot($ignoredEnrollmentId))
            ->count();

        return $count < $this->capacity;
    }
}
