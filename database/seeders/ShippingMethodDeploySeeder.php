<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/**
 * The deploy-time counterpart of ShippingMethodSeeder, run by the GitHub
 * deploy workflow on every release. It only creates the default methods a
 * fresh database is missing, so a new environment can take orders.
 *
 * ShippingMethodSeeder resets the defaults (updateOrCreate + restore), which
 * suits local and demo data but would undo what admins do in Settings:
 * repriced, renamed, deactivated or deleted methods. This one never touches a
 * method whose code already exists — trashed ones included, so a method an
 * admin deleted stays deleted.
 */
class ShippingMethodDeploySeeder extends Seeder
{
    public function run(): void
    {
        $existing = ShippingMethod::withTrashed()->pluck('code')->all();

        foreach (ShippingMethodSeeder::METHODS as $attributes) {
            if (! in_array($attributes['code'], $existing, true)) {
                ShippingMethod::query()->create($attributes);
            }
        }
    }
}
