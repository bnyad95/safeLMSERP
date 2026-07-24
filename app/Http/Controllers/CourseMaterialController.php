<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\CourseMaterialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseMaterialController extends Controller
{
    public function __construct(private CourseMaterialService $service) {}

    public function index(Request $request, Course $course)
    {
        $user = Auth::user();

        if (! $this->canViewCourse($course)) {
            abort(403);
        }

        $section = $this->resolveSection($request, $course);
        $onlyPublished = $user->hasRole('student');
        $materials = $this->service->getMaterials($course->id, $onlyPublished, $section?->id);

        return view('course-materials.index', compact('course', 'section', 'materials'));
    }

    public function create(Request $request, Course $course)
    {
        if (! $this->canManageCourse($course)) {
            abort(403);
        }

        $section = $this->resolveSection($request, $course);

        return view('course-materials.create', compact('course', 'section'));
    }

    public function store(Request $request, Course $course)
    {
        if (! $this->canManageCourse($course)) {
            return $this->respondUnauthorized($request);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => $this->safeUploadRules('nullable', 51200),
            'file_type' => 'required|in:pdf,doc,video,image,presentation,other',
            'visibility' => 'required|in:draft,published',
        ]);

        $section = $this->resolveSection($request, $course);
        $material = $this->service->uploadMaterial($course->id, $validated, $section?->id);

        return $this->respondSaved($request, $course, 'Material uploaded successfully.', $material, $request->integer('section_id') ? $section : null);
    }

    public function edit(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section) || ! $this->canManageCourse($course, $material)) {
            abort(403);
        }

        return view('course-materials.edit', compact('course', 'material'));
    }

    public function update(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section) || ! $this->canManageCourse($course, $material)) {
            return $this->respondUnauthorized($request);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => $this->safeUploadRules('nullable', 51200),
            'file_type' => 'required|in:pdf,doc,video,image,presentation,other',
            'visibility' => 'required|in:draft,published',
        ]);

        $updated = $this->service->updateMaterial($material, $validated);

        return $this->respondSaved($request, $course, 'Material updated successfully.', $updated, $request->integer('section_id') ? $section : null);
    }

    public function destroy(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section) || ! $this->canManageCourse($course, $material)) {
            return $this->respondUnauthorized($request);
        }

        $this->service->deleteMaterial($material);

        return $this->respondDeleted($request, $course, 'Material deleted successfully.', $request->integer('section_id') ? $section : null);
    }

    public function download(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section)) {
            abort(404);
        }

        if (! $this->canViewCourse($course)) {
            abort(403);
        }

        if ($material->visibility === 'draft' && ! $this->canManageCourse($course, $material) && ! $this->canInspectCourse($course)) {
            abort(403);
        }

        if (! $material->file_path) {
            abort(404, 'File not found');
        }
        abort_unless(Storage::disk('public')->exists($material->file_path), 404);

        return Storage::disk('public')->download($material->file_path);
    }

    public function publish(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section) || ! $this->canManageCourse($course, $material)) {
            return $this->respondUnauthorized($request);
        }

        $this->service->publishMaterial($material);

        return $this->respondDeleted($request, $course, 'Material published successfully.', $request->integer('section_id') ? $section : null);
    }

    public function unpublish(Request $request, Course $course, CourseMaterial $material)
    {
        $section = $this->resolveSection($request, $course);
        if (! $this->materialBelongsToCourse($course, $material) || ! $this->materialMatchesSection($material, $section) || ! $this->canManageCourse($course, $material)) {
            return $this->respondUnauthorized($request);
        }

        $this->service->unpublishMaterial($material);

        return $this->respondDeleted($request, $course, 'Material unpublished successfully.', $request->integer('section_id') ? $section : null);
    }

    private function canViewCourse(Course $course): bool
    {
        $user = Auth::user();

        if ($this->canInspectCourse($course)) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            return $this->teacherAssignedToCourse($course);
        }

        if ($user->hasRole('student')) {
            $student = Student::where('email', $user->email)->first();

            return (bool) $student?->enrollments()
                ->where('status', 'enrolled')
                ->whereHas('courseSection', fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->whereIn('status', ['planned', 'active']))
                ->exists();
        }

        return false;
    }

    private function canInspectCourse(Course $course): bool
    {
        return Auth::user()->hasAnyRole([
            'administrator',
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'lms_administrator',
        ]);
    }

    private function canManageCourse(Course $course, ?CourseMaterial $material = null): bool
    {
        $user = Auth::user();

        if ($user->hasRole('super_administrator')) {
            return true;
        }

        if (! $user->hasRole('teacher') || ! $this->teacherAssignedToCourse($course)) {
            return false;
        }

        return ! $material || $material->uploaded_by === $user->id;
    }

    private function teacherAssignedToCourse(Course $course): bool
    {
        $teacher = Teacher::where('email', Auth::user()->email)->first();

        return (bool) $teacher?->courseSections()
            ->where('course_id', $course->id)
            ->whereIn('status', ['planned', 'active'])
            ->exists();
    }

    private function materialBelongsToCourse(Course $course, CourseMaterial $material): bool
    {
        return $material->course_id === $course->id;
    }

    private function materialMatchesSection(CourseMaterial $material, ?CourseSection $section): bool
    {
        return ! $section || ! $material->course_section_id || $material->course_section_id === $section->id;
    }

    private function respondUnauthorized(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        abort(403);
    }

    private function respondSaved(Request $request, Course $course, string $message, CourseMaterial $material, ?CourseSection $section = null)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $material,
            ]);
        }

        $parameters = ['course' => $course];
        if ($section) {
            $parameters['section_id'] = $section->id;
        }

        return redirect()->route('materials.index', $parameters)->with('success', $message);
    }

    private function respondDeleted(Request $request, Course $course, string $message, ?CourseSection $section = null)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        $parameters = ['course' => $course];
        if ($section) {
            $parameters['section_id'] = $section->id;
        }

        return redirect()->route('materials.index', $parameters)->with('success', $message);
    }

    private function resolveSection(Request $request, Course $course): ?CourseSection
    {
        $user = $request->user();
        $sections = CourseSection::where('course_id', $course->id);
        $sections->whereIn('status', ['planned', 'active']);

        if ($user->hasRole('teacher') && ! $user->hasRole('super_administrator')) {
            $teacher = Teacher::where('email', $user->email)->first();
            abort_unless($teacher, 403);
            $sections->where('teacher_id', $teacher->id);
        } elseif ($user->hasRole('student')) {
            $student = Student::where('email', $user->email)->first();
            abort_unless($student, 403);
            $sections->whereHas('activeEnrollments', fn ($query) => $query->where('student_id', $student->id));
        }

        if ($request->integer('section_id')) {
            return (clone $sections)->whereKey($request->integer('section_id'))->firstOrFail();
        }

        if ($user->hasAnyRole(['administrator', 'super_administrator'])) {
            return null;
        }

        $matchingSections = $sections->get();
        abort_if($matchingSections->count() > 1, 422, 'Choose a class section first.');

        return $matchingSections->first();
    }
}
