<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkScoreHistory extends Model
{
    protected $fillable = [
        'mark_id',
        'actor_id',
        'trial',
        'previous_score',
        'new_score',
        'previous_submission_status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_score' => 'decimal:2',
            'new_score' => 'decimal:2',
        ];
    }

    public function mark()
    {
        return $this->belongsTo(Mark::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
