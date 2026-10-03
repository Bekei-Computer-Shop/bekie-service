<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Requests\Api\Client\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\Client\V1\ResetPasswordWithOtpRequest;
use App\Http\Requests\Api\Client\V1\VerifyPasswordResetOtpRequest;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PasswordResetController extends BaseApiController
{
    public function __construct(private readonly PasswordResetOtpService $passwordResets) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->passwordResets->requestOtp($request->string('email')->toString());
        } catch (TooManyRequestsHttpException $exception) {
            return $this->error($exception->getMessage(), 429);
        }

        return $this->success(message: 'If the account exists, password reset instructions have been sent.');
    }

    public function verify(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        try {
            $resetToken = $this->passwordResets->verifyOtp(
                $request->string('email')->toString(),
                $request->string('otp')->toString(),
            );
        } catch (HttpExceptionInterface $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->success(['reset_token' => $resetToken], 'Verification code accepted.');
    }

    public function reset(ResetPasswordWithOtpRequest $request): JsonResponse
    {
        $reset = $this->passwordResets->resetPassword(
            $request->string('email')->toString(),
            $request->string('reset_token')->toString(),
            $request->string('password')->toString(),
        );

        if (! $reset) {
            return $this->error('The reset authorization is invalid or expired.', 422);
        }

        return $this->success(message: 'Password reset successfully.');
    }
}
