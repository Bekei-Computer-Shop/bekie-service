<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\withHeaders;

uses(RefreshDatabase::class);

const CLOUDINARY_TEST_SECRET = 'super-secret';

function adminTokenForMedia(): string
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

    $user = User::factory()->create([
        'is_admin' => true,
        'is_active' => true,
        'is_banned' => false,
    ]);
    $user->assignRole('admin');

    config()->set('cloudinary.cloud_name', 'test-cloud');
    config()->set('cloudinary.api_key', '123456789012345');
    config()->set('cloudinary.api_secret', CLOUDINARY_TEST_SECRET);
    config()->set('cloudinary.upload_preset', null);

    return (new AdminAuthService)->createAdminToken($user)['access_token'];
}

/**
 * Flatten Guzzle's multipart part list into name => contents.
 *
 * @return array<string, string>
 */
function multipartParts(array $body): array
{
    return collect($body)
        ->filter(fn ($part): bool => is_array($part) && isset($part['name']) && is_string($part['contents'] ?? null))
        ->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']])
        ->all();
}

test('admin can upload media to Cloudinary', function (): void {
    $token = adminTokenForMedia();

    Http::fake([
        'https://api.cloudinary.com/v1_1/test-cloud/image/upload' => Http::response([
            'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1234/photo.jpg',
            'public_id' => 'products/photo.jpg',
            'resource_type' => 'image',
            'format' => 'jpg',
            'bytes' => 12345,
        ], 200),
    ]);

    $response = withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 600, 600),
            'folder' => 'products',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.url', 'https://res.cloudinary.com/test-cloud/image/upload/v1234/photo.jpg');
    $response->assertJsonPath('data.path', 'products/photo.jpg');

    Http::assertSent(function ($request): bool {
        $parts = multipartParts($request->data());

        return $request->url() === 'https://api.cloudinary.com/v1_1/test-cloud/image/upload'
            && ($parts['folder'] ?? null) === 'products';
    });
});

/**
 * Cloudinary computes `sha1(<signable params as sorted query string> . api_secret)`
 * and excludes `file`, `cloud_name`, `api_key` and `resource_type` from the signed
 * string. An HMAC, or an extra signed param, yields 401 Invalid Signature — which
 * a faked 200 response cannot reveal, so assert the signature itself.
 */
test('upload signature matches the scheme Cloudinary verifies', function (): void {
    $token = adminTokenForMedia();

    Http::fake([
        'https://api.cloudinary.com/v1_1/test-cloud/image/upload' => Http::response([
            'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1/x.jpg',
            'public_id' => 'products/x',
        ], 200),
    ]);

    withHeaders(['Authorization' => 'Bearer '.$token])
        ->postJson('/api/v1/admin/media', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
            'folder' => 'products',
        ])
        ->assertCreated();

    Http::assertSent(function ($request): bool {
        $parts = multipartParts($request->data());

        expect($parts)->toHaveKeys(['api_key', 'timestamp', 'signature', 'folder']);

        // resource_type belongs in the URL path, never in the signed body.
        expect($parts)->not->toHaveKey('resource_type');

        // Raw, unencoded, alphabetical — the exact string Cloudinary reports back
        // in its "String to sign - '...'" error.
        $expected = sha1("folder={$parts['folder']}&timestamp={$parts['timestamp']}".CLOUDINARY_TEST_SECRET);

        expect($parts['signature'])->toBe($expected);

        return true;
    });
});

test('delete signature matches the scheme Cloudinary verifies', function (): void {
    $token = adminTokenForMedia();

    Http::fake([
        'https://api.cloudinary.com/v1_1/test-cloud/image/destroy' => Http::response(['result' => 'ok'], 200),
    ]);

    withHeaders(['Authorization' => 'Bearer '.$token])
        ->deleteJson('/api/v1/admin/media', ['path' => 'products/photo'])
        ->assertNoContent();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), '/image/destroy')) {
            return false;
        }

        $data = $request->data();

        // 'products/photo' must be signed with a literal slash, not %2F.
        $expected = sha1("public_id={$data['public_id']}&timestamp={$data['timestamp']}".CLOUDINARY_TEST_SECRET);

        expect($data['public_id'])->toBe('products/photo');
        expect($data['signature'])->toBe($expected);

        return true;
    });
});

test('media listing authenticates against the Cloudinary admin API', function (): void {
    $token = adminTokenForMedia();

    Http::fake([
        'https://api.cloudinary.com/v1_1/test-cloud/resources/image*' => Http::response(['resources' => []], 200),
    ]);

    withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/admin/media')
        ->assertOk();

    // The Admin API rejects unauthenticated reads with 401 Invalid credentials.
    Http::assertSent(fn ($request): bool => $request->hasHeader(
        'Authorization',
        'Basic '.base64_encode('123456789012345:'.CLOUDINARY_TEST_SECRET)
    ));
});
