<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'department_id',
        'academic_year_id',
        'currency',
        'rate_per_credit',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return ['rate_per_credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (TuitionRate $rate) {
            if ($rate->isDirty('department_id') || ! $rate->university_id) {
                $rate->university_id = Department::findOrFail($rate->department_id)->university_id;
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

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
