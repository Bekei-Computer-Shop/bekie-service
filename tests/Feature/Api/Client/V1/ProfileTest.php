<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '1234567890',
    ]);

    $this->token = ApiToken::factory()->create([
        'user_id' => $this->user->id,
        'scope' => 'client',
    ]);
});

describe('Get User Profile', function () {
    test('authenticated user can get their profile', function () {
        $response = $this->getJson(
            '/api/v1/profile',
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'avatar',
                'is_active',
                'created_at',
                'updated_at',
            ],
        ]);
        expect($response['data']['email'])->toBe('john@example.com');
        expect($response['data']['first_name'])->toBe('John');
    });

    test('unauthenticated user cannot get profile', function () {
        $response = $this->getJson('/api/v1/profile');

        $response->assertUnauthorized();
    });
});

describe('Update User Profile', function () {
    test('authenticated user can update their profile', function () {
        $response = $this->patchJson(
            '/api/v1/profile',
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'phone' => '9876543210',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Jane');
        $response->assertJsonPath('data.last_name', 'Smith');
        $response->assertJsonPath('data.email', 'jane@example.com');
        $response->assertJsonPath('data.phone', '9876543210');

        $this->user->refresh();
        expect($this->user->first_name)->toBe('Jane');
        expect($this->user->last_name)->toBe('Smith');
    });

    test('user can update only some profile fields', function () {
        $response = $this->patchJson(
            '/api/v1/profile',
            ['first_name' => 'Jane'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Jane');
        $response->assertJsonPath('data.last_name', 'Doe');

        $this->user->refresh();
        expect($this->user->first_name)->toBe('Jane');
        expect($this->user->last_name)->toBe('Doe');
    });

    test('email must be unique when updating profile', function () {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->patchJson(
            '/api/v1/profile',
            ['email' => 'taken@example.com'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    });

    test('phone must be unique when updating profile', function () {
        User::factory()->create(['phone' => '5555555555']);

        $response = $this->patchJson(
            '/api/v1/profile',
            ['phone' => '5555555555'],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('phone');
    });
});

describe('Change Password', function () {
    test('user can change password with correct current password', function () {
        $this->user->update(['password' => 'OldPassword123!']);

        $response = $this->postJson(
            '/api/v1/profile/change-password',
            [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('message', 'Password changed successfully.');

        $this->user->refresh();
        expect(Hash::check('NewPassword456!', $this->user->password))->toBeTrue();
    });

    test('user cannot change password with incorrect current password', function () {
        $this->user->update(['password' => 'OldPassword123!']);

        $response = $this->postJson(
            '/api/v1/profile/change-password',
            [
                'current_password' => 'WrongPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('current_password');
    });

    test('new password confirmation must match', function () {
        $this->user->update(['password' => 'OldPassword123!']);

        $response = $this->postJson(
            '/api/v1/profile/change-password',
            [
                'current_password' => 'OldPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'DifferentPassword456!',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('new_password');
    });

    test('new password must meet complexity requirements', function () {
        $this->user->update(['password' => 'OldPassword123!']);

        $response = $this->postJson(
            '/api/v1/profile/change-password',
            [
                'current_password' => 'OldPassword123!',
                'new_password' => 'weak',
                'new_password_confirmation' => 'weak',
            ],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('new_password');
    });
});

describe('Update Profile Image', function () {
    test('user can upload profile image', function () {
        $image = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->postJson(
            '/api/v1/profile/image',
            ['image' => $image],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertOk();
        $response->assertJsonPath('message', 'Profile image updated successfully.');

        $this->user->refresh();
        expect($this->user->avatar)->toContain('avatar_');
        expect($this->user->avatar)->toContain('.jpg');
    });

    test('image must be provided', function () {
        $response = $this->postJson(
            '/api/v1/profile/image',
            [],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    });

    test('image must be valid image file', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson(
            '/api/v1/profile/image',
            ['image' => $file],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    });

    test('image must not exceed size limit', function () {
        $image = UploadedFile::fake()->image('avatar.jpg', 100, 100)->size(6000);

        $response = $this->postJson(
            '/api/v1/profile/image',
            ['image' => $image],
            ['Authorization' => 'Bearer '.$this->token->token]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('image');
    });

    test('user can upload different image formats', function () {
        $formats = ['avatar.png', 'avatar.gif', 'avatar.webp'];

        foreach ($formats as $filename) {
            $image = UploadedFile::fake()->image($filename, 100, 100);

            $response = $this->postJson(
                '/api/v1/profile/image',
                ['image' => $image],
                ['Authorization' => 'Bearer '.$this->token->token]
            );

            $response->assertOk();
        }
    });
});
