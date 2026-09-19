<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Http\Requests\Api\Admin\V1\ResetPasswordWithOtpRequest;
use App\Http\Requests\Api\Admin\V1\VerifyResetOtpRequest;
use App\Mail\AdminPasswordRecoveryOtpMail;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PasswordRecoveryController extends BaseAdminController
{
    public function __construct(private readonly OtpService $otps) {}

    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = strtolower(trim($request->string('email')->toString()));
        $user = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->where(fn ($query) => $query->where('email', $email)->orWhere('recovery_email', $email))
            ->first();

        if ($user) {
            try {
                $recoveryEmail = strtolower((string) ($user->recovery_email ?: $user->email));
                $issued = $this->otps->issue($user, $recoveryEmail, 'admin_password_reset');
                Mail::to($recoveryEmail)->queue(new AdminPasswordRecoveryOtpMail($issued['code'], (int) config('otp.expires_minutes')));
            } catch (TooManyRequestsHttpException) {
                // Keep this response generic to prevent account enumeration.
            } catch (\Throwable $exception) {
                Log::error('Admin recovery email could not be queued.', ['exception' => $exception::class]);
            }
        }

        return $this->success(message: 'If the account exists, recovery instructions have been sent.');
    }

    public function verify(VerifyResetOtpRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->string('email')->toString()));
        $challenge = OtpChallenge::query()
            ->where('email', $email)
            ->where('purpose', 'admin_password_reset')
            ->latest('id')
            ->first();

        if (! $challenge) {
            return $this->error('The verification code is invalid or expired.', 422);
        }

        try {
            $resetToken = $this->otps->verify($challenge, $request->string('otp')->toString());
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(['reset_token' => $resetToken], 'Recovery code verified.');
    }

    public function reset(ResetPasswordWithOtpRequest $request): JsonResponse
    {
        $challenge = $this->otps->consumeResetToken($request->string('reset_token')->toString(), 'admin_password_reset');

        if (! $challenge || ! $challenge->user || ! $challenge->user->is_admin) {
            return $this->error('The reset authorization is invalid or expired.', 422);
        }

        $user = $challenge->user;
        $user->password = $request->string('new_password')->toString();
        $user->save();
        $user->apiTokens()->where('scope', 'admin')->update(['revoked' => true]);

        return $this->success(message: 'Password reset successfully.');
    }
}
