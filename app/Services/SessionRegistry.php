<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and revokes the browser sessions behind a user account.
 *
 * Only meaningful while the database session driver is in use — with `file` or
 * `cookie` there is no server-side record of who is signed in, so the list is
 * reported as unavailable rather than silently empty, which would read as
 * "nobody else is signed in" and be a lie.
 */
class SessionRegistry
{
    public static function available(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Every live session for the user, newest activity first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forUser(User $user, ?string $currentSessionId = null): Collection
    {
        if (! self::available()) {
            return collect();
        }

        return self::table()
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $row) use ($currentSessionId): array {
                $agent = (string) ($row->user_agent ?? '');
                $parsed = LoginSecurity::describe($agent);

                return [
                    'id' => (string) $row->id,
                    'label' => $parsed['label'],
                    'platform' => $parsed['platform'],
                    'browser' => $parsed['browser'],
                    'ip_address' => $row->ip_address,
                    'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity)
                        ->translatedFormat('d M Y, H:i'),
                    'last_active_diff' => Carbon::createFromTimestamp((int) $row->last_activity)->diffForHumans(),
                    'is_current' => $currentSessionId !== null && (string) $row->id === $currentSessionId,
                ];
            });
    }

    /**
     * Sign one session out. Returns false when the id does not belong to this
     * user, so a guessed id cannot end somebody else's session.
     */
    public static function revoke(User $user, string $sessionId): bool
    {
        if (! self::available()) {
            return false;
        }

        return self::table()
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /**
     * Sign every other session out, keeping the one making the request.
     */
    public static function revokeOthers(User $user, ?string $currentSessionId): int
    {
        if (! self::available()) {
            return 0;
        }

        return self::table()
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();
    }

    /**
     * Sign out every session whose browser matches the given device
     * fingerprint. Used when a device is revoked from the device list.
     */
    public static function revokeByFingerprint(User $user, string $fingerprint): int
    {
        if (! self::available()) {
            return 0;
        }

        $ids = self::table()
            ->where('user_id', $user->id)
            ->get(['id', 'user_agent'])
            ->filter(fn (object $row): bool => LoginSecurity::fingerprint((string) ($row->user_agent ?? '')) === $fingerprint)
            ->pluck('id')
            ->all();

        return $ids === [] ? 0 : self::table()->whereIn('id', $ids)->delete();
    }

    private static function table(): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'));
    }
}
