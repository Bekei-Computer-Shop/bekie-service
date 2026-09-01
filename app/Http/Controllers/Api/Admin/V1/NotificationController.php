<?php

namespace App\Http\Controllers\Api\Admin\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseAdminController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return $this->success([
            'items' => $notifications->getCollection()->map(fn ($notification) => $this->present($notification))->values(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'count' => $notifications->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return $this->success(message: 'Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(message: 'Notifications marked as read.');
    }

    private function present(object $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'category' => $data['category'] ?? 'system',
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? '',
            'data' => $data['data'] ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
