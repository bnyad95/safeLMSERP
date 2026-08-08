<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use HasFactory;

    public const TYPE_UNIVERSITY = 'university';

    public const TYPE_INSTITUTE = 'institute';

    private const ACADEMIC_STRUCTURES = [
        self::TYPE_UNIVERSITY => [
            'expected_stage_count' => 4,
            'expected_semesters_per_year' => 2,
        ],
        self::TYPE_INSTITUTE => [
            'expected_stage_count' => 2,
            'expected_semesters_per_year' => 2,
        ],
    ];

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'institution_type',
        'expected_stage_count',
        'expected_semesters_per_year',
        'prefinal_marks_open',
        'prefinal_marks_opened_by',
        'prefinal_marks_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_stage_count' => 'integer',
            'expected_semesters_per_year' => 'integer',
            'prefinal_marks_open' => 'boolean',
            'prefinal_marks_opened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (University $university) {
            $type = $university->institution_type ?: self::TYPE_UNIVERSITY;
            $structure = self::structureForType($type);

            $university->institution_type = $type;
            $university->expected_stage_count = $structure['expected_stage_count'];
            $university->expected_semesters_per_year = $structure['expected_semesters_per_year'];
        });
    }

    public static function structureForType(?string $type): array
    {
        return self::ACADEMIC_STRUCTURES[$type] ?? self::ACADEMIC_STRUCTURES[self::TYPE_UNIVERSITY];
    }

    public function isInstitute(): bool
    {
        return $this->institution_type === self::TYPE_INSTITUTE;
    }

    public function expectedStageCount(): int
    {
        return self::structureForType($this->institution_type)['expected_stage_count'];
    }

    public function expectedSemesterCount(): int
    {
        return self::structureForType($this->institution_type)['expected_semesters_per_year'];
    }

    public function expectedProgramSemesterCount(): int
    {
        return $this->expectedStageCount() * $this->expectedSemesterCount();
    }

    public function colleges()
    {
        return $this->hasMany(College::class);
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function stages()
    {
        return $this->hasMany(Stage::class);
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

    public function prefinalMarksOpener()
    {
        return $this->belongsTo(User::class, 'prefinal_marks_opened_by');
    }
}
