<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Support\Notifier;

/**
 * Notifies platform super admins about tenant-lifecycle milestones: a new
 * client, a trial→active conversion, or a suspension/deactivation. The super
 * admin who performed the action is excluded from their own alert.
 */
class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        Notifier::tenantCreated($tenant, request()->user()?->id);
    }

    public function updated(Tenant $tenant): void
    {
        if (! $tenant->wasChanged('status')) {
            return;
        }

        $from = (string) $tenant->getOriginal('status');
        $to = (string) $tenant->status;
        $actorId = request()->user()?->id;

        if ($from === 'trial' && $to === 'active') {
            Notifier::tenantActivated($tenant, $actorId);

            return;
        }

        if (in_array($to, ['suspended', 'inactive'], true)) {
            Notifier::tenantDeactivated($tenant, $to, $actorId);
        }
    }
}
