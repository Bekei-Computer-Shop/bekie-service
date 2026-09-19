<?php

namespace App\Services;

use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class OtpService
{
    /** @return array{challenge: OtpChallenge, code: string} */
    public function issue(?User $user, string $email, string $purpose): array
    {
        $email = strtolower(trim($email));
        $latest = OtpChallenge::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($latest?->last_sent_at?->gt(now()->subSeconds((int) config('otp.resend_cooldown_seconds')))) {
            throw new TooManyRequestsHttpException(null, 'Please wait before requesting another code.');
        }

        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($user, $email, $purpose, $code): OtpChallenge {
            OtpChallenge::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return OtpChallenge::create([
                'user_id' => $user?->id,
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes((int) config('otp.expires_minutes')),
                'last_sent_at' => now(),
            ]);
        });

        return compact('challenge', 'code');
    }

    public function verify(OtpChallenge $challenge, string $code): string
    {
        if ($challenge->used_at || $challenge->verified_at || $challenge->expires_at->isPast()) {
            throw new \InvalidArgumentException('This verification code is invalid or expired.');
        }

        if ($challenge->attempts >= (int) config('otp.max_attempts')) {
            throw new \InvalidArgumentException('Maximum verification attempts exceeded.');
        }

        $challenge->increment('attempts');

        if (! hash_equals($challenge->code_hash, $this->hash($code))) {
            throw new \InvalidArgumentException('The verification code is incorrect.');
        }

        $resetToken = Str::random(64);
        $challenge->forceFill([
            'verified_at' => now(),
            'reset_token_hash' => $this->hash($resetToken),
            'reset_token_expires_at' => now()->addMinutes((int) config('otp.reset_token_minutes')),
        ])->save();

        return $resetToken;
    }

    public function consumeResetToken(string $token, string $purpose): ?OtpChallenge
    {
        $challenge = OtpChallenge::query()
            ->where('purpose', $purpose)
            ->where('reset_token_hash', $this->hash($token))
            ->whereNotNull('verified_at')
            ->whereNull('used_at')
            ->where('reset_token_expires_at', '>', now())
            ->first();

        if ($challenge) {
            $challenge->forceFill(['used_at' => now()])->save();
        }

        return $challenge;
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
