<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'course_section_id',
        'status',
        'is_retake',
        'retake_from_enrollment_id',
        'retake_reason',
        'enrolled_at',
        'dropped_at',
        'drop_reason',
        'waitlisted_at',
        'transferred_from_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'dropped_at' => 'date',
            'waitlisted_at' => 'datetime',
            'is_retake' => 'boolean',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function transferredFrom()
    {
        return $this->belongsTo(Enrollment::class, 'transferred_from_id')->withTrashed();
    }

    public function retakeFromEnrollment()
    {
        return $this->belongsTo(Enrollment::class, 'retake_from_enrollment_id')->withTrashed();
    }

    public function events()
    {
        return $this->hasMany(EnrollmentEvent::class)->orderByDesc('occurred_at');
    }
}
