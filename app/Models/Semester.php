<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['university_id', 'academic_year_id', 'name', 'academic_year', 'start_date', 'end_date'];

    protected static function booted(): void
    {
        static::saving(function (Semester $semester) {
            if ($semester->academic_year_id) {
                $academicYear = AcademicYear::find($semester->academic_year_id);
                if ($academicYear) {
                    $semester->university_id = $academicYear->university_id;
                    $semester->academic_year = $academicYear->name;
                }

                return;
            }

            if ($semester->university_id && filled($semester->academic_year)) {
                $academicYear = AcademicYear::firstOrCreate(
                    ['university_id' => $semester->university_id, 'name' => $semester->academic_year],
                    ['status' => 'active']
                );
                $semester->academic_year_id = $academicYear->id;
            }
        });
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
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
