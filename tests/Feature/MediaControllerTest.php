<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('admin can upload media to Cloudinary', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

    $user = User::factory()->create([
        'is_admin' => true,
        'is_active' => true,
        'is_banned' => false,
    ]);
    $user->assignRole('admin');

    $tokens = (new AdminAuthService)->createAdminToken($user);
    Http::fake([
        'https://api.cloudinary.com/v1_1/test-cloud/upload' => Http::response([
            'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1234/photo.jpg',
            'public_id' => 'products/photo.jpg',
            'resource_type' => 'image',
            'format' => 'jpg',
            'bytes' => 12345,
        ], 200),
    ]);

    config()->set('cloudinary.cloud_name', 'test-cloud');
    config()->set('cloudinary.api_key', '123456789012345');
    config()->set('cloudinary.api_secret', 'super-secret');
    config()->set('cloudinary.upload_preset', null);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$tokens['access_token']])
        ->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 600, 600),
            'folder' => 'products',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.url', 'https://res.cloudinary.com/test-cloud/image/upload/v1234/photo.jpg');
    $response->assertJsonPath('data.path', 'products/photo.jpg');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.cloudinary.com/v1_1/test-cloud/upload'
            && collect($body)->contains(fn (array $part): bool => ($part['name'] ?? null) === 'folder' && ($part['contents'] ?? null) === 'products');
    });
});
