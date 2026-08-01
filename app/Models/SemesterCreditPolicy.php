<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemesterCreditPolicy extends Model
{
    protected $fillable = [
        'university_id',
        'semester_credits',
        'passing_credits',
        'graduation_credits',
    ];

    protected function casts(): array
    {
        return [
            'semester_credits' => 'integer',
            'passing_credits' => 'integer',
            'graduation_credits' => 'integer',
        ];
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}
