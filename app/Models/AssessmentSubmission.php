<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_item_id',
        'student_id',
        'graded_by',
        'content',
        'attachment_path',
        'submitted_at',
        'score',
        'feedback',
        'status',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function assessmentItem()
    {
        return $this->belongsTo(AssessmentItem::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
