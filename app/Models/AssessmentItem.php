<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_section_id',
        'created_by',
        'title',
        'type',
        'description',
        'instruction_file_path',
        'max_score',
        'weight_percent',
        'opens_at',
        'due_at',
        'status',
        'allow_submissions',
    ];

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'weight_percent' => 'decimal:2',
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
            'allow_submissions' => 'boolean',
        ];
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(AssessmentSubmission::class);
    }

    public function rubric()
    {
        return $this->hasOne(Rubric::class);
    }
}
