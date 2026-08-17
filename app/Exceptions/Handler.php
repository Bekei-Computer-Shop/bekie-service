<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

/**
 * Legacy exception handler. In Laravel 12 the API JSON envelope is
 * produced by `App\Exceptions\ApiExceptionRenderer`, registered through
 * `withExceptions()` in `bootstrap/app.php`. This class is kept so any
 * service-container binding that still resolves it does not blow up, but
 * the actual `render` logic now lives in the renderer above.
 */
class Handler extends ExceptionHandler
{
    /** @var array<int, class-string<\Throwable>> */
    protected array $dontReport = [];

    /** @var array<int, string> */
    protected array $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
