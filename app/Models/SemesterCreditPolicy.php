<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemesterCreditPolicy extends Model
{
    protected $fillable = [
        'university_id',
        'semester_credits',
        'passing_credits',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}
