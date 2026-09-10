<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Client\V1;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->assertJsonPath('data.email', 'user@example.com');

        $this->assertNotNull($response->json('data.avatar'));
        Storage::disk('public')->assertExists($this->user->fresh()->avatar);
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
            ->assertJsonPath('errors.avatar.0', 'The file must be a valid image.');
    }

    public function test_upload_profile_image_rejects_oversized_file(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg')->size(6000);

        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'The image size cannot exceed 5 MB.');
    }

    public function test_upload_profile_image_requires_image(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->jwtToken}")
            ->postJson('/api/v1/profile/avatar', []);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'Profile image is required.');
    }

    public function test_upload_profile_image_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertUnauthorized();
    }
}
