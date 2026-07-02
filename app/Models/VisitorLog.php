<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $action
 * @property string|null $target_url
 * @property string|null $session_id
 * @property Carbon $created_at
 */
class VisitorLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'action',
        'target_url',
        'session_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function log(string $ipAddress, string $action, ?string $targetUrl = null, ?string $sessionId = null): self
    {
        return self::create([
            'ip_address' => $ipAddress,
            'action' => $action,
            'target_url' => $targetUrl,
            'session_id' => $sessionId,
            'created_at' => now(),
        ]);
    }
}
