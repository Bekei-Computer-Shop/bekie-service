<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Route-binds the model on its `uuid` column instead of the numeric id.
 *
 * The guard in resolveRouteBindingQuery matters on Postgres: `uuid` is a real
 * `uuid` column there, so a request for `/orders/39` (an id that leaked into a
 * link) would otherwise run `where uuid = 39` and throw a 22P02 "invalid input
 * syntax for type uuid" — surfacing as a 500 instead of a clean 404. Rejecting
 * anything that is not a valid UUID up front keeps the miss a 404.
 */
trait RoutesByUuid
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'uuid' && ! Str::isUuid((string) $value)) {
            // Force an empty result so route binding returns a 404.
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * Populate `uuid` on create for models that pull this trait in.
     */
    public static function bootRoutesByUuid(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('uuid')) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
