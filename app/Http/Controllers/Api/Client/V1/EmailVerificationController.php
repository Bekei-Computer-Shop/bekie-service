<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\VerifyEmailOtpRequest;
use App\Mail\CustomerVerificationOtpMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class EmailVerificationController extends BaseApiController
{
    public function __construct(
        private readonly OtpService $otps,
        private readonly AuthService $authService,
    ) {}

    public function verify(VerifyEmailOtpRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->string('email')->toString()));
        $user = User::query()->where('email', $email)->first();
        $challenge = OtpChallenge::query()
            ->where('email', $email)
            ->where('purpose', 'customer_email_verification')
            ->latest('id')
            ->first();

        if (! $user || ! $challenge) {
            return $this->error('The verification code is invalid or expired.', 422);
        }

        try {
            $this->otps->verify($challenge, $request->string('otp')->toString());
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $tokens = $this->authService->createToken($user, $request);

        return $this->success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokens['expires_at']->toDateTimeString(),
        ], 'Email verified successfully.');
    }

    public function resend(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = strtolower(trim($request->string('email')->toString()));
        $user = User::query()->where('email', $email)->whereNull('email_verified_at')->first();

        if (! $user) {
            return $this->success(message: 'If the account can receive verification mail, a new code has been sent.');
        }

        try {
            $issued = $this->otps->issue($user, $email, 'customer_email_verification');
            Mail::to($email)->queue(new CustomerVerificationOtpMail($issued['code'], (int) config('otp.expires_minutes')));
        } catch (TooManyRequestsHttpException $exception) {
            return $this->error($exception->getMessage(), 429);
        } catch (\Throwable $exception) {
            Log::error('Customer verification email could not be queued.', ['exception' => $exception::class]);
        }

        return $this->success(message: 'If the account can receive verification mail, a new code has been sent.');
    }
}
