<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'email', 'phone', 'institution_type'];

    public function isInstitute(): bool
    {
        return $this->institution_type === 'institute';
    }

    public function expectedStageCount(): int
    {
        return $this->isInstitute() ? 2 : 4;
    }

    public function colleges()
    {
        return $this->hasMany(College::class);
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }
}
