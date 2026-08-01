<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'tuition_agreement_id',
        'semester_id',
        'invoice_transaction_id',
        'original_transaction_id',
        'recorded_by',
        'approved_by',
        'approved_at',
        'voided_by',
        'voided_at',
        'type',
        'amount',
        'balance_after',
        'currency',
        'status',
        'posting_status',
        'payment_status',
        'invoice_number',
        'receipt_number',
        'reference',
        'academic_year',
        'transaction_date',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function tuitionAgreement()
    {
        return $this->belongsTo(TuitionAgreement::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function invoice()
    {
        return $this->belongsTo(self::class, 'invoice_transaction_id');
    }

    public function allocations()
    {
        return $this->hasMany(self::class, 'invoice_transaction_id');
    }

    public function originalTransaction()
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    public function reversals()
    {
        return $this->hasMany(self::class, 'original_transaction_id');
    }

    public static function chargeTypes(): array
    {
        return ['invoice', 'refund'];
    }

    public static function creditTypes(): array
    {
        return ['payment', 'discount', 'scholarship'];
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return in_array($this->type, self::creditTypes(), true) ? -$amount : $amount;
    }

    public function isPosted(): bool
    {
        return $this->posting_status === 'posted';
    }

    public function documentNumber(): ?string
    {
        return $this->invoice_number ?: $this->receipt_number;
    }
}
