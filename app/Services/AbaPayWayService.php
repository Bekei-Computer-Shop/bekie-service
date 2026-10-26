<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AbaPayWayService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.payway.merchant_id')
            && (bool) config('services.payway.api_key')
            && (bool) config('services.payway.purchase_url');
    }

    public function purchase(array $data): array
    {
        $this->ensureConfigured();

        $fields = [
            'req_time' => now('UTC')->format('YmdHis'),
            'merchant_id' => config('services.payway.merchant_id'),
            'tran_id' => $data['tran_id'] ?? Str::upper(Str::random(20)),
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'items' => base64_encode(json_encode($data['items'] ?? [], JSON_THROW_ON_ERROR)),
            'shipping' => number_format((float) ($data['shipping'] ?? 0), 2, '.', ''),
            'firstname' => $data['firstname'] ?? '',
            'lastname' => $data['lastname'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'type' => 'purchase',
            'payment_option' => $data['payment_option'] ?? '',
            'return_url' => base64_encode((string) config('services.payway.return_url')),
            'cancel_url' => $data['cancel_url'] ?? '',
            'continue_success_url' => $data['continue_success_url'] ?? '',
            'return_deeplink' => $data['return_deeplink'] ?? '',
            'currency' => $data['currency'] ?? 'USD',
            'custom_fields' => base64_encode(json_encode($data['custom_fields'] ?? [], JSON_THROW_ON_ERROR)),
            'return_params' => $data['return_params'] ?? '',
            'payout' => $data['payout'] ?? '',
            'lifetime' => $data['lifetime'] ?? '',
            'additional_params' => $data['additional_params'] ?? '',
            'google_pay_token' => $data['google_pay_token'] ?? '',
            'skip_success_page' => $data['skip_success_page'] ?? 0,
            'view_type' => ($data['payment_option'] ?? '') === 'abapay_khqr'
                ? 'popup'
                : ($data['view_type'] ?? 'popup'),
        ];
        // PayWay's documented HMAC input has a fixed field order. `view_type`
        // controls the response presentation and is intentionally not signed.
        $hashFields = [
            'req_time', 'merchant_id', 'tran_id', 'amount', 'items', 'shipping',
            'firstname', 'lastname', 'email', 'phone', 'type', 'payment_option',
            'return_url', 'cancel_url', 'continue_success_url', 'return_deeplink',
            'currency', 'custom_fields', 'return_params', 'payout', 'lifetime',
            'additional_params', 'google_pay_token', 'skip_success_page',
        ];
        $hashValues = array_map(
            fn (string $key): string => (string) $fields[$key],
            $hashFields,
        );
        $hashInput = implode('', $hashValues);
        $fields['hash'] = $this->hash($hashInput);

        $response = Http::asMultipart()
            ->acceptJson()
            ->timeout(20)
            ->post(config('services.payway.purchase_url'), $fields);
        $gatewayResponse = $response->json() ?? [];
        $gatewayStatusCode = (string) data_get($gatewayResponse, 'status.code', '');
        if ($response->failed() || ($gatewayStatusCode !== '' && ! in_array($gatewayStatusCode, ['00', '0'], true))) {
            $gatewayMessage = (string) data_get($gatewayResponse, 'status.message', '');
            $this->failPurchase(
                $response,
                $gatewayStatusCode,
                $gatewayMessage !== '' ? $gatewayMessage : 'ABA PayWay rejected the checkout request.',
            );
        }

        if ($fields['payment_option'] === 'abapay_khqr') {
            $qrCodeUrl = data_get($gatewayResponse, 'checkout_qr_url');
            $qrCodeScheme = is_string($qrCodeUrl) ? parse_url($qrCodeUrl, PHP_URL_SCHEME) : null;
            if (! is_string($qrCodeUrl) || filter_var($qrCodeUrl, FILTER_VALIDATE_URL) === false || $qrCodeScheme !== 'https') {
                $this->failPurchase(
                    $response,
                    $gatewayStatusCode,
                    'ABA PayWay did not return a valid HTTPS checkout_qr_url.',
                );
            }
        }

        return [
            'transaction_id' => $fields['tran_id'],
            'view_type' => $fields['view_type'],
            'gateway' => $gatewayResponse ?: ['html' => $response->body()],
        ];
    }

    private function failPurchase(Response $response, string $abaStatusCode, string $message): never
    {
        $message = $this->redactSecrets($message);
        $httpStatus = $response->status();

        Log::error('ABA PayWay purchase rejected', [
            'endpoint' => config('services.payway.purchase_url'),
            'http_status' => $httpStatus,
            'aba_status_code' => $abaStatusCode !== '' ? $abaStatusCode : null,
            'response_body' => $this->redactSecrets($response->body()),
        ]);

        throw new AbaPayWayException($message, [
            'aba' => [
                'status_code' => $abaStatusCode !== '' ? $abaStatusCode : null,
                'http_status' => $httpStatus,
                'message' => $message,
            ],
        ]);
    }

    private function redactSecrets(string $value): string
    {
        return str_replace(
            array_filter([
                (string) config('services.payway.merchant_id'),
                (string) config('services.payway.api_key'),
            ]),
            '[REDACTED]',
            $value,
        );
    }

    public function paymentOptions(string $platform = 'web'): array
    {
        $isMobile = $platform === 'mobile';

        return [
            'platform' => $platform,
            'checkout' => [
                'view_type' => $isMobile ? 'hosted_view' : 'popup',
                'requires_return_deeplink' => $isMobile,
            ],
            'currency' => ['USD', 'KHR'],
            'methods' => [
                ['key' => 'aba_payway', 'enabled' => $this->isConfigured(), 'options' => ['abapay_khqr', 'cards']],
                ['key' => 'cod', 'enabled' => true],
            ],
        ];
    }

    public function check(string $transactionId): array
    {
        $this->ensureConfigured();
        $reqTime = now('UTC')->format('YmdHis');
        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => config('services.payway.merchant_id'),
            'tran_id' => $transactionId,
        ];
        $payload['hash'] = $this->hash($reqTime.$payload['merchant_id'].$transactionId);

        $response = Http::acceptJson()
            ->timeout(20)
            ->post(config('services.payway.check_url'), $payload);

        $this->throwIfGatewayFailed($response);

        return $response->json() ?? [];
    }

    /**
     * Probe PayWay without creating a charge. A "transaction not found" reply
     * for the generated id proves the merchant credentials were accepted.
     */
    public function connectionCheck(): array
    {
        if (! $this->isConfigured()) {
            return [
                'connected' => false,
                'code' => 'CONFIGURATION_MISSING',
                'message' => 'ABA PayWay credentials are not configured on the server.',
            ];
        }

        $reqTime = now('UTC')->format('YmdHis');
        $transactionId = 'CONNECTION'.now('UTC')->format('YmdHis').Str::upper(Str::random(4));
        $merchantId = config('services.payway.merchant_id');
        $response = Http::acceptJson()->timeout(20)->post(config('services.payway.check_url'), [
            'req_time' => $reqTime,
            'merchant_id' => $merchantId,
            'tran_id' => $transactionId,
            'hash' => $this->hash($reqTime.$merchantId.$transactionId),
        ]);

        $payload = $response->json() ?? [];
        $gatewayStatus = $payload['status'] ?? $payload['data']['status'] ?? [];
        $code = (string) ($gatewayStatus['code'] ?? $response->status());
        $message = (string) ($gatewayStatus['message'] ?? ($response->successful() ? 'PayWay responded.' : 'PayWay request failed.'));

        return [
            'connected' => $response->successful() && in_array($code, ['00', '0', '6'], true),
            'code' => $code,
            'message' => $message,
        ];
    }

    public function verifyCallback(array $payload, ?string $signature): bool
    {
        if (! $signature || ! config('services.payway.api_key')) {
            Log::warning('PayWay callback verification failed: missing signature or API key', [
                'has_signature' => (bool) $signature,
                'has_api_key' => (bool) config('services.payway.api_key'),
            ]);

            return false;
        }

        ksort($payload);
        $input = '';
        foreach ($payload as $value) {
            $input .= is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
        }

        $computedHash = $this->hash($input);
        $isValid = hash_equals($computedHash, $signature);

        if (! $isValid) {
            Log::warning('PayWay callback signature verification failed', [
                'tran_id' => $payload['tran_id'] ?? 'unknown',
                'expected_hash' => substr($computedHash, 0, 20).'...',
                'received_hash' => substr($signature, 0, 20).'...',
            ]);
        }

        return $isValid;
    }

    public function publicConfig(): array
    {
        return [
            'connected' => $this->isConfigured(),
        ];
    }

    private function hash(string $input): string
    {
        return base64_encode(hash_hmac('sha512', $input, config('services.payway.api_key'), true));
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ABA PayWay is not configured.');
        }
    }

    private function throwIfGatewayFailed(Response $response): void
    {
        if ($response->failed()) {
            throw new RuntimeException('ABA PayWay request failed with HTTP '.$response->status().'.');
        }
    }
}
