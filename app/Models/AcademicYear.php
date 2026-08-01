<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = [
        'university_id',
        'name',
        'starts_on',
        'ends_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }
}
