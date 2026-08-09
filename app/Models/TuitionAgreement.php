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
        'installment_count',
        'installments_generated',
        'installment_amount',
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
            'installment_amount' => 'decimal:2',
            'agreed_at' => 'date',
        ];
    }

    public function isInstallmentPlan(): bool
    {
        return ! is_null($this->installment_count) && $this->installment_count > 1;
    }

    public function remainingInstallments(): int
    {
        if (! $this->isInstallmentPlan()) {
            return 0;
        }

        return max(0, $this->installment_count - $this->installments_generated);
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
