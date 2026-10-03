<?php

use App\Mail\PasswordResetOtpMail;
use App\Models\ApiToken;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function requestPasswordResetOtp(string $email): void
{
    Mail::fake();
    test()->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertOk();
}

function queuedPasswordResetOtp(): string
{
    $otp = '';
    Mail::assertQueued(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp): bool {
        $otp = $mail->otp;

        return true;
    });

    return $otp;
}

test('forgot password queues a hashed OTP for an existing account', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'customer@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('message', 'If the account exists, password reset instructions have been sent.');

    $challenge = PasswordResetOtp::query()->firstOrFail();
    Mail::assertQueued(PasswordResetOtpMail::class, fn (PasswordResetOtpMail $mail): bool => Hash::check($mail->otp, $challenge->otp_hash)
    );
    expect($challenge->otp_hash)->not->toBe('');
});

test('forgot password returns the same response and queues no mail for an unknown account', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'customer@example.com']);
    $existingResponse = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
    $unknownResponse = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com']);

    $unknownResponse->assertOk()->assertExactJson($existingResponse->json());
    Mail::assertQueued(PasswordResetOtpMail::class, 1);
});

test('correct OTP returns a reset token', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'customer@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'customer@example.com'])->assertOk();
    $otp = queuedPasswordResetOtp();

    $this->postJson('/api/v1/auth/verify-otp', [
        'email' => 'customer@example.com',
        'otp' => $otp,
    ])->assertOk()->assertJsonPath('data.reset_token', fn (string $token): bool => strlen($token) === 64);

    expect(PasswordResetOtp::query()->firstOrFail()->reset_token_hash)->not->toBe($otp);
});

test('wrong OTP increments attempts and locks the challenge after the configured limit', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'customer@example.com']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'customer@example.com'])->assertOk();

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'customer@example.com',
            'otp' => '000000',
        ]);
        $response->assertStatus($attempt === 5 ? 429 : 422);
    }

    expect(PasswordResetOtp::query()->firstOrFail()->attempts)->toBe(5);
});

test('expired OTP is rejected', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'customer@example.com']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'customer@example.com'])->assertOk();
    PasswordResetOtp::query()->firstOrFail()->update(['expires_at' => now()->subSecond()]);

    $this->postJson('/api/v1/auth/verify-otp', [
        'email' => 'customer@example.com',
        'otp' => queuedPasswordResetOtp(),
    ])->assertUnprocessable();
});

test('resend cooldown is enforced', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'customer@example.com']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'customer@example.com'])->assertOk();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'customer@example.com'])
        ->assertStatus(429);
});

test('password reset revokes API tokens and makes the reset token single use', function (): void {
    Mail::fake();
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'OldPassword123!',
    ]);
    ApiToken::query()->create([
        'user_id' => $user->id,
        'token' => str_repeat('a', 64),
        'refresh_token' => str_repeat('b', 64),
        'expires_at' => now()->addDay(),
        'refresh_expires_at' => now()->addDays(2),
    ]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    $otp = queuedPasswordResetOtp();
    $verify = $this->postJson('/api/v1/auth/verify-otp', ['email' => $user->email, 'otp' => $otp])->assertOk();
    $resetToken = $verify->json('data.reset_token');

    $payload = [
        'email' => $user->email,
        'reset_token' => $resetToken,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Password reset successfully.');

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    expect($user->apiTokens()->firstOrFail()->revoked)->toBeTrue();
    Event::assertDispatched(PasswordReset::class);

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
});
