<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Open one notification: mark it read and forward the user to the page it
     * points at (for a delivery assignment that is the delivery detail page).
     *
     * Only the signed-in user's own notifications are reachable — anything
     * else is a 404, so notification URLs can never leak another account's
     * alerts. Notifications without a URL simply return to the list.
     */
    public function read(Request $request, string $notification)
    {
        $record = $request->user()
            ->notifications()
            ->where('id', $notification)
            ->first();

        abort_if($record === null, 404);

        $record->markAsRead();

        return redirect()->to($this->safeNotificationUrl($record->data['url'] ?? null));
    }

    /**
     * Mark every notification of the signed-in user as read.
     */
    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Only ever bounce to an internal (same-application) URL so a stored
     * notification can never be turned into an open redirect.
     */
    private function safeNotificationUrl(?string $url): string
    {
        if (is_string($url) && $url !== '' && Str::startsWith($url, url('/'))) {
            return $url;
        }

        return route('notifications.index');
    }
}
