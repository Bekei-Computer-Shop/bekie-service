<?php

namespace App\Services;

use RuntimeException;

class GoogleAuthException extends RuntimeException
{
    private function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    /** The token is not a valid, current Google ID token for this app. */
    public static function invalidToken(): self
    {
        return new self('Invalid Google sign-in token.', 401);
    }

    /** We cannot check tokens right now (not configured, or Google unreachable). */
    public static function unavailable(): self
    {
        return new self('Google sign-in is temporarily unavailable.', 503);
    }
}
