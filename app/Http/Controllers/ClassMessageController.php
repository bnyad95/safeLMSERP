<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\ClassMessage;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClassMessageController extends Controller
{
    public function index(Request $request, CourseSection $courseSection)
    {
        $this->authorizeMember($request, $courseSection);
        $courseSection->load(['course', 'semester', 'teacher']);
        $participants = $this->participants($request, $courseSection);
        $selectedUser = $this->selectedParticipant($request, $participants);
        $messages = collect();

        if ($selectedUser) {
            $this->markConversationRead($request, $courseSection, $selectedUser);
            $messages = $this->messagesFor($request, $courseSection, $selectedUser);
        }

        $this->addUnreadCounts($request, $courseSection, $participants);

        return view('class-messages.index', compact('courseSection', 'participants', 'selectedUser', 'messages'));
    }

    public function thread(Request $request, CourseSection $courseSection)
    {
        $this->authorizeMember($request, $courseSection);
        $courseSection->load(['course', 'semester', 'teacher']);
        $participants = $this->participants($request, $courseSection);
        $selectedUser = $participants->firstWhere('id', $request->integer('recipient_id'));
        abort_unless($selectedUser, 403);

        $this->markConversationRead($request, $courseSection, $selectedUser);
        $messages = $this->messagesFor($request, $courseSection, $selectedUser);
        $this->addUnreadCounts($request, $courseSection, $participants);

        $lastMessage = $messages->last();

        return response()->json([
            'messages_html' => view('class-messages._messages', compact('courseSection', 'messages'))->render(),
            'participants_html' => view('class-messages._participants', compact('courseSection', 'participants', 'selectedUser'))->render(),
            'signature' => $messages->count().':'.($lastMessage?->id ?? 0).':'.($lastMessage?->updated_at?->timestamp ?? 0),
        ]);
    }

    public function store(Request $request, CourseSection $courseSection)
    {
        $this->authorizeMember($request, $courseSection);
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:5000'],
            'attachment' => ['nullable', 'required_without:body', 'file', 'max:51200'],
        ]);
        $recipient = $this->participants($request, $courseSection)->firstWhere('id', (int) $validated['recipient_id']);
        abort_unless($recipient, 403);

        $attachment = $request->file('attachment');
        $message = ClassMessage::create([
            'course_section_id' => $courseSection->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $recipient->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $attachment?->store('class-messages', 'public'),
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getClientMimeType(),
        ]);

        app(NotificationService::class)->notifyClassMessage($message);

        if ($request->expectsJson()) {
            return response()->json(['message_id' => $message->id], 201);
        }

        return redirect()->route('class-messages.index', ['courseSection' => $courseSection, 'recipient_id' => $recipient->id]);
    }

    public function attachment(Request $request, CourseSection $courseSection, ClassMessage $message)
    {
        $this->authorizeMember($request, $courseSection);
        abort_unless($message->course_section_id === $courseSection->id, 404);
        abort_unless(in_array($request->user()->id, [$message->sender_id, $message->recipient_id], true), 403);
        abort_unless($message->attachment_path && Storage::disk('public')->exists($message->attachment_path), 404);

        return Storage::disk('public')->download($message->attachment_path, $message->attachment_name);
    }

    private function participants(Request $request, CourseSection $courseSection)
    {
        if ($this->isAssignedTeacher($request, $courseSection) || $request->user()->hasRole('super_administrator')) {
            $students = Student::whereHas('enrollments', fn ($query) => $query
                ->where('status', 'enrolled')
                ->where('course_section_id', $courseSection->id))
                ->get(['email', 'student_id'])
                ->keyBy('email');

            $participants = User::whereIn('email', $students->keys())
                ->orderBy('name')
                ->get()
                ->each(fn (User $user) => $user->setAttribute('academic_id', $students[$user->email]?->student_id));

            $latestReceived = ClassMessage::where('course_section_id', $courseSection->id)
                ->where('recipient_id', $request->user()->id)
                ->whereIn('sender_id', $participants->pluck('id'))
                ->selectRaw('sender_id, max(id) as latest_message_id')
                ->groupBy('sender_id')
                ->pluck('latest_message_id', 'sender_id');

            return $participants
                ->sortByDesc(fn (User $participant) => (int) ($latestReceived[$participant->id] ?? 0))
                ->values();
        }

        $teacherEmail = $courseSection->teacher?->email;

        return $teacherEmail ? User::where('email', $teacherEmail)->get() : collect();
    }

    private function authorizeMember(Request $request, CourseSection $courseSection): void
    {
        abort_unless($this->isCurrentSection($courseSection) || $request->user()->hasRole('super_administrator'), 404);

        if ($request->user()->hasRole('super_administrator') || $this->isAssignedTeacher($request, $courseSection)) {
            return;
        }

        if ($request->user()->hasRole('student')) {
            $student = Student::where('email', $request->user()->email)->first();
            abort_unless($student && $courseSection->activeEnrollments()->where('student_id', $student->id)->exists(), 403);

            return;
        }

        abort(403);
    }

    private function isAssignedTeacher(Request $request, CourseSection $courseSection): bool
    {
        if (! $request->user()->hasRole('teacher')) {
            return false;
        }
        if (! $this->isCurrentSection($courseSection)) {
            return false;
        }

        $teacher = Teacher::where('email', $request->user()->email)->first();

        return $teacher && $courseSection->teacher_id === $teacher->id;
    }

    private function isCurrentSection(CourseSection $courseSection): bool
    {
        return in_array($courseSection->status, ['planned', 'active'], true) && ! $courseSection->trashed();
    }

    private function selectedParticipant(Request $request, $participants): ?User
    {
        if ($request->integer('recipient_id')) {
            return $participants->firstWhere('id', $request->integer('recipient_id'));
        }

        return $request->user()->hasRole('student') ? $participants->first() : null;
    }

    private function markConversationRead(Request $request, CourseSection $courseSection, User $selectedUser): void
    {
        ClassMessage::where('course_section_id', $courseSection->id)
            ->where('sender_id', $selectedUser->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $notificationIds = AppNotification::where('user_id', $request->user()->id)
            ->where('type', 'class_message')
            ->whereNull('read_at')
            ->get()
            ->filter(fn (AppNotification $notification) => (int) data_get($notification->data, 'sender_id') === $selectedUser->id
                && (int) data_get($notification->data, 'course_section_id') === $courseSection->id)
            ->pluck('id');

        AppNotification::whereIn('id', $notificationIds)->update(['read_at' => now()]);
    }

    private function messagesFor(Request $request, CourseSection $courseSection, User $selectedUser)
    {
        return ClassMessage::with(['sender', 'recipient'])
            ->where('course_section_id', $courseSection->id)
            ->where(function ($query) use ($request, $selectedUser) {
                $query->where(fn ($query) => $query->where('sender_id', $request->user()->id)->where('recipient_id', $selectedUser->id))
                    ->orWhere(fn ($query) => $query->where('sender_id', $selectedUser->id)->where('recipient_id', $request->user()->id));
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();
    }

    private function addUnreadCounts(Request $request, CourseSection $courseSection, $participants): void
    {
        $unreadCounts = ClassMessage::where('course_section_id', $courseSection->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->selectRaw('sender_id, count(*) as unread_count')
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $participants->each(function (User $participant) use ($unreadCounts) {
            $participant->setAttribute('unread_count', (int) ($unreadCounts[$participant->id] ?? 0));
        });
    }
}
