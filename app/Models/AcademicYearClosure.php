<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYearClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'academic_year',
        'status',
        'closed_by',
        'closed_at',
        'summary',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'summary' => 'array',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
