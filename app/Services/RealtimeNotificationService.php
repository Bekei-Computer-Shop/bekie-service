<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Database\Eloquent\Builder;

class RealtimeNotificationService
{
    public function orderStatusChanged(Order $order, ?string $previousStatus, string $status): void
    {
        if (! $order->user) {
            return;
        }

        $seed = 'order-status-'.$order->id.'-'.($order->updated_at?->getTimestamp() ?? now()->getTimestamp());
        $this->notify($order->user, 'order_tracking', 'Order Updated', sprintf('Order %s is now %s.', $order->order_number, $status), [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $status,
            'previous_status' => $previousStatus,
        ], $seed);
    }

    public function paymentStatusChanged(Order $order, ?string $previousStatus, string $status): void
    {
        $message = sprintf('Payment for order %s is %s.', $order->order_number, $status);
        $payload = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_status' => $status,
            'previous_payment_status' => $previousStatus,
        ];
        $seed = 'payment-'.$order->id.'-'.$status.'-'.$order->updated_at?->getTimestamp();

        if ($order->user) {
            $this->notify($order->user, 'payment', 'Payment Updated', $message, $payload, $seed);
        }

        User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->each(fn (User $admin) => $this->notify($admin, 'payment', 'Payment Updated', $message, $payload, $seed));
    }

    public function announcementPublished(ContentItem $announcement): void
    {
        $audience = $announcement->audience ?: ['type' => 'all'];
        $recipients = User::query()->where('is_active', true)->where('is_banned', false);

        $this->applyAudience($recipients, $audience);

        $recipients->each(fn (User $user) => $this->notify(
            $user,
            'announcement',
            $announcement->title,
            (string) ($announcement->body ?? ''),
            ['announcement_id' => $announcement->id, 'category' => $announcement->category],
            'announcement-'.$announcement->id,
        ));
    }

    /** @param array<string, mixed> $audience */
    private function applyAudience(Builder $query, array $audience): void
    {
        match ($audience['type'] ?? 'all') {
            'users' => $query->whereIn('id', $audience['user_ids'] ?? []),
            'roles' => $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $audience['roles'] ?? [])),
            'groups' => $query->whereHas('customerGroups', fn (Builder $groups) => $groups->whereIn('customer_groups.id', $audience['group_ids'] ?? [])),
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function notify(User $user, string $category, string $title, string $message, array $payload, string $seed): void
    {
        $user->notify(new RealtimeNotification(
            $this->notificationId($seed, (string) $user->id),
            $category,
            $title,
            $message,
            $payload,
        ));
    }

    private function notificationId(string $seed, string $recipientId): string
    {
        $hash = substr(hash('sha256', $seed.'|'.$recipientId), 0, 32);

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }
}
