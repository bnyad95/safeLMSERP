<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $fillable = [
        'university_id',
        'department_id',
        'name',
        'code',
        'sequence',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function courseSections()
    {
        return $this->hasMany(CourseSection::class);
    }
}
