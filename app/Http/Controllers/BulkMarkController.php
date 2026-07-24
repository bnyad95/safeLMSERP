<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkMarkController extends Controller
{
    public function index(Course $course)
    {
        if (! Auth::user()->hasAnyRole(['teacher', 'administrator', 'super_administrator'])) {
            abort(403);
        }

        $students = Student::where('department_id', $course->department_id)
            ->orderBy('full_name')
            ->get();

        $existingMarks = Mark::where('course_id', $course->id)
            ->pluck(null, 'student_id')
            ->toArray();

        return view('marks.bulk-entry', compact('course', 'students', 'existingMarks'));
    }

    public function store(Request $request, Course $course)
    {
        if (! Auth::user()->hasAnyRole(['teacher', 'administrator', 'super_administrator'])) {
            abort(403);
        }

        $validated = $request->validate([
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:students,id',
            'marks.*.assignments' => 'nullable|numeric|min:0|max:100',
            'marks.*.quizzes' => 'nullable|numeric|min:0|max:100',
            'marks.*.midterm' => 'nullable|numeric|min:0|max:100',
            'marks.*.practical' => 'nullable|numeric|min:0|max:100',
            'marks.*.final_exam' => 'nullable|numeric|min:0|max:100',
            'marks.*.final_mark' => 'nullable|numeric|min:0|max:100',
        ]);

        $saved = 0;
        foreach ($validated['marks'] as $markData) {
            Mark::updateOrCreate(
                ['student_id' => $markData['student_id'], 'course_id' => $course->id],
                [
                    'assignments' => $markData['assignments'] ?? 0,
                    'quizzes' => $markData['quizzes'] ?? 0,
                    'midterm' => $markData['midterm'] ?? 0,
                    'practical' => $markData['practical'] ?? 0,
                    'final_exam' => $markData['final_exam'] ?? 0,
                    'final_mark' => $markData['final_mark'] ?? 0,
                    'status' => 'Draft',
                    'submission_status' => 'draft',
                ]
            );
            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => "Saved marks for {$saved} students",
        ]);
    }
}
