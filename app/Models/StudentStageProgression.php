<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStageProgression extends Model
{
    protected $fillable = [
        'student_id',
        'stage_id',
        'next_stage_id',
        'academic_year',
        'attempt_number',
        'outcome',
        'attempted_credits',
        'earned_credits',
        'failed_modules',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'attempted_credits' => 'decimal:2',
            'earned_credits' => 'decimal:2',
            'failed_modules' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function nextStage()
    {
        return $this->belongsTo(Stage::class, 'next_stage_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
