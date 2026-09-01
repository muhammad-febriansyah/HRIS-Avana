<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Handling rules for the personal identifiers the application stores:
 * the KTP number, the tax number, and bank account numbers.
 *
 * Two jobs. `hash()` produces the deterministic lookup value that makes an
 * encrypted column searchable — the ciphertext differs on every write, so a
 * WHERE on it can never match. And the `mask*` helpers shorten a number for
 * anyone who has no business reading it in full; encryption protects the
 * database file, masking protects the screen.
 */
final class Pii
{
    /**
     * The deterministic lookup value for an identifier.
     *
     * Keyed on the application key so the same NIK hashes differently in
     * another deployment, and so a stolen database cannot be matched against a
     * list of candidate numbers without the key.
     */
    public static function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, self::key());
    }

    /**
     * Whether this viewer may read the employee's identifiers in full.
     *
     * The person themself always may. Beyond that it takes the permission that
     * already governs editing employee records — whoever may change a NIK can
     * necessarily read it.
     */
    public static function visibleTo(?User $viewer, ?Employee $employee = null): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($employee !== null && $employee->user_id !== null && (int) $employee->user_id === (int) $viewer->id) {
            return true;
        }

        return $viewer->isSuperAdmin() || $viewer->hasPermissionTo('employee.update');
    }

    /**
     * A KTP number with only its last four digits legible.
     */
    public static function maskNik(?string $value): ?string
    {
        return self::maskTail($value, 4);
    }

    /**
     * A tax number with only its last four characters legible. Punctuation is
     * kept so the shape still reads as an NPWP.
     */
    public static function maskNpwp(?string $value): ?string
    {
        return self::maskTail($value, 4);
    }

    /**
     * A bank account number with only its last four digits legible — the same
     * amount a bank statement shows.
     */
    public static function maskAccount(?string $value): ?string
    {
        return self::maskTail($value, 4);
    }

    /**
     * Mask everything but the last `$keep` characters.
     */
    public static function maskTail(?string $value, int $keep = 4): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::length($value) <= $keep) {
            return str_repeat('•', Str::length($value));
        }

        return str_repeat('•', Str::length($value) - $keep).Str::substr($value, -$keep);
    }

    /**
     * Return the value in full or masked, depending on the viewer.
     */
    public static function forViewer(?string $value, ?User $viewer, ?Employee $employee = null, int $keep = 4): ?string
    {
        return self::visibleTo($viewer, $employee) ? $value : self::maskTail($value, $keep);
    }

    /**
     * The HMAC key. Falls back to the raw APP_KEY string when it is not
     * base64-encoded, so a deployment that predates the encoding still hashes.
     */
    private static function key(): string
    {
        return Cache::driver('array')->rememberForever('pii.key', static function (): string {
            $key = (string) config('app.key');

            return str_starts_with($key, 'base64:')
                ? (string) base64_decode(substr($key, 7), true)
                : $key;
        });
    }
}
