<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'created_by',
        'payment_method',
        'total_amount',
        'currency',
        'status',
        'agreed_at',
        'notes',
        'agreement_key',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'agreed_at' => 'date',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class);
    }
}
