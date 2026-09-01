<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Centralised renderer for `api/*` responses.
 *
 * Every JSON error response is forced into the project's standard envelope:
 *   { status: 'error', message: string, errors?: object, data: null }
 *
 * Stack traces, SQL fragments, file paths, and other internal details never
 * reach the wire in production. Technical details are written to the
 * application log instead so operators can still diagnose failures.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        // Only handle requests that expect a JSON response.
        if (! $request->expectsJson()) {
            return null;
        }

        // 422 — FormRequest / manual validation failures.
        if ($e instanceof ValidationException) {
            return $this->respond(
                'The given data was invalid.',
                422,
                $e->errors(),
            );
        }

        // 401 — unauthenticated (no token / invalid token / banned user).
        if ($e instanceof AuthenticationException) {
            return $this->respond('Unauthenticated.', 401);
        }

        // 403 — authenticated but not allowed.
        if ($e instanceof AuthorizationException
            || $e instanceof AccessDeniedHttpException) {
            return $this->respond('Forbidden.', 403);
        }

        // 429 — rate limit / throttle.
        if ($e instanceof ThrottleRequestsException) {
            $message = 'Too many attempts. Please slow down.';
            if ($retryAfter = $e->getHeaders()['Retry-After'] ?? null) {
                return $this->respond(
                    "Too many attempts. Please slow down. Try again in {$retryAfter} seconds.",
                    429,
                    [],
                    ['Retry-After' => $retryAfter]
                );
            }

            return $this->respond($message, 429);
        }

        // 404 — route-binding miss or unknown route.
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->respond('Resource not found.', 404);
        }

        // 405 — wrong HTTP verb.
        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->respond('Method not allowed for this endpoint.', 405);
        }

        // Other Symfony HttpExceptions (e.g. custom 4xx/5xx).
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $status >= 500 && ! config('app.debug')
                ? 'Server error.'
                : $e->getMessage();

            return $this->respond($message ?: 'Server error.', $status);
        }

        // Anything else is a 500.
        report($e);

        $message = config('app.debug') && ! app()->environment('production')
            ? $e->getMessage()
            : 'Server error.';

        return $this->respond($message, 500);
    }

    /**
     * Shape the response into the project's standard envelope so callers see
     * `{status, message, errors?, data}` consistently regardless of which
     * exception type fired.
     */
    private function respond(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }
}
