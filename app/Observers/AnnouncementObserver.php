<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Support\Notifier;

/**
 * Broadcasts a notification to every active employee when an announcement is
 * published (draft → published). Re-saving an already-published announcement
 * does not re-notify.
 */
class AnnouncementObserver
{
    public function updated(Announcement $announcement): void
    {
        if (! $announcement->wasChanged('status')) {
            return;
        }

        if ($announcement->status !== 'published') {
            return;
        }

        Notifier::announcementPublished($announcement);
    }
}
