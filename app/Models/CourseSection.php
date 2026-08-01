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

            $stageName = trim((string) $section->grade_level);
            $course = $section->course_id ? Course::withTrashed()->find($section->course_id) : null;
            if ($stageName !== '' && $course) {
                $stage = Stage::where('department_id', $course->department_id)
                    ->where('name', $stageName)
                    ->first();

                if (! $stage) {
                    $university = University::find($course->university_id);
                    $nextSequence = ((int) Stage::where('department_id', $course->department_id)->max('sequence')) + 1;
                    $maxStages = $university?->expectedStageCount() ?? 4;

                    if ($nextSequence <= $maxStages) {
                        $stage = Stage::create([
                            'department_id' => $course->department_id,
                            'name' => $stageName,
                            'university_id' => $course->university_id,
                            'sequence' => $nextSequence,
                        ]);
                    }
                }

                if ($stage) {
                    $section->stage_id = $stage->id;
                }
            }
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
}
