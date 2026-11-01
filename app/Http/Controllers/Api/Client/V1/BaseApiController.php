<?php

namespace App\Http\Controllers\Api\Client\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function success(mixed $data = null, string $message = ''): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function created(mixed $data = null, string $message = ''): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $errors  per-field messages, keyed by field name
     * @param  string|null  $code  stable machine-readable code; defaults from the status
     */
    protected function error(string $message = '', int $status = 400, array $errors = [], ?string $code = null): JsonResponse
    {
        return ApiError::response($message, $status, $errors, $code);
    }
}
