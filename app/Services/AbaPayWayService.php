<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AbaPayWayService
{
    public function isConfigured(): bool
    {
        return (bool) config('aba_payway.merchant_id')
            && (bool) config('aba_payway.api_key')
            && (bool) config('aba_payway.purchase_url');
    }

    public function purchase(array $data): array
    {
        $this->ensureConfigured();

        $fields = [
            'req_time' => now('UTC')->format('YmdHis'),
            'merchant_id' => config('aba_payway.merchant_id'),
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
            'return_url' => base64_encode((string) config('aba_payway.return_url')),
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
            'view_type' => in_array(($data['payment_option'] ?? ''), ['abapay_khqr', 'abapay_khqr_deeplink'], true)
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
            ->post(config('aba_payway.purchase_url'), $fields);
        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $gatewayResponse = $response->json();
        $expectsQrPayload = $fields['payment_option'] === 'abapay_khqr_deeplink';

        if ($response->failed()) {
            $this->failPurchase(
                $response,
                '',
                (string) $fields['tran_id'],
                'ABA PayWay rejected the checkout request.',
                is_array($gatewayResponse) ? array_keys($gatewayResponse) : [],
                'http_error',
            );
        }

        if (! $expectsQrPayload) {
            if ($contentType === 'application/json' && ! is_array($gatewayResponse)) {
                $this->failPurchase(
                    $response,
                    '',
                    (string) $fields['tran_id'],
                    'ABA PayWay returned an unexpected checkout response.',
                    [],
                    'unexpected_response_type',
                );
            }

            $gatewayStatusCode = (string) data_get($gatewayResponse, 'status.code', '');
            if ($gatewayStatusCode !== '' && ! in_array($gatewayStatusCode, ['00', '0'], true)) {
                $this->failPurchase(
                    $response,
                    $gatewayStatusCode,
                    (string) $fields['tran_id'],
                    'ABA PayWay rejected the checkout request.',
                    is_array($gatewayResponse) ? array_keys($gatewayResponse) : [],
                    'provider_rejected',
                );
            }

            return [
                'transaction_id' => $fields['tran_id'],
                'view_type' => $fields['view_type'],
                'gateway' => is_array($gatewayResponse) ? $gatewayResponse : ['html' => $response->body()],
            ];
        }

        if ($contentType !== 'application/json' || ! is_array($gatewayResponse)) {
            $this->failPurchase(
                $response,
                '',
                (string) $fields['tran_id'],
                'ABA PayWay returned an unexpected KHQR response.',
                [],
                'unexpected_response_type',
            );
        }

        $gatewayStatusCode = (string) data_get($gatewayResponse, 'status.code', '');
        if (! in_array($gatewayStatusCode, ['00', '0'], true)) {
            $this->failPurchase(
                $response,
                $gatewayStatusCode,
                (string) $fields['tran_id'],
                'ABA PayWay rejected the checkout request.',
                array_keys($gatewayResponse),
                'provider_rejected',
            );
        }

        $responseTransactionId = data_get($gatewayResponse, 'status.tran_id');
        if ($responseTransactionId !== (string) $fields['tran_id']) {
            $this->failPurchase(
                $response,
                $gatewayStatusCode,
                (string) $fields['tran_id'],
                'ABA PayWay returned a mismatched transaction identifier.',
                array_keys($gatewayResponse),
                'transaction_id_mismatch',
            );
        }

        $qrString = $gatewayResponse['qr_string'] ?? $gatewayResponse['qrString'] ?? null;
        if (! is_string($qrString) || trim($qrString) === '') {
            $this->failPurchase(
                $response,
                $gatewayStatusCode,
                (string) $fields['tran_id'],
                'ABA PayWay did not return a usable KHQR payload.',
                array_keys($gatewayResponse),
                'qr_payload_missing',
            );
        }

        $checkoutQrUrl = $gatewayResponse['checkout_qr_url'] ?? null;
        $qrUrlScheme = is_string($checkoutQrUrl) ? parse_url($checkoutQrUrl, PHP_URL_SCHEME) : null;
        $checkoutQrUrl = is_string($checkoutQrUrl)
            && filter_var($checkoutQrUrl, FILTER_VALIDATE_URL) !== false
            && strtolower((string) $qrUrlScheme) === 'https'
                ? $checkoutQrUrl
                : null;
        $abaPayDeeplink = $gatewayResponse['abapay_deeplink'] ?? null;
        $deeplinkScheme = is_string($abaPayDeeplink) ? parse_url($abaPayDeeplink, PHP_URL_SCHEME) : null;
        $abaPayDeeplink = is_string($abaPayDeeplink)
            && strtolower((string) $deeplinkScheme) === 'abamobilebank'
                ? $abaPayDeeplink
                : null;
        $safeGatewayResponse = [
            'status' => ['code' => $gatewayStatusCode],
            'qr_string' => $qrString,
        ];
        if ($checkoutQrUrl !== null) {
            $safeGatewayResponse['checkout_qr_url'] = $checkoutQrUrl;
        }
        if ($abaPayDeeplink !== null) {
            $safeGatewayResponse['abapay_deeplink'] = $abaPayDeeplink;
        }

        return [
            'transaction_id' => $fields['tran_id'],
            'view_type' => $fields['view_type'],
            'payment_payload' => ['type' => 'qr_string', 'value' => $qrString],
            'qr_string' => $qrString,
            'abapay_deeplink' => $abaPayDeeplink,
            'checkout_url' => $checkoutQrUrl,
            'gateway' => $safeGatewayResponse,
        ];
    }

    private function failPurchase(
        Response $response,
        string $abaStatusCode,
        string $transactionId,
        string $message,
        array $responseKeys = [],
        string $reasonCode = 'provider_rejected',
    ): never {
        $message = $this->redactSecrets($message);
        $httpStatus = $response->status();
        $gatewayResponse = $response->json();
        $responseFieldTypes = [];
        foreach (['status.code', 'status.tran_id', 'qr_string', 'qrString', 'qrImage', 'abapay_deeplink', 'checkout_qr_url'] as $path) {
            $value = is_array($gatewayResponse) ? data_get($gatewayResponse, $path) : null;
            $responseFieldTypes[$path] = $value === null ? 'missing' : get_debug_type($value);
        }

        Log::error('ABA PayWay purchase rejected', [
            'tran_id' => $transactionId,
            'http_status' => $httpStatus,
            'status_code' => $abaStatusCode !== '' ? $abaStatusCode : null,
            'reason_code' => $reasonCode,
            'content_type' => $response->header('Content-Type'),
            'response_field_types' => $responseFieldTypes,
            'response_keys' => array_values(array_filter(
                $responseKeys,
                fn (string|int $key): bool => ! preg_match('/key|secret|hash|signature|token/i', (string) $key),
            )),
        ]);

        throw new AbaPayWayException($message, [
            'aba' => [
                'status_code' => $abaStatusCode !== '' ? $abaStatusCode : null,
                'http_status' => $httpStatus,
                'reason_code' => $reasonCode,
                'message' => $message,
            ],
        ]);
    }

    private function redactSecrets(string $value): string
    {
        return str_replace(
            array_filter([
                (string) config('aba_payway.merchant_id'),
                (string) config('aba_payway.api_key'),
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
                ['key' => 'aba_payway', 'enabled' => $this->isConfigured(), 'options' => ['abapay_khqr_deeplink', 'abapay_khqr', 'cards']],
                ['key' => 'cod', 'enabled' => true],
            ],
        ];
    }

    public function check(string $transactionId, bool $allowNotFound = false): array
    {
        $this->ensureConfigured();
        $reqTime = now('UTC')->format('YmdHis');
        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => config('aba_payway.merchant_id'),
            'tran_id' => $transactionId,
        ];
        $payload['hash'] = $this->hash($reqTime.$payload['merchant_id'].$transactionId);

        $response = Http::retry(2, 200, fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
            ->acceptJson()
            ->timeout(20)
            ->post(config('aba_payway.check_url'), $payload);

        $this->throwIfGatewayFailed($response, $allowNotFound);

        return $response->json() ?? [];
    }

    public function transactionDetails(array $response): array
    {
        $details = data_get($response, 'data.data');
        if (! is_array($details)) {
            $details = data_get($response, 'data');
        }
        if (! is_array($details)) {
            $details = $response;
        }

        return [
            'tran_id' => $details['tran_id'] ?? $details['transaction_id'] ?? null,
            'payment_status_code' => $details['payment_status_code'] ?? $details['status_code'] ?? null,
            'payment_status' => $details['payment_status'] ?? null,
            'payment_amount' => $details['payment_amount'] ?? $details['amount'] ?? null,
            'payment_currency' => $details['payment_currency'] ?? $details['currency'] ?? null,
        ];
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
        $merchantId = config('aba_payway.merchant_id');
        $response = Http::retry(2, 200, fn (\Throwable $exception): bool => $exception instanceof ConnectionException)
            ->acceptJson()
            ->timeout(20)
            ->post(config('aba_payway.check_url'), [
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
        if (! $signature || ! config('aba_payway.api_key')) {
            return false;
        }

        ksort($payload);
        $input = '';
        foreach ($payload as $value) {
            $input .= is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
        }

        $computedHash = $this->hash($input);
        $isValid = hash_equals($computedHash, $signature);

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
        return base64_encode(hash_hmac('sha512', $input, config('aba_payway.api_key'), true));
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ABA PayWay is not configured.');
        }
    }

    private function throwIfGatewayFailed(Response $response, bool $allowNotFound = false): void
    {
        if ($response->failed()) {
            throw new AbaPayWayException('ABA PayWay transaction check failed.', [
                'aba' => [
                    'reason_code' => 'check_http_error',
                    'http_status' => $response->status(),
                ],
            ]);
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $gatewayResponse = $response->json();
        if ($contentType !== 'application/json' || ! is_array($gatewayResponse)) {
            throw new AbaPayWayException('ABA PayWay returned an unexpected transaction-check response.', [
                'aba' => ['reason_code' => 'check_unexpected_response'],
            ]);
        }

        $statusCode = (string) data_get($gatewayResponse, 'status.code', '');
        if ($statusCode === '') {
            throw new AbaPayWayException('ABA PayWay transaction-check status is missing.', [
                'aba' => ['reason_code' => 'check_status_missing'],
            ]);
        }

        if (! in_array($statusCode, ['00', '0'], true)
            && ! ($allowNotFound && $statusCode === '6')) {
            throw new AbaPayWayException('ABA PayWay rejected the transaction status request.', [
                'aba' => [
                    'reason_code' => 'check_provider_rejected',
                    'status_code' => $statusCode,
                ],
            ]);
        }
    }
}
