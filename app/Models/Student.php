<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    public const STANDING_NEW = 'new';

    public const STANDING_GOOD = 'good';

    public const STANDING_CARRYING_RETAKES = 'carrying_retakes';

    public const STANDING_REPEAT_STAGE = 'repeat_stage';

    public const STANDING_EXTENDED = 'extended';

    public const STANDING_COMPLETED_PROGRAM = 'completed_program';

    public const STANDING_AWAITING_STAGE_SETUP = 'awaiting_stage_setup';

    public const STANDING_DEFERRED = 'deferred';

    public const STANDING_WITHDRAWN = 'withdrawn';

    public const STANDING_INCOMPLETE = 'incomplete';

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (blank($student->student_id)) {
                $student->student_id = static::suggestedStudentIdentifier();
            }
        });
    }

    protected $fillable = [
        'university_id',
        'department_id',
        'current_stage_id',
        'user_id',
        'student_id',
        'full_name',
        'name',
        'email',
        'phone',
        'status',
        'academic_standing',
        'admission_status',
        'admission_date',
        'admission_type',
        'previous_school',
        'address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'preferred_payment_method',
        'preferred_installment_count',
        'scholarship_percentage',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'scholarship_percentage' => 'decimal:2',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function currentStage()
    {
        return $this->belongsTo(Stage::class, 'current_stage_id');
    }

    public function stageProgressions()
    {
        return $this->hasMany(StudentStageProgression::class)->latest('decided_at');
    }

    public function progressionExceptions()
    {
        return $this->hasMany(StudentProgressionException::class)->latest('approved_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function financeTransactions()
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    public function tuitionAgreements()
    {
        return $this->hasMany(TuitionAgreement::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrollmentEvents()
    {
        return $this->hasMany(EnrollmentEvent::class);
    }

    public function courseSections()
    {
        return $this->belongsToMany(CourseSection::class, 'enrollments')->withPivot(['status', 'enrolled_at', 'dropped_at', 'notes'])->withTimestamps();
    }

    public function assessmentSubmissions()
    {
        return $this->hasMany(AssessmentSubmission::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function guardians()
    {
        return $this->hasMany(StudentGuardian::class)->orderByDesc('is_primary')->orderBy('full_name');
    }

    public function documents()
    {
        return $this->hasMany(StudentDocument::class)->latest();
    }

    public static function suggestedStudentIdentifier(): string
    {
        $prefix = trim((string) config('academics.student_id_prefix', 'STD'));
        $prefix = $prefix !== '' ? strtoupper($prefix) : 'STD';
        $year = now()->format('y');
        $sequence = static::withTrashed()->count() + 1;

        do {
            $candidate = sprintf('%s%s%04d', $prefix, $year, $sequence);
            $sequence++;
        } while (static::withTrashed()->where('student_id', $candidate)->exists());

        return $candidate;
    }
}
