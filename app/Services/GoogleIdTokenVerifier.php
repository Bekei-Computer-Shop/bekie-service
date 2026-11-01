<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Google Sign-In ID token locally: RS256 signature against Google's
 * published certificates, then issuer, audience (our client ID), expiry and a
 * verified email. Fails closed on anything unexpected.
 */
class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    private const CERTS_CACHE_KEY = 'google.oauth.certs';

    private const CERTS_CACHE_SECONDS = 3600;

    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @return array{sub: string, email: string, name: ?string, given_name: ?string, family_name: ?string, picture: ?string}
     *
     * @throws GoogleAuthException
     */
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw GoogleAuthException::unavailable();
        }

        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw GoogleAuthException::invalidToken();
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);

        // Only RS256: refusing "none" and HMAC blocks algorithm-confusion forgeries.
        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw GoogleAuthException::invalidToken();
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $certificate = $this->certificateFor($header['kid']);

        if ($signature === '' || openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw GoogleAuthException::invalidToken();
        }

        $claims = $this->decodeJson($encodedPayload);

        $valid = in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && ($claims['aud'] ?? null) === $clientId
            && is_numeric($claims['exp'] ?? null) && $claims['exp'] > time()
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== ''
            && is_string($claims['email'] ?? null) && $claims['email'] !== ''
            && ($claims['email_verified'] ?? false) === true;

        if (! $valid) {
            throw GoogleAuthException::invalidToken();
        }

        return [
            'sub' => $claims['sub'],
            'email' => $claims['email'],
            'name' => $this->optionalString($claims, 'name'),
            'given_name' => $this->optionalString($claims, 'given_name'),
            'family_name' => $this->optionalString($claims, 'family_name'),
            'picture' => $this->optionalString($claims, 'picture'),
        ];
    }

    /**
     * Google rotates its signing keys, so a key id we have not cached triggers
     * one re-download before the token is declared invalid.
     */
    private function certificateFor(string $keyId): string
    {
        $certificates = $this->certificates();

        if (! isset($certificates[$keyId])) {
            Cache::forget(self::CERTS_CACHE_KEY);
            $certificates = $this->certificates();
        }

        if (! isset($certificates[$keyId]) || ! is_string($certificates[$keyId])) {
            throw GoogleAuthException::invalidToken();
        }

        return $certificates[$keyId];
    }

    /**
     * @return array<string, mixed>
     */
    private function certificates(): array
    {
        return Cache::remember(self::CERTS_CACHE_KEY, self::CERTS_CACHE_SECONDS, function (): array {
            try {
                $certificates = Http::timeout(5)->get(self::CERTS_URL)->throw()->json();
            } catch (\Throwable $exception) {
                Log::warning('Google signing certificates could not be fetched.', ['exception' => $exception::class]);

                throw GoogleAuthException::unavailable();
            }

            if (! is_array($certificates) || $certificates === []) {
                throw GoogleAuthException::unavailable();
            }

            return $certificates;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $encoded): array
    {
        $decoded = json_decode($this->base64UrlDecode($encoded), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function optionalString(array $claims, string $key): ?string
    {
        $value = $claims[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
