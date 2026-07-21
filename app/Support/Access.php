<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Kill-switch for action-level permission enforcement.
 *
 * When disabled, every {@see User::hasPermissionTo()} check passes
 * and the frontend shares no permission list, so the app behaves exactly as it
 * did before action-level RBAC was rolled out. Flip it with the
 * `access:enforcement` Artisan command. Default: enabled.
 */
final class Access
{
    private const KEY = 'action_permissions.enabled';

    private const CACHE_KEY = 'access.enforced';

    /**
     * Whether action-level enforcement is currently active.
     */
    public static function enforced(): bool
    {
        return Cache::rememberForever(self::CACHE_KEY, static function (): bool {
            $value = SystemSetting::query()->where('key', self::KEY)->value('value');

            // Default ON when the flag has never been set.
            return $value === null ? true : (bool) (int) $value;
        });
    }

    /**
     * Turn enforcement on or off, persisting the choice.
     */
    public static function setEnforced(bool $enabled): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $enabled ? '1' : '0'],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
