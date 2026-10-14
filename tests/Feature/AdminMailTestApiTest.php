<?php

declare(strict_types=1);

use App\Mail\AdminPasswordRecoveryOtpMail;
use App\Mail\ContactMessageMail;
use App\Mail\CustomerVerificationOtpMail;
use App\Mail\MailtrapTestMail;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use App\Services\AdminAuthService;
use App\Services\AuthService;
use Database\Seeders\AdminPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AdminPermissionsSeeder::class);
});

test('the SMTP mailer uses a supported scheme', function (): void {
    expect(config('mail.mailers.smtp.scheme'))->toBe('smtp');
});

test('all email templates render with their expected content', function (): void {
    $mailables = [
        [new AdminPasswordRecoveryOtpMail('admin-code-123', 10), ['Admin password recovery', 'admin-code-123', '10 minutes']],
        [new ContactMessageMail('Contact Tester', 'tester@example.com', 'Order question', 'A package arrived damaged.'), ['Contact Tester', 'tester@example.com', 'Order question', 'A package arrived damaged.']],
        [new CustomerVerificationOtpMail('verify-code-456', 15), ['Verify your email', 'verify-code-456', '15 minutes']],
        [new PasswordResetOtpMail('reset-code-789', 20), ['Password Reset Request', 'reset-code-789', '20 minutes']],
        [new MailtrapTestMail, ['Email delivery test', 'Delivery check passed', 'successfully connected', 'no action is needed']],
    ];

    foreach ($mailables as [$mailable, $expectedContent]) {
        $rendered = $mailable->render();

        foreach ($expectedContent as $content) {
            $this->assertStringContainsString($content, $rendered);
        }
    }
});

test('only an authenticated admin can send the mail configuration test', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/admin/mail/test', ['email' => 'recipient@example.com'])
        ->assertUnauthorized();

    $customer = User::factory()->create();
    $clientToken = (new AuthService)->createToken(
        $customer,
        Request::create('/api/v1/auth/login', 'POST'),
    )['access_token'];

    $this->withToken($clientToken)
        ->postJson('/api/v1/admin/mail/test', ['email' => 'recipient@example.com'])
        ->assertUnauthorized();

    $admin = User::factory()->superAdmin()->create();
    $adminToken = (new AdminAuthService)->createAdminToken($admin)['access_token'];

    $this->withToken($adminToken)
        ->postJson('/api/v1/admin/mail/test', ['email' => 'recipient@example.com'])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.recipient', 'recipient@example.com');

    Mail::assertSent(MailtrapTestMail::class);
});

test('the mail configuration test validates its recipient', function (): void {
    Mail::fake();

    $admin = User::factory()->superAdmin()->create();
    $adminToken = (new AdminAuthService)->createAdminToken($admin)['access_token'];

    $this->withToken($adminToken)
        ->postJson('/api/v1/admin/mail/test', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Mail::assertNothingSent();
});
