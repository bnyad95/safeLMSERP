<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TuitionChargeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_transaction_id',
        'enrollment_id',
        'course_id',
        'course_section_id',
        'tuition_rate_id',
        'credits',
        'rate_per_credit',
        'amount',
        'is_retake',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:1',
            'rate_per_credit' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_retake' => 'boolean',
        ];
    }

    public function financeTransaction()
    {
        return $this->belongsTo(FinanceTransaction::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class)->withTrashed();
    }

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function tuitionRate()
    {
        return $this->belongsTo(TuitionRate::class);
    }
}
