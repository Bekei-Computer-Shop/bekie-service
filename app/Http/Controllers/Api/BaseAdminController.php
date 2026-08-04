<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseAdminController extends Controller
{
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['timestamp' => now()->toIso8601String()],
        ], $status);
    }

    protected function created(mixed $data): JsonResponse
    {
        return $this->success($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $message,
            'errors' => $errors,
            'meta' => ['timestamp' => now()->toIso8601String()],
        ]), $status);
    }
}
