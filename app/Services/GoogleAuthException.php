<?php

namespace App\Services;

use RuntimeException;

class GoogleAuthException extends RuntimeException
{
    /**
     * What the website developer can act on, per rejection reason. The message
     * stays generic; none of these say anything an attacker could use.
     */
    private const HINTS = [
        'not_a_jwt' => 'Not a Google ID token. Send the "credential" from Google Sign-In (a long eyJ... string with two dots), not an access token or the client ID.',
        'unsupported_algorithm' => 'Not a Google ID token: it is not signed with RS256.',
        'unknown_signing_key' => 'The token was not signed by a current Google key.',
        'bad_signature' => 'The token signature does not verify, so it was not issued by Google or was altered.',
        'wrong_issuer' => 'The token was not issued by Google.',
        'wrong_audience' => 'The token was issued for a different Google client ID than the one this server accepts.',
        'expired' => 'The token has expired. Get a fresh one from Google and send it straight away.',
        'missing_subject' => 'The token has no Google account id.',
        'missing_email' => 'The token has no email. The "email" scope must be granted.',
        'email_not_verified' => 'The Google account email is not verified.',
    ];

    private function __construct(string $message, public readonly int $status, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }

    /** The token is not a valid, current Google ID token for this app. */
    public static function invalidToken(string $reason = 'invalid'): self
    {
        return new self('Invalid Google sign-in token.', 401, $reason);
    }

    /** We cannot check tokens right now (not configured, or Google unreachable). */
    public static function unavailable(): self
    {
        return new self('Google sign-in is temporarily unavailable.', 503);
    }

    /**
     * Validation-shaped `errors` for the response envelope.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return isset(self::HINTS[$this->reason]) ? ['id_token' => [self::HINTS[$this->reason]]] : [];
    }
}
