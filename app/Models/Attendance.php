<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_section_id',
        'student_id',
        'date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public static function getStatusOptions(): array
    {
        return [
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'excused' => 'Excused',
        ];
    }
}
