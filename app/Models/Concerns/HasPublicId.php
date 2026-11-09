<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a random, unguessable public ID ("uid", a lowercase ULID)
 * and uses it in URLs instead of the auto-increment number.
 */
trait HasPublicId
{
    public const PUBLIC_ID_PATTERN = '[0-9a-hjkmnp-tv-z]{26}';

    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
                $model->uid = strtolower((string) Str::ulid());
            }
        });
    }

    /** Route model binding and route() helpers use the uid. */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }
}
