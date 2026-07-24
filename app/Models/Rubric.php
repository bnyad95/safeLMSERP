<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rubric extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'assessment_item_id',
        'title',
        'criteria',
        'total_points',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'total_points' => 'decimal:2',
        ];
    }

    public function assessmentItem()
    {
        return $this->belongsTo(AssessmentItem::class);
    }
}
