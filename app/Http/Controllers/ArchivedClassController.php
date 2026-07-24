<?php

namespace App\Http\Controllers;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class ArchivedClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('student')) {
            return $this->studentArchive($request);
        }

        if ($user->hasRole('teacher')) {
            return $this->teacherArchive($request);
        }

        abort(403);
    }

    private function studentArchive(Request $request)
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('email', $request->user()->email)
            ->first();

        abort_unless($student, 404);

        $archivedSectionIds = CourseSection::withTrashed()
            ->where(function ($query) {
                $query->whereNotNull('deleted_at')
                    ->orWhere('status', 'closed');
            })
            ->pluck('id');

        $enrollments = Enrollment::with([
                'courseSection.course.department.college',
                'courseSection.semester',
                'courseSection.teacher',
            ])
            ->where('student_id', $student->id)
            ->whereIn('course_section_id', $archivedSectionIds)
            ->orderByDesc('updated_at')
            ->get();

        $marks = Mark::with('course')
            ->where('student_id', $student->id)
            ->whereIn('course_section_id', $enrollments->pluck('course_section_id')->filter())
            ->where('visibility_status', 'published')
            ->get()
            ->keyBy('course_section_id');

        return view('archived-classes.index', [
            'mode' => 'student',
            'student' => $student,
            'teacher' => null,
            'enrollments' => $enrollments,
            'sections' => collect(),
            'marks' => $marks,
        ]);
    }

    private function teacherArchive(Request $request)
    {
        $teacher = Teacher::query()
            ->where('user_id', $request->user()->id)
            ->orWhere('email', $request->user()->email)
            ->first();

        abort_unless($teacher, 404);

        $sections = CourseSection::withTrashed()
            ->with(['course.department.college', 'semester', 'teacher'])
            ->withCount([
                'enrollments as roster_count',
                'assessmentItems',
                'materials',
                'streamPosts',
                'marks',
            ])
            ->where('teacher_id', $teacher->id)
            ->where(function ($query) {
                $query->whereNotNull('deleted_at')
                    ->orWhere('status', 'closed');
            })
            ->orderByDesc('updated_at')
            ->get();

        return view('archived-classes.index', [
            'mode' => 'teacher',
            'student' => null,
            'teacher' => $teacher,
            'enrollments' => collect(),
            'sections' => $sections,
            'marks' => collect(),
        ]);
    }
}
