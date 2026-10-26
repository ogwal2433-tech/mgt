<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread' => Notification::where('user_id', $user->id)->unread()->count(),
        ]);
    }

    public function markRead(Notification $notification)
    {
        $user = request()->user();
        if ($notification->user_id !== $user->id) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
