<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\CalendarEvent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        $student = $this->studentForUser($request);

        $notifications = AppNotification::query()
            ->where(fn (Builder $query) => $this->recipientScope($query, $request, $student))
            ->latest()
            ->paginate(15);

        $events = CalendarEvent::query()
            ->where(fn (Builder $query) => $this->recipientScope($query, $request, $student))
            ->where('starts_at', '>=', now()->subDay())
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $unreadCount = AppNotification::query()
            ->where(fn (Builder $query) => $this->recipientScope($query, $request, $student))
            ->whereNull('read_at')
            ->count();

        return view('notifications.index', compact('notifications', 'events', 'unreadCount'));
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        $student = $this->studentForUser($request);

        abort_unless($this->canAccessNotification($request, $notification, $student), 403);

        $notification->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', __('Notification marked as read.'));
    }

    public function markAllRead(Request $request)
    {
        $student = $this->studentForUser($request);

        AppNotification::query()
            ->where(fn (Builder $query) => $this->recipientScope($query, $request, $student))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', __('All notifications marked as read.'));
    }

    private function recipientScope(Builder $query, Request $request, ?Student $student): void
    {
        $query->where('user_id', $request->user()->id);

        if ($student) {
            $query->orWhere('student_id', $student->id);
        }
    }

    private function canAccessNotification(Request $request, AppNotification $notification, ?Student $student): bool
    {
        return $notification->user_id === $request->user()->id
            || ($student && $notification->student_id === $student->id);
    }

    private function studentForUser(Request $request): ?Student
    {
        if (! $request->user()->hasRole('student')) {
            return null;
        }

        return Student::where('email', $request->user()->email)->first();
    }
}
