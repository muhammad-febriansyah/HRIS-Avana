<?php

namespace App\Services;

use App\Concerns\Auditable;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * Writes a single row to `user_activity_logs` for anything worth showing on
 * the "Aktivitas" tab of the audit trail: logins, page visits, and (mirrored
 * from {@see Auditable}) data changes.
 *
 * Best-effort like {@see Auditable}: logging must never break
 * the request it is observing.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $event,
        ?string $description = null,
        array $properties = [],
        ?User $user = null,
        ?Model $subject = null,
        ?Request $request = null,
    ): void {
        $user ??= auth()->user();
        $request ??= request();

        try {
            UserActivityLog::create([
                'tenant_id' => $user?->tenant_id,
                'user_id' => $user?->id,
                'event' => $event,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'path' => $request?->path(),
                'method' => $request?->method(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'properties' => $properties === [] ? null : $properties,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
