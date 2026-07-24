<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'course_section_id',
        'teacher_id',
        'classroom_id',
        'time_slot_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_number',
        'type',
        'status',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimetableTimeSlot::class, 'time_slot_id');
    }

    public static function getDays(): array
    {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }

    public static function types(): array
    {
        return ['lecture', 'lab', 'tutorial'];
    }

    public function scopeOverlapping($query, string $dayOfWeek, string $startTime, string $endTime)
    {
        return $query
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }
}
