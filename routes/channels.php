<?php

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('company.{tenantId}.tracking', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === $tenantId
        && $user->can('viewAny', Attendance::class);
});
