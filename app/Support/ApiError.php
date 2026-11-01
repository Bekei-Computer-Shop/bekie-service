<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The one shape every API error takes, wherever it is raised:
 *
 *   {
 *     "status":  "error",
 *     "code":    "validation_failed",   // stable, machine-readable; branch on this
 *     "message": "Please enter your email address.",  // safe to show the user as-is
 *     "data":    null,
 *     "errors":  { "email": ["Please enter your email address."] }  // per field, may be empty
 *   }
 *
 * `message` is written for the person using the app: what went wrong and what
 * to do next. It never carries stack traces, SQL, vendor names or config.
 */
final class ApiError
{
    /** Default `code` for each status, used when the caller does not name one. */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        410 => 'gone',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        419 => 'session_expired',
        422 => 'validation_failed',
        423 => 'locked',
        429 => 'too_many_requests',
        500 => 'server_error',
        502 => 'service_unavailable',
        503 => 'service_unavailable',
        504 => 'service_unavailable',
    ];

    /** Default `message` for each status, used when the caller gives none. */
    private const MESSAGES = [
        400 => 'We couldn\'t process this request. Please check the details and try again.',
        401 => 'Please sign in to continue.',
        403 => 'You don\'t have permission to do this.',
        404 => 'We couldn\'t find what you were looking for.',
        405 => 'This action isn\'t supported here.',
        409 => 'This was changed by another request. Please refresh and try again.',
        410 => 'This is no longer available.',
        413 => 'The file you\'re uploading is too large. Please choose a smaller file and try again.',
        415 => 'This file type isn\'t supported.',
        419 => 'Your session has expired. Please sign in again.',
        422 => 'Please check the information you entered and try again.',
        423 => 'This is locked right now. Please try again shortly.',
        429 => 'You\'re going a little too fast. Please wait a moment and try again.',
        500 => 'Something went wrong on our side. Please try again in a moment.',
        502 => 'This service is temporarily unavailable. Please try again in a few minutes.',
        503 => 'This service is temporarily unavailable. Please try again in a few minutes.',
        504 => 'This is taking longer than expected. Please try again in a few minutes.',
    ];

    /**
     * @param  array<string, mixed>  $errors  per-field messages, keyed by field name
     * @param  array<string, string>  $headers
     */
    public static function response(
        string $message = '',
        int $status = 400,
        array $errors = [],
        ?string $code = null,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'code' => $code ?? self::code($status),
            'message' => $message !== '' ? $message : self::message($status),
            'data' => null,
            // Always an object, so clients can read `errors.field` without a null check.
            'errors' => (object) $errors,
        ], $status, $headers);
    }

    public static function code(int $status): string
    {
        return self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'bad_request');
    }

    public static function message(int $status): string
    {
        return self::MESSAGES[$status] ?? self::MESSAGES[$status >= 500 ? 500 : 400];
    }
}
