<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminPermissionsSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\ReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('review permissions are assigned to the admin and client roles', function (): void {
    $admin = Role::findByName('admin', 'api');
    $user = Role::findByName('user', 'api');

    expect($admin->hasPermissionTo('reviews.view', 'api'))->toBeTrue()
        ->and($admin->hasPermissionTo('reviews.update', 'api'))->toBeTrue()
        ->and($admin->hasPermissionTo('reviews.delete', 'api'))->toBeTrue()
        ->and($user->hasPermissionTo('client.reviews.manage', 'api'))->toBeTrue()
        ->and(Permission::where('name', 'client.reviews.manage')->where('guard_name', 'api')->exists())->toBeTrue();
});

test('review seeder creates 100 idempotent reviews across every status', function (): void {
    foreach (CustomerSeeder::shopperEmails() as $email) {
        User::factory()->create(['email' => $email]);
    }
    Product::factory()->create();

    $seeder = new ReviewSeeder;
    $seeder->run();

    expect(Review::query()->count())->toBe(100);

    $countsByStatus = Review::query()
        ->selectRaw('status, COUNT(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status')
        ->all();
    ksort($countsByStatus);

    expect($countsByStatus)->toBe([
        'approved' => 25,
        'hidden' => 25,
        'pending' => 25,
        'rejected' => 25,
    ]);

    $seeder->run();

    expect(Review::query()->count())->toBe(100);
});
