<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['university_id', 'name', 'academic_year', 'start_date', 'end_date'];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function courseSections()
    {
        return $this->hasMany(CourseSection::class);
    }
}
