<?php

namespace App\Http\Controllers;

use App\Models\AssessmentItem;
use App\Models\ClassStreamPost;
use App\Models\ClassStreamReaction;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Mark;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Services\ProtectedFileService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;

class ClassStreamController extends Controller
{
    public function show(Request $request, CourseSection $courseSection)
    {
        $this->authorizeClassMember($request, $courseSection);
        $courseSection->load(['course', 'semester', 'teacher']);
        $student = Student::where('email', $request->user()->email)->first();
        $requestedTab = (string) $request->query('tab', 'stream');
        $activeTab = in_array($requestedTab, ['stream', 'classwork', 'people', 'grades', 'timetable'], true)
            ? $requestedTab
            : 'stream';
        $streamPosts = $this->postsFor($courseSection, $request->user()->id);
        $assessments = AssessmentItem::with([
            'courseSection.course',
            'courseSection.semester',
            'rubric',
            'submissions' => fn ($query) => $query->where('student_id', $student?->id),
        ])
            ->where('course_section_id', $courseSection->id)
            ->where('status', 'published')
            ->orderByRaw('due_at is null, due_at asc')
            ->get();
        $materials = CourseMaterial::where('course_id', $courseSection->course_id)
            ->where('visibility', 'published')
            ->where(fn ($query) => $query->where('course_section_id', $courseSection->id)->orWhereNull('course_section_id'))
            ->latest()
            ->get();
        $mark = $student
            ? Mark::where('student_id', $student->id)
                ->where('course_id', $courseSection->course_id)
                ->where(fn ($query) => $query->where('course_section_id', $courseSection->id)->orWhereNull('course_section_id'))
                ->where(fn ($query) => $query->where('visibility_status', 'published')->orWhere('status', 'Published'))
                ->latest()
                ->first()
            : null;
        $timetableEntries = Timetable::with(['classroom', 'timeSlot'])
            ->where('course_section_id', $courseSection->id)
            ->where('status', 'scheduled')
            ->orderByRaw("case day_of_week when 'Sunday' then 1 when 'Monday' then 2 when 'Tuesday' then 3 when 'Wednesday' then 4 when 'Thursday' then 5 when 'Friday' then 6 when 'Saturday' then 7 else 8 end")
            ->orderBy('start_time')
            ->get();

        return view('class-stream.show', [
            'section' => $courseSection,
            'student' => $student,
            'activeTab' => $activeTab,
            'streamPosts' => $streamPosts,
            'assessments' => $assessments,
            'materials' => $materials,
            'mark' => $mark,
            'timetableEntries' => $timetableEntries,
            'canCreatePost' => $this->canTeachClass($request, $courseSection) || $this->canStudentPost($request, $courseSection),
        ]);
    }

    public function store(Request $request, CourseSection $courseSection)
    {
        abort_unless($this->canTeachClass($request, $courseSection) || $this->canStudentPost($request, $courseSection), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:10000'],
            'attachment' => $this->safeUploadRules('required_without:body'),
        ]);

        $attachment = $request->file('attachment');
        ClassStreamPost::create([
            'course_section_id' => $courseSection->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $attachment ? app(ProtectedFileService::class)->store($attachment, 'class-stream') : null,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
        ]);

        return $this->redirectToStream($request, $courseSection)->with('success', 'Posted to the class stream.');
    }

    public function comment(Request $request, CourseSection $courseSection, ClassStreamPost $post)
    {
        $this->authorizeClassMember($request, $courseSection);
        $this->ensurePostBelongsToSection($courseSection, $post);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return $this->redirectToStream($request, $courseSection)->with('success', 'Comment added.');
    }

    public function react(Request $request, CourseSection $courseSection, ClassStreamPost $post)
    {
        $this->authorizeClassMember($request, $courseSection);
        $this->ensurePostBelongsToSection($courseSection, $post);

        $reaction = ClassStreamReaction::where('class_stream_post_id', $post->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($reaction) {
            $reaction->delete();
        } else {
            $post->reactions()->create(['user_id' => $request->user()->id, 'type' => 'like']);
        }

        return $this->redirectToStream($request, $courseSection);
    }

    public function attachment(Request $request, CourseSection $courseSection, ClassStreamPost $post)
    {
        if ($request->user()->hasAnyRole([
            'administrator',
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'lms_administrator',
        ])) {
            $sectionQuery = CourseSection::whereKey($courseSection->id)
                ->whereIn('status', ['planned', 'active']);
            OrganizationScope::apply($sectionQuery, $request->user(), 'section');
            abort_unless($sectionQuery->exists(), 403);
        } else {
            $this->authorizeClassMember($request, $courseSection);
        }
        $this->ensurePostBelongsToSection($courseSection, $post);
        $files = app(ProtectedFileService::class);
        abort_unless($post->attachment_path && $files->exists($post->attachment_path), 404);

        return $files->download($post->attachment_path, $post->attachment_name);
    }

    public function destroy(Request $request, CourseSection $courseSection, ClassStreamPost $post)
    {
        $this->authorizeClassMember($request, $courseSection);
        $this->ensurePostBelongsToSection($courseSection, $post);
        abort_unless($this->canTeachClass($request, $courseSection) || $post->user_id === $request->user()->id, 403);

        if ($post->attachment_path) {
            app(ProtectedFileService::class)->delete($post->attachment_path);
        }
        $post->delete();

        return $this->redirectToStream($request, $courseSection)->with('success', 'Stream post deleted.');
    }

    public function toggleStudentPosting(Request $request, CourseSection $courseSection)
    {
        abort_unless($this->canTeachClass($request, $courseSection), 403);
        $courseSection->update(['students_can_post_stream' => ! $courseSection->students_can_post_stream]);

        return $this->redirectToStream($request, $courseSection)->with(
            'success',
            $courseSection->students_can_post_stream ? 'Students can now post to the stream.' : 'Student stream posting disabled.'
        );
    }

    public function postsFor(CourseSection $courseSection, int $userId)
    {
        return $courseSection->streamPosts()
            ->with(['user.roles', 'comments.user.roles', 'reactions'])
            ->withCount(['comments', 'reactions'])
            ->latest()
            ->limit(30)
            ->get()
            ->each(fn (ClassStreamPost $post) => $post->setAttribute('reacted_by_current_user', $post->reactions->contains('user_id', $userId)));
    }

    private function authorizeClassMember(Request $request, CourseSection $courseSection): void
    {
        $user = $request->user();
        abort_unless($this->isCurrentSection($courseSection) || $user->hasRole('super_administrator'), 404);

        if ($user->hasRole('super_administrator') || $this->canTeachClass($request, $courseSection)) {
            return;
        }

        if ($user->hasRole('student')) {
            $student = Student::where('email', $user->email)->first();
            abort_unless($student && $courseSection->activeEnrollments()->where('student_id', $student->id)->exists(), 403);

            return;
        }

        abort(403);
    }

    private function canTeachClass(Request $request, CourseSection $courseSection): bool
    {
        $user = $request->user();
        if ($user->hasRole('super_administrator')) {
            return true;
        }
        if (! $this->isCurrentSection($courseSection)) {
            return false;
        }
        if (! $user->hasRole('teacher')) {
            return false;
        }

        $teacher = Teacher::where('email', $user->email)->first();

        return $teacher && $courseSection->teacher_id === $teacher->id;
    }

    private function canStudentPost(Request $request, CourseSection $courseSection): bool
    {
        if (! $this->isCurrentSection($courseSection)) {
            return false;
        }
        if (! $request->user()->hasRole('student') || ! $courseSection->students_can_post_stream) {
            return false;
        }

        $student = Student::where('email', $request->user()->email)->first();

        return $student && $courseSection->activeEnrollments()->where('student_id', $student->id)->exists();
    }

    private function ensurePostBelongsToSection(CourseSection $courseSection, ClassStreamPost $post): void
    {
        abort_unless($post->course_section_id === $courseSection->id, 404);
    }

    private function redirectToStream(Request $request, CourseSection $courseSection)
    {
        if ($request->user()->hasAnyRole(['teacher', 'super_administrator'])) {
            return redirect()->route('teacher-dashboard', ['section_id' => $courseSection->id, 'tab' => 'stream']);
        }

        return redirect()->route('class-stream.show', $courseSection);
    }

    private function isCurrentSection(CourseSection $courseSection): bool
    {
        return in_array($courseSection->status, ['planned', 'active'], true) && ! $courseSection->trashed();
    }
}
