<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Centralised renderer for `api/*` responses.
 *
 * Every JSON error response is forced into the project's standard envelope
 * (see App\Support\ApiError): { status, code, message, data, errors }.
 *
 * `message` is written for the end user. Stack traces, SQL fragments, file
 * paths, model class names and other internal details never reach the wire in
 * production; they are written to the application log instead, under a
 * reference the user can quote to support.
 */
class ApiExceptionRenderer
{
    /** Framework default messages that say nothing useful to a user. */
    private const FRAMEWORK_MESSAGES = [
        'This action is unauthorized.',
        'Unauthenticated.',
        'Too Many Attempts.',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        // Only handle requests that expect a JSON response.
        if (! $request->expectsJson()) {
            return null;
        }

        // 422 — FormRequest / manual validation failures.
        if ($e instanceof ValidationException) {
            return $this->validationFailed($e);
        }

        // 401 — unauthenticated (no token / invalid token / banned user).
        if ($e instanceof AuthenticationException) {
            return ApiError::response(status: 401);
        }

        // 403 — authenticated but not allowed. A policy may explain why.
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return ApiError::response($this->ownMessage($e), 403);
        }

        // 429 — route throttles and any abort(429) / TooManyRequestsHttpException.
        // A message a developer wrote ("Too many incorrect attempts...") wins.
        if ($e instanceof ThrottleRequestsException
            || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 429)) {
            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            $message = $this->ownMessage($e) ?: ($retryAfter
                ? "You're going a little too fast. Please wait {$this->seconds((int) $retryAfter)} and try again."
                : '');

            return ApiError::response(
                $message,
                429,
                headers: $retryAfter ? ['Retry-After' => (string) $retryAfter] : [],
            );
        }

        // 404 — route-binding miss, unknown route, or abort(404, '...').
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->notFound($e);
        }

        // 413 — the body exceeded PHP's post_max_size, so PHP dropped it whole
        // (usually a photo or file that is far too big).
        if ($e instanceof PostTooLargeException) {
            return ApiError::response(status: 413);
        }

        // 405 — wrong HTTP verb.
        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiError::response(status: 405);
        }

        // Other Symfony HttpExceptions: abort(409, '...') and friends keep their
        // message; server-side ones fall back to the friendly default.
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            if ($status >= 500) {
                return $this->serverError($e, $request, $status);
            }

            return ApiError::response($this->ownMessage($e), $status, headers: $e->getHeaders());
        }

        return $this->serverError($e, $request, 500);
    }

    /**
     * Lead with the actual problem rather than "The given data was invalid.":
     * one field wrong reads as that field's message, several as a count.
     */
    private function validationFailed(ValidationException $e): JsonResponse
    {
        $errors = $e->errors();
        $first = collect($errors)->flatten()->first();

        $message = match (true) {
            count($errors) === 1 && is_string($first) => $first,
            count($errors) > 1 => 'Please check the '.count($errors).' highlighted fields and try again.',
            default => '',
        };

        return ApiError::response($message, 422, $errors);
    }

    /**
     * Name what is missing ("We couldn't find that product.") without exposing
     * the model class, and keep messages given to abort(404, '...').
     */
    private function notFound(Throwable $e): JsonResponse
    {
        $missing = $e instanceof ModelNotFoundException ? $e : $e->getPrevious();

        if ($missing instanceof ModelNotFoundException && $missing->getModel()) {
            $thing = Str::of(class_basename($missing->getModel()))->snake(' ')->lower();

            return ApiError::response("We couldn't find that {$thing}. It may have been removed or the link may be out of date.", 404);
        }

        // Unknown routes carry "The route ... could not be found."; that is for developers.
        $message = Str::startsWith($e->getMessage(), 'The route ') ? '' : $this->ownMessage($e);

        return ApiError::response($message, 404);
    }

    /**
     * Unexpected failures: a friendly message plus a reference that ties the
     * user's report to the log entry.
     */
    private function serverError(Throwable $e, Request $request, int $status): JsonResponse
    {
        $reference = strtoupper(Str::random(10));

        // The framework has already reported the exception itself; this line
        // links it to the reference the user sees.
        Log::error('API request failed.', [
            'reference' => $reference,
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        $message = config('app.debug') && ! app()->environment('production') && $e->getMessage() !== ''
            ? $e->getMessage()
            : ApiError::message($status).' If this keeps happening, contact support and quote reference '.$reference.'.';

        return ApiError::response($message, $status, ['reference' => [$reference]]);
    }

    /** The exception's own message when a developer wrote one, otherwise ''. */
    private function ownMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());

        return in_array($message, self::FRAMEWORK_MESSAGES, true) ? '' : $message;
    }

    private function seconds(int $seconds): string
    {
        if ($seconds >= 120) {
            $minutes = (int) ceil($seconds / 60);

            return "{$minutes} minutes";
        }

        return $seconds === 1 ? '1 second' : "{$seconds} seconds";
    }
}
