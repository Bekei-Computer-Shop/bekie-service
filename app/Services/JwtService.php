<?php

namespace App\Services;

class JwtService
{
    protected string $secret;

    public function __construct()
    {
        $this->secret = config('app.jwt_secret') ?: env('JWT_SECRET', env('APP_KEY'));
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function encode(array $payload, int $ttlSeconds = 3600, string $alg = 'HS256'): string
    {
        $header = ['typ' => 'JWT', 'alg' => $alg];

        $now = time();
        $payload = array_merge([
            'iss' => config('app.url') ?: config('app.name'),
            'aud' => config('app.jwt_audience') ?: config('app.url') ?: config('app.name'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
        ], $payload);

        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header));
        $segments[] = $this->base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature = $this->sign($signingInput, $alg);

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    protected function sign(string $input, string $alg): string
    {
        switch ($alg) {
            case 'HS256':
            default:
                return hash_hmac('sha256', $input, $this->secret, true);
        }
    }

    public function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headb64, $bodyb64, $cryptob64] = $parts;

        $header = json_decode($this->base64UrlDecode($headb64), true);
        $payload = json_decode($this->base64UrlDecode($bodyb64), true);
        $signature = $this->base64UrlDecode($cryptob64);

        if (! is_array($header) || ! is_array($payload) || $signature === false) {
            return null;
        }

        $alg = $header['alg'] ?? 'HS256';
        $signingInput = $headb64.'.'.$bodyb64;
        $expected = $this->sign($signingInput, $alg);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        if (isset($payload['nbf']) && time() < $payload['nbf']) {
            return null;
        }

        if (isset($payload['exp']) && time() >= $payload['exp']) {
            return null;
        }

        return $payload;
    }
}
