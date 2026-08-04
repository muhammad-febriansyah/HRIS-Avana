<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Address a record in the URL by an opaque key instead of its primary key.
 *
 * A sequential id in a link tells anyone how many of a thing exist, invites
 * walking the range, and makes a link pasted into a chat carry a guessable
 * neighbour. A ULID says none of that.
 *
 * The primary key stays exactly where it is — every foreign key in the schema
 * points at it, and none of that belongs in a URL. This only changes what the
 * router binds on, so `route($name, $model)` and `{model}` bindings both pick
 * the opaque key up on their own.
 *
 * Requires a unique, nullable `public_id` column of at least 26 characters.
 *
 * @phpstan-require-extends Model
 */
trait HasPublicId
{
    /**
     * Give a new record its key before it is written, so nothing ever reaches
     * the database without one.
     */
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
