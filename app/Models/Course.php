<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['department_id', 'university_id', 'code', 'name', 'credits', 'status'];

    protected static function booted(): void
    {
        static::saving(function (Course $course) {
            if ($course->isDirty('department_id') || ! $course->university_id) {
                $course->university_id = Department::findOrFail($course->department_id)->university_id;
            }
        });
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class);
    }
}
