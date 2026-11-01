<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ApiToken $token;

    private string $jwtToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+12345678901',
        ]);

        config([
            'cloudinary.cloud_name' => 'test-cloud',
            'cloudinary.api_key' => 'test-api-key',
            'cloudinary.api_secret' => 'test-api-secret',
            'cloudinary.folder' => 'uploads',
        ]);
        Http::fake([
            'https://api.cloudinary.com/v1_1/test-cloud/image/upload' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v123/uploads/avatars/customer-profile.jpg',
                'public_id' => 'uploads/avatars/customer-'.$this->user->id,
            ]),
        ]);

        $jti = Str::random(64);
        $this->token = ApiToken::factory()->for($this->user)->create([
            'scope' => 'client',
            'token' => hash('sha256', $jti),
        ]);

        $jwtService = new JwtService;
        $this->jwtToken = $jwtService->encode([
            'sub' => (string) $this->user->id,
            'jti' => $jti,
            'scope' => 'client',
        ], 60 * 24 * 60 * 60);
    }

    public function test_get_profile_success(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Profile retrieved successfully.')
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.last_name', 'Doe')
            ->assertJsonPath('data.phone', '+12345678901')
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_banned', false)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'phone',
                    'first_name',
                    'last_name',
                    'name',
                    'avatar',
                    'role',
                    'is_active',
                    'is_banned',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_get_profile_with_avatar(): void
    {
        $this->user->update(['avatar' => 'avatars/test.jpg']);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('data.avatar', '/storage/avatars/test.jpg');
    }

    public function test_get_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertUnauthorized();
    }

    public function test_upload_profile_image_success(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Profile image updated successfully.')
            ->assertJsonPath('data.id', $this->user->id)
            ->assertJsonPath('data.email', 'user@example.com')
            ->assertJsonPath('data.avatar', 'https://res.cloudinary.com/test-cloud/image/upload/v123/uploads/avatars/customer-profile.jpg');

        $this->assertSame($response->json('data.avatar'), $this->user->fresh()->avatar);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.cloudinary.com/v1_1/test-cloud/image/upload');
    }

    public function test_upload_profile_image_replaces_old_image(): void
    {
        $oldFile = UploadedFile::fake()->image('old.jpg');
        Storage::disk('public')->put('avatars/old.jpg', $oldFile);
        $this->user->update(['avatar' => 'avatars/old.jpg']);

        $newFile = UploadedFile::fake()->image('new.jpg');

        $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $newFile,
            ])
            ->assertOk();

        $this->assertNotEquals('avatars/old.jpg', $this->user->fresh()->avatar);
    }

    public function test_upload_profile_image_accepts_png(): void
    {
        $file = UploadedFile::fake()->image('avatar.png');

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk();
    }

    public function test_upload_profile_image_accepts_gif(): void
    {
        $file = UploadedFile::fake()->image('avatar.gif');

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk();
    }

    public function test_upload_profile_image_rejects_non_image(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'avatar_invalid_type')
            ->assertJsonPath('message', "This file isn't a photo. Please upload a JPG, PNG or GIF image.")
            ->assertJsonPath('errors.avatar', ["This file isn't a photo. Please upload a JPG, PNG or GIF image."]);
    }

    public function test_upload_profile_image_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(6000);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'avatar_too_large')
            ->assertJsonPath('message', 'Your photo is larger than 5 MB. Please choose a smaller photo and try again.');
    }

    public function test_upload_profile_image_requires_image(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', []);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'avatar_required')
            ->assertJsonPath('message', 'Please choose a photo to upload.')
            ->assertJsonPath('data', null);
    }

    public function test_upload_profile_image_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_upload_profile_image_explains_a_file_php_rejected_as_too_large(): void
    {
        $this->postAvatar($this->failedUpload(UPLOAD_ERR_INI_SIZE))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'avatar_too_large')
            ->assertJsonPath('message', 'Your photo is too large. Please choose a photo under 5 MB and try again.');
    }

    public function test_upload_profile_image_explains_an_interrupted_upload(): void
    {
        $this->postAvatar($this->failedUpload(UPLOAD_ERR_PARTIAL))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'avatar_upload_interrupted')
            ->assertJsonPath('message', "Your photo didn't finish uploading. Please check your connection and try again.");
    }

    public function test_upload_profile_image_reports_a_photo_cloudinary_cannot_process(): void
    {
        $this->fakeCloudinaryFailure(400, 'Invalid image file');

        $this->postAvatar(UploadedFile::fake()->image('avatar.jpg'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'avatar_unprocessable')
            ->assertJsonPath('message', "We couldn't process this photo. Please choose a different photo and try again.");

        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_upload_profile_image_hides_cloudinary_outages_behind_a_retry_message(): void
    {
        $this->fakeCloudinaryFailure(500, 'Internal error');

        $response = $this->postAvatar(UploadedFile::fake()->image('avatar.jpg'))
            ->assertStatus(503)
            ->assertJsonPath('code', 'avatar_service_unavailable')
            ->assertJsonPath('message', "We couldn't update your photo right now. Please try again in a few minutes.");

        $this->assertStringNotContainsStringIgnoringCase('cloudinary', $response->getContent());
    }

    public function test_upload_profile_image_does_not_expose_missing_configuration(): void
    {
        config(['cloudinary.api_secret' => null]);

        $response = $this->postAvatar(UploadedFile::fake()->image('avatar.jpg'))
            ->assertStatus(503)
            ->assertJsonPath('code', 'avatar_service_unavailable');

        $this->assertStringNotContainsStringIgnoringCase('cloudinary', $response->getContent());
    }

    public function test_upload_larger_than_the_server_accepts_gets_a_clear_message(): void
    {
        $this->call('POST', '/api/v1/profile/avatar', server: [
            'CONTENT_LENGTH' => (string) (1024 * 1024 * 1024),
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$this->jwtToken}",
        ])
            ->assertStatus(413)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', "The file you're uploading is too large. Please choose a smaller file and try again.");
    }

    /** Replace the successful upload faked in setUp(); the first matching fake wins. */
    private function fakeCloudinaryFailure(int $status, string $error): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*' => Http::response(['error' => ['message' => $error]], $status)]);
    }

    private function postAvatar(UploadedFile $file): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', ['avatar' => $file]);
    }

    /** A file PHP refused before validation ran, as it arrives with a PHP upload error. */
    private function failedUpload(int $error): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', $error, true);
    }
}
