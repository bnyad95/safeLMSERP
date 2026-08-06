<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProgressionException extends Model
{
    public const STATUSES = ['deferred', 'withdrawn', 'incomplete'];

    protected $fillable = [
        'student_id',
        'academic_year',
        'status',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
