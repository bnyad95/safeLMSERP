<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'student_id',
        'course_section_id',
        'actor_id',
        'action',
        'notes',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class)->withTrashed();
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
