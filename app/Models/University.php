<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'institution_type',
        'expected_stage_count',
        'expected_semesters_per_year',
    ];

    protected function casts(): array
    {
        return [
            'expected_stage_count' => 'integer',
            'expected_semesters_per_year' => 'integer',
        ];
    }

    public function isInstitute(): bool
    {
        return $this->institution_type === 'institute';
    }

    public function expectedStageCount(): int
    {
        return max(1, (int) ($this->expected_stage_count ?: ($this->isInstitute() ? 2 : 4)));
    }

    public function expectedSemesterCount(): int
    {
        return max(1, (int) ($this->expected_semesters_per_year ?: 2));
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
