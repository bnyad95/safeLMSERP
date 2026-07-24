<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $fillable = [
        'university_id',
        'department_id',
        'user_id',
        'student_id',
        'full_name',
        'name',
        'email',
        'phone',
        'roll_number',
        'status',
        'admission_status',
        'admission_date',
        'admission_type',
        'previous_school',
        'address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    protected $casts = [
        'admission_date' => 'date',
    ];

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
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
}
