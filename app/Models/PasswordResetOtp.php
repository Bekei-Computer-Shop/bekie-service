<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp_hash',
        'reset_token_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }
}
