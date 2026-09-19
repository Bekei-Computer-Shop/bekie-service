<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RealtimeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(
        string $id,
        public readonly string $category,
        public readonly string $title,
        public readonly string $message,
        public readonly array $payload = [],
    ) {
        $this->id = $id;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function broadcastType(): string
    {
        return 'realtime.notification';
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'type' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->payload,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
