<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class PasswordResetOtpService
{
    public function requestOtp(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $otp = (string) random_int(0, 999999);
        $otp = str_pad($otp, 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($email, $otp): void {
            $latest = PasswordResetOtp::query()
                ->where('email', $email)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $cooldown = (int) config('auth.otp.resend_cooldown_seconds');
            if ($latest?->created_at?->gt(now()->subSeconds($cooldown))) {
                throw new TooManyRequestsHttpException(null, 'Please wait before requesting another code.');
            }

            PasswordResetOtp::query()->where('email', $email)->delete();
            PasswordResetOtp::query()->create([
                'email' => $email,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes((int) config('auth.otp.expires_minutes')),
            ]);
        });

        if (! $user) {
            return;
        }

        try {
            Mail::to($email)->queue(new PasswordResetOtpMail(
                $otp,
                (int) config('auth.otp.expires_minutes'),
            ));
        } catch (\Throwable $exception) {
            Log::error('Password reset OTP email could not be queued.', ['exception' => $exception::class]);
        }
    }

    public function verifyOtp(string $email, string $otp): string
    {
        $result = DB::transaction(function () use ($email, $otp): array {
            $challenge = PasswordResetOtp::query()
                ->where('email', strtolower(trim($email)))
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge || $challenge->used_at || $challenge->verified_at || $challenge->expires_at->isPast()) {
                return ['error' => 'The verification code is invalid or expired.', 'status' => 422];
            }

            $maxAttempts = (int) config('auth.otp.max_attempts');
            if ($challenge->attempts >= $maxAttempts) {
                return ['error' => 'Maximum verification attempts exceeded.', 'status' => 429];
            }

            if (! Hash::check($otp, $challenge->otp_hash)) {
                $challenge->increment('attempts');

                if ($challenge->attempts >= $maxAttempts) {
                    return ['error' => 'Maximum verification attempts exceeded.', 'status' => 429];
                }

                return ['error' => 'The verification code is invalid or expired.', 'status' => 422];
            }

            $resetToken = bin2hex(random_bytes(32));
            $challenge->forceFill([
                'reset_token_hash' => Hash::make($resetToken),
                'verified_at' => now(),
                'expires_at' => now()->addMinutes((int) config('auth.otp.reset_token_minutes')),
            ])->save();

            return ['reset_token' => $resetToken];
        });

        if (isset($result['error'])) {
            throw new HttpException($result['status'], $result['error']);
        }

        return $result['reset_token'];
    }

    public function resetPassword(string $email, string $resetToken, string $password): bool
    {
        return DB::transaction(function () use ($email, $resetToken, $password): bool {
            $challenge = PasswordResetOtp::query()
                ->where('email', strtolower(trim($email)))
                ->whereNotNull('reset_token_hash')
                ->whereNotNull('verified_at')
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge || ! Hash::check($resetToken, $challenge->reset_token_hash)) {
                return false;
            }

            $user = User::query()->where('email', $challenge->email)->lockForUpdate()->first();
            if (! $user) {
                return false;
            }

            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->apiTokens()->update(['revoked' => true]);
            $challenge->forceFill(['used_at' => now()])->save();
            PasswordResetOtp::query()
                ->where('email', $challenge->email)
                ->whereKeyNot($challenge->getKey())
                ->delete();

            event(new PasswordReset($user));

            return true;
        });
    }
}
