<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\TranscriptService;
use App\Support\OrganizationScope;
use Illuminate\Support\Facades\Auth;

class TranscriptController extends Controller
{
    public function __construct(private TranscriptService $service) {}

    public function show(Student $student)
    {
        $this->authorizeTranscript($student);
        $student->loadMissing(['university', 'department']);
        $marks = $this->service->publishedMarks($student);

        $gpa = $this->service->calculateGPA($marks);
        $credits = $this->service->calculateTotalCredits($marks);
        $completedCourses = $this->service->calculateCompletedCourses($marks);

        return view('transcripts.view', compact('student', 'marks', 'gpa', 'credits', 'completedCourses'));
    }

    public function download(Student $student)
    {
        $this->authorizeTranscript($student);

        return $this->service->downloadTranscript($student);
    }

    public function preview(Student $student)
    {
        $this->authorizeTranscript($student);

        $pdf = $this->service->generateTranscript($student);

        return $pdf->stream('transcript.pdf');
    }

    private function authorizeTranscript(Student $student): void
    {
        $user = Auth::user();

        if ($user->hasRole('student') && $user->email === $student->email) {
            return;
        }

        abort_unless($user->hasRole('super_administrator') || $user->hasPermission('marks.view'), 403);

        $query = Student::query();
        OrganizationScope::apply($query, $user, 'student');
        abort_unless($query->whereKey($student->id)->exists(), 404);
    }
}
