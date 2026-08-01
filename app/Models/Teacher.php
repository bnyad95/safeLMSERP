<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use SoftDeletes;

    protected $fillable = ['university_id', 'department_id', 'user_id', 'staff_id', 'full_name', 'email', 'title', 'status'];

    protected static function booted(): void
    {
        static::creating(function (Teacher $teacher): void {
            if (blank($teacher->staff_id)) {
                $teacher->staff_id = static::suggestedStaffIdentifier();
            }
        });
    }

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

    public static function suggestedStaffIdentifier(): string
    {
        $prefix = trim((string) config('academics.teacher_id_prefix', 'TCH'));
        $prefix = $prefix !== '' ? strtoupper($prefix) : 'TCH';
        $year = now()->format('y');
        $sequence = static::withTrashed()->count() + 1;

        do {
            $candidate = sprintf('%s%s%04d', $prefix, $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('staff_id', $candidate)->exists());

        return $candidate;
    }
}
