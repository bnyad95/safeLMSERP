<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use SoftDeletes;

    protected $fillable = ['university_id', 'department_id', 'user_id', 'staff_id', 'full_name', 'email', 'title', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function courseSections()
    {
        return $this->hasMany(CourseSection::class);
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }
}
