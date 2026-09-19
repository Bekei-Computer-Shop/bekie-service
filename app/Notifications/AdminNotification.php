<?php

namespace App\Notifications;

use Illuminate\Support\Str;

class AdminNotification extends RealtimeNotification
{
    public function __construct(
        string $category,
        string $title,
        string $message,
        array $payload = [],
    ) {
        parent::__construct(Str::uuid()->toString(), $category, $title, $message, $payload);
    }

    public function broadcastType(): string
    {
        return 'admin.notification';
    }
}
