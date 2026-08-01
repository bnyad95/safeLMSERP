<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseSection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'semester_id',
        'stage_id',
        'teacher_id',
        'section_code',
        'grade_level',
        'capacity',
        'status',
        'students_can_post_stream',
        'notes',
    ];

    protected $casts = [
        'students_can_post_stream' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CourseSection $section) {
            if ($section->stage_id) {
                $stage = Stage::find($section->stage_id);
                if ($stage) {
                    $section->grade_level = $stage->name;
                }

                return;
            }

            // Legacy grade text remains readable, but stage definitions are created only in Academic Setup.
        });
    }

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class)->withTrashed();
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'enrolled');
    }

    public function waitlistedEnrollments()
    {
        return $this->hasMany(Enrollment::class)->where('status', 'waitlisted');
    }

    public function enrollmentEvents()
    {
        return $this->hasMany(EnrollmentEvent::class)->orderByDesc('occurred_at');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')->withPivot(['status', 'enrolled_at', 'dropped_at', 'notes'])->withTimestamps();
    }

    public function assessmentItems()
    {
        return $this->hasMany(AssessmentItem::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    public function streamPosts()
    {
        return $this->hasMany(ClassStreamPost::class);
    }

    public function messages()
    {
        return $this->hasMany(ClassMessage::class);
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class);
    }

    public function hasAvailableSeat(?int $ignoredEnrollmentId = null): bool
    {
        $count = $this->activeEnrollments()
            ->when($ignoredEnrollmentId, fn ($query) => $query->whereKeyNot($ignoredEnrollmentId))
            ->count();

        return $count < $this->capacity;
    }

    public function programSemesterNumber(): ?int
    {
        $stage = $this->stage;
        $semester = $this->semester;

        if (! $stage || ! $semester || ($semester->term_type ?? 'regular') === 'summer') {
            return null;
        }

        $university = $semester->university;
        if (! $university || (int) $stage->university_id !== (int) $university->id) {
            return null;
        }

        $stageSequence = (int) $stage->sequence;
        $semesterSequence = (int) $semester->sequence;
        $regularSemesters = $university->expectedSemesterCount();

        if (
            $stageSequence < 1
            || $stageSequence > $university->expectedStageCount()
            || $semesterSequence < 1
            || $semesterSequence > $regularSemesters
        ) {
            return null;
        }

        return (($stageSequence - 1) * $regularSemesters) + $semesterSequence;
    }

    public function programSemesterLabel(): string
    {
        if (($this->semester?->term_type ?? 'regular') === 'summer') {
            return 'Summer Semester';
        }

        $number = $this->programSemesterNumber();

        return $number ? 'Program Semester '.$number : 'Program semester unavailable';
    }
}
