<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Header-bell notifications for the web app. Every action is scoped to the
 * authenticated user's own rows, so one tenant can never touch another's.
 */
class NotificationController extends Controller
{
    /** Mark a single notification read (404 when it isn't the caller's). */
    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_if((int) $notification->user_id !== (int) $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    /** Mark all of the caller's unread notifications read. */
    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
