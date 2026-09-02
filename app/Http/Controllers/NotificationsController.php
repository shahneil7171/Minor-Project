<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Lightweight in-app notification list (all authenticated roles).
 */
class NotificationsController extends Controller
{
    /**
     * The signed-in user's notifications (latest first).
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark every notification of the signed-in user as read.
     */
    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
