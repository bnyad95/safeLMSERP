<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mark extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['student_id', 'course_id', 'course_section_id', 'assignments', 'quizzes', 'midterm', 'practical', 'prefinal_mark', 'first_trial_final_exam', 'second_trial_final_exam', 'final_exam', 'final_mark', 'status', 'submission_status', 'visibility_status', 'reviewer_notes', 'submitted_at', 'reviewed_at', 'published_at', 'reviewed_by'];

    protected $casts = [
        'prefinal_mark' => 'decimal:2',
        'first_trial_final_exam' => 'decimal:2',
        'second_trial_final_exam' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function activeFinalExamScore(): ?float
    {
        if (! is_null($this->second_trial_final_exam)) {
            return (float) $this->second_trial_final_exam;
        }

        if (! is_null($this->first_trial_final_exam)) {
            return (float) $this->first_trial_final_exam;
        }

        return (float) $this->final_exam > 0 ? (float) $this->final_exam : null;
    }

    public function recalculateFinalMark(): void
    {
        $activeFinalExam = $this->activeFinalExamScore();

        if (is_null($this->prefinal_mark) || is_null($activeFinalExam)) {
            $this->final_exam = $activeFinalExam ?? 0;
            $this->final_mark = 0;

            return;
        }

        $this->final_exam = $activeFinalExam;
        $this->final_mark = min((float) config('academics.total_mark_max', 100), (float) $this->prefinal_mark + $activeFinalExam);
    }

    public function passedFirstTrial(): bool
    {
        return ! is_null($this->first_trial_final_exam)
            && is_null($this->second_trial_final_exam)
            && ((float) $this->prefinal_mark + (float) $this->first_trial_final_exam) >= (float) config('academics.pass_mark', 50);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function courseSection()
    {
        return $this->belongsTo(CourseSection::class)->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
