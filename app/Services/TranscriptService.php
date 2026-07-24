<?php

namespace App\Services;

use App\Models\Mark;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

class TranscriptService
{
    public function publishedMarks(Student $student)
    {
        return Mark::where('student_id', $student->id)
            ->with(['course.department', 'courseSection.semester'])
            ->where('visibility_status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function generateTranscript(Student $student)
    {
        $student->loadMissing(['university', 'department']);
        $marks = $this->publishedMarks($student);

        $data = [
            'student' => $student,
            'marks' => $marks,
            'generated_at' => now('Asia/Baghdad'),
            'institution' => config('app.name', 'LMS'),
            'gpa' => $this->calculateGPA($marks),
            'total_credits' => $this->calculateTotalCredits($marks),
            'completed_courses' => $this->calculateCompletedCourses($marks),
        ];

        $pdf = Pdf::loadView('transcripts.template', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    public function downloadTranscript(Student $student)
    {
        $transcript = $this->generateTranscript($student);
        $filename = "transcript_{$student->student_id}_".now('Asia/Baghdad')->format('Ymd').'.pdf';

        return $transcript->download($filename);
    }

    public function calculateGPA(mixed $marks): float
    {
        $marks = collect($marks)->filter(fn ($mark) => $mark->final_mark !== null);

        if ($marks->isEmpty()) {
            return 0.0;
        }

        $weightedGradePoints = 0;
        $attemptedCredits = 0;

        foreach ($marks as $mark) {
            $credits = max((int) ($mark->course?->credits ?? 0), 0);
            if ($credits === 0) {
                continue;
            }

            $gradePoint = $this->getGradePoint($mark->final_mark);
            $weightedGradePoints += $gradePoint * $credits;
            $attemptedCredits += $credits;
        }

        return $attemptedCredits > 0 ? round($weightedGradePoints / $attemptedCredits, 2) : 0.0;
    }

    public function calculateTotalCredits(mixed $marks): int
    {
        return (int) collect($marks)
            ->filter(fn ($mark) => $mark->final_mark !== null)
            ->sum(fn ($mark) => (int) ($mark->course?->credits ?? 0));
    }

    public function calculateCompletedCourses(mixed $marks): int
    {
        return collect($marks)
            ->filter(fn ($mark) => isset($mark->final_mark) && $mark->final_mark >= 50)
            ->count();
    }

    private function getGradePoint(float $mark): float
    {
        if ($mark >= 90) {
            return 4.0;
        }
        if ($mark >= 80) {
            return 3.5;
        }
        if ($mark >= 70) {
            return 3.0;
        }
        if ($mark >= 60) {
            return 2.5;
        }
        if ($mark >= 50) {
            return 2.0;
        }

        return 0.0;
    }

    public function getLetterGrade(float $mark): string
    {
        if ($mark >= 90) {
            return 'A';
        }
        if ($mark >= 80) {
            return 'B';
        }
        if ($mark >= 70) {
            return 'C';
        }
        if ($mark >= 60) {
            return 'D';
        }
        if ($mark >= 50) {
            return 'E';
        }

        return 'F';
    }
}
