<?php

namespace App\Http\Controllers;

use App\Models\CourseSection;
use App\Models\Mark;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeacherMarkController extends Controller
{
    public function storePrefinal(Request $request, CourseSection $courseSection)
    {
        $this->authorizeTeacher($request, $courseSection);
        $validated = $request->validate([
            'prefinal_marks' => ['required', 'array'],
            'prefinal_marks.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $studentIds = $courseSection->activeEnrollments()->pluck('student_id');
        $submittedIds = collect(array_keys($validated['prefinal_marks']))->map(fn ($id) => (int) $id);
        if ($submittedIds->diff($studentIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['prefinal_marks' => 'Marks contain students outside this class.']);
        }

        $lockedStudentIds = Mark::where('course_section_id', $courseSection->id)
            ->whereIn('student_id', $submittedIds)
            ->where(fn ($query) => $query
                ->whereIn('submission_status', ['submitted', 'under_review', 'approved'])
                ->orWhereNotNull('first_trial_final_exam')
                ->orWhereNotNull('second_trial_final_exam'))
            ->pluck('student_id');
        if ($lockedStudentIds->isNotEmpty()) {
            throw ValidationException::withMessages(['prefinal_marks' => 'Submitted or approved marks cannot be changed.']);
        }

        foreach ($validated['prefinal_marks'] as $studentId => $score) {
            if ($score === null || $score === '') {
                continue;
            }

            Mark::updateOrCreate(
                [
                    'student_id' => (int) $studentId,
                    'course_id' => $courseSection->course_id,
                    'course_section_id' => $courseSection->id,
                ],
                [
                    'prefinal_mark' => $score,
                    'status' => 'Draft',
                    'submission_status' => 'draft',
                ]
            );
        }

        return redirect()
            ->route('teacher-dashboard', ['section_id' => $courseSection->id, 'tab' => 'grades'])
            ->with('success', 'Pre-final marks saved.');
    }

    private function authorizeTeacher(Request $request, CourseSection $courseSection): void
    {
        $user = $request->user();
        abort_unless(in_array($courseSection->status, ['planned', 'active'], true) && ! $courseSection->trashed(), 404);

        if ($user->hasRole('super_administrator')) {
            return;
        }

        abort_unless($user->hasRole('teacher'), 403);
        $teacher = Teacher::where('email', $user->email)->first();
        abort_unless($teacher && $courseSection->teacher_id === $teacher->id, 403);
    }
}
