<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'refresh_token',
        'expires_at',
        'refresh_expires_at',
        'revoked',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRefreshExpired(): bool
    {
        return $this->refresh_expires_at->isPast();
    }

    public function revoke(): bool
    {
        return $this->update(['revoked' => true]);
    }
}
