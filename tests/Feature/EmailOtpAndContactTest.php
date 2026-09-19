<?php

use App\Mail\AdminPasswordRecoveryOtpMail;
use App\Mail\ContactMessageMail;
use App\Mail\CustomerVerificationOtpMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('contact form validates and queues a support email', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/v1/contact', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Product question',
        'message' => 'Please contact me.',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Your message has been sent.');
    Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->senderEmail === 'jane@example.com');
});

test('customer email registration queues an OTP and does not issue a token', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'customer@example.com',
        'first_name' => 'Customer',
        'password' => 'SecurePassword123',
        'password_confirmation' => 'SecurePassword123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email_verification_required', true)
        ->assertJsonMissingPath('data.access_token');
    Mail::assertQueued(CustomerVerificationOtpMail::class);
    expect(OtpChallenge::where('purpose', 'customer_email_verification')->count())->toBe(1);
});

test('correct customer OTP verifies email and OTP cannot be reused', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    $issued = app(OtpService::class)->issue($user, $user->email, 'customer_email_verification');

    $response = $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => $issued['code'],
    ]);

    $response->assertOk()->assertJsonPath('message', 'Email verified successfully.');
    expect($user->fresh()->email_verified_at)->not->toBeNull();

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => $issued['code'],
    ])->assertUnprocessable();
});

test('admin forgot password is generic and reset requires verified OTP', function (): void {
    Mail::fake();
    $admin = User::factory()->superAdmin()->create([
        'email' => 'admin@example.com',
        'password' => 'OldPassword123',
    ]);

    $this->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'unknown@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'If the account exists, recovery instructions have been sent.');

    $this->postJson('/api/v1/admin/auth/forgot-password', ['email' => $admin->email])
        ->assertOk();
    Mail::assertQueued(AdminPasswordRecoveryOtpMail::class);

    OtpChallenge::query()->where('purpose', 'admin_password_reset')->update(['last_sent_at' => now()->subMinute()]);
    $issued = app(OtpService::class)->issue($admin, $admin->email, 'admin_password_reset');
    $verify = $this->postJson('/api/v1/admin/auth/verify-reset-otp', [
        'email' => $admin->email,
        'otp' => $issued['code'],
    ])->assertOk();

    $this->postJson('/api/v1/admin/auth/reset-password', [
        'reset_token' => $verify->json('data.reset_token'),
        'new_password' => 'NewPassword123',
        'confirm_password' => 'NewPassword123',
    ])->assertOk();

    expect(password_verify('NewPassword123', $admin->fresh()->password))->toBeTrue();
});

test('incorrect OTP is rejected and attempts are counted', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    app(OtpService::class)->issue($user, $user->email, 'customer_email_verification');

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '000000',
    ])->assertUnprocessable();

    expect(OtpChallenge::first()->attempts)->toBe(1);
});

test('expired OTPs and immediate resends are rejected', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    $issued = app(OtpService::class)->issue($user, $user->email, 'customer_email_verification');
    OtpChallenge::query()->firstOrFail()->update([
        'expires_at' => now()->subSecond(),
        'last_sent_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => $issued['code'],
    ])->assertUnprocessable();

    app(OtpService::class)->issue($user, $user->email, 'customer_email_verification');

    $this->postJson('/api/v1/auth/resend-email-otp', [
        'email' => $user->email,
    ])->assertStatus(429);
});

test('OTP verification stops after the configured maximum attempts', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'customer@example.com']);
    app(OtpService::class)->issue($user, $user->email, 'customer_email_verification');

    for ($attempt = 0; $attempt < (int) config('otp.max_attempts'); $attempt++) {
        $this->postJson('/api/v1/auth/verify-email-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/verify-email-otp', [
        'email' => $user->email,
        'otp' => '000000',
    ])->assertUnprocessable()->assertJsonPath('message', 'Maximum verification attempts exceeded.');
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
