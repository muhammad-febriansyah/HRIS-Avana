<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a column at rest, tolerating values written before it was encrypted.
 *
 * Laravel's built-in `encrypted` cast throws on any value it cannot decrypt,
 * which turns one legacy row into a 500 on every page that touches it. This
 * cast returns such a value unchanged instead: the backfill migration converts
 * the existing rows, and anything it missed keeps working while still being
 * written back encrypted on the next save.
 *
 * An empty string is stored as null. Encrypting `''` yields a non-empty
 * ciphertext, which would make `NULLIF(col, '')` and `filled()` checks in
 * reporting queries read a blank field as filled in.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            // Written before this column was encrypted. Readable as-is.
            return (string) $value;
        }

        return $plain;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
