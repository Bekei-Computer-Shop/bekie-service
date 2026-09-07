<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KhqrTransaction;
use Illuminate\Support\Str;
use RuntimeException;

class KhqrService
{
    private const MERCHANT_ID = 'bekie';

    private const MERCHANT_NAME = 'Bekie';

    public function isConfigured(): bool
    {
        return (bool) config('services.khqr.enabled')
            && (bool) config('services.khqr.merchant_id')
            && (bool) config('services.khqr.merchant_name');
    }

    /**
     * Generate KHQR QR code for payment.
     * Returns QR code data URL and transaction record.
     */
    public function generateQrCode(array $data): array
    {
        $this->ensureConfigured();

        $merchantId = config('services.khqr.merchant_id') ?? self::MERCHANT_ID;
        $merchantName = config('services.khqr.merchant_name') ?? self::MERCHANT_NAME;

        $amount = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'USD';
        $orderId = $data['order_id'];
        $userEmail = $data['user_email'] ?? '';

        $reference = "ORD{$orderId}";

        $transactionId = Str::upper('KHQR'.now()->format('YmdHis').Str::random(8));

        $khqrData = $this->buildKhqrPayload(
            merchantId: $merchantId,
            merchantName: $merchantName,
            amount: $amount,
            currency: $currency,
            reference: $reference,
            userEmail: $userEmail
        );

        $khqrTransaction = KhqrTransaction::create([
            'user_id' => $data['user_id'],
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'khqr_data' => $khqrData,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $qrCodeUrl = $this->encodeQrCode($khqrData);

        return [
            'transaction_id' => $transactionId,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_data' => $khqrData,
            'merchant_id' => $merchantId,
            'merchant_name' => $merchantName,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'expires_at' => $khqrTransaction->expires_at->toIso8601String(),
        ];
    }

    /**
     * Build KHQR payload according to Cambodian KHQR standard.
     * Format: TLV (Tag-Length-Value) encoded.
     */
    private function buildKhqrPayload(
        string $merchantId,
        string $merchantName,
        float $amount,
        string $currency,
        string $reference,
        string $userEmail = ''
    ): string {
        $payload = [];

        $payload[] = '00'; // Payload Format Indicator
        $payload[] = '02'; // Version 02

        $payload[] = '01'; // Initiation Mode (Static = 11, Dynamic = 12)
        $payload[] = '12'; // Dynamic QR

        $payload[] = '29'; // Merchant Account Information
        $merchantAccountInfo = $this->tlvEncode('00', $merchantId);
        $merchantAccountInfo .= $this->tlvEncode('01', $merchantName);
        $payload[] = str_pad(dechex(strlen($merchantAccountInfo) / 2), 2, '0', STR_PAD_LEFT);
        $payload[] = $merchantAccountInfo;

        $payload[] = '54'; // Currency Code
        $currencyCode = $this->getCurrencyCode($currency);
        $payload[] = '03';
        $payload[] = $currencyCode;

        $payload[] = '58'; // Transaction Amount
        $amountStr = number_format($amount, 2, '.', '');
        $amountHex = bin2hex($amountStr);
        $payload[] = str_pad(dechex(strlen($amountStr)), 2, '0', STR_PAD_LEFT);
        $payload[] = $amountHex;

        $payload[] = '62'; // Additional Data
        $additionalData = $this->tlvEncode('01', $reference);
        if ($userEmail) {
            $additionalData .= $this->tlvEncode('02', $userEmail);
        }
        $payload[] = str_pad(dechex(strlen($additionalData) / 2), 2, '0', STR_PAD_LEFT);
        $payload[] = $additionalData;

        return implode('', $payload);
    }

    /**
     * Encode TLV (Tag-Length-Value) format.
     */
    private function tlvEncode(string $tag, string $value): string
    {
        $length = strlen($value);

        return $tag.str_pad(dechex($length), 2, '0', STR_PAD_LEFT).bin2hex($value);
    }

    /**
     * Get ISO 4217 currency code.
     */
    private function getCurrencyCode(string $currency): string
    {
        return match ($currency) {
            'USD' => '840',
            'KHR' => '116',
            default => '840', // Default to USD
        };
    }

    /**
     * Encode KHQR data as QR code image (data URI).
     * Uses qrencode if available, otherwise returns raw data.
     */
    private function encodeQrCode(string $khqrData): string
    {
        if (extension_loaded('imagick') && command_exists('qrencode')) {
            try {
                $tmpFile = tempnam(sys_get_temp_dir(), 'khqr_');
                exec("qrencode -o {$tmpFile} '{$khqrData}'");
                if (file_exists($tmpFile)) {
                    $imageData = base64_encode(file_get_contents($tmpFile));
                    unlink($tmpFile);

                    return 'data:image/png;base64,'.$imageData;
                }
            } catch (\Throwable $e) {
                // Fall back to data URL
            }
        }

        return 'data:text/plain;base64,'.base64_encode($khqrData);
    }

    /**
     * Check payment status for a KHQR transaction.
     */
    public function checkStatus(string $transactionId): array
    {
        $transaction = KhqrTransaction::where('transaction_id', $transactionId)->firstOrFail();

        if ($transaction->status === 'paid' && $transaction->paid_at) {
            return [
                'status' => 'paid',
                'paid_at' => $transaction->paid_at->toIso8601String(),
                'payment_reference' => $transaction->payment_reference ?? null,
            ];
        }

        if ($transaction->expires_at->isPast()) {
            $transaction->update(['status' => 'expired']);

            return [
                'status' => 'expired',
                'expired_at' => $transaction->expires_at->toIso8601String(),
            ];
        }

        return [
            'status' => 'pending',
            'expires_at' => $transaction->expires_at->toIso8601String(),
        ];
    }

    /**
     * Confirm payment and update transaction status.
     * Called when payment is verified (webhook or manual).
     */
    public function confirmPayment(string $transactionId, string $paymentReference = ''): void
    {
        $transaction = KhqrTransaction::where('transaction_id', $transactionId)->firstOrFail();

        $transaction->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
        ]);
    }

    /**
     * Cancel a KHQR transaction.
     */
    public function cancel(string $transactionId): void
    {
        $transaction = KhqrTransaction::where('transaction_id', $transactionId)->firstOrFail();

        $transaction->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Get payment options available for KHQR.
     */
    public function paymentOptions(): array
    {
        return [
            'khqr' => [
                'enabled' => $this->isConfigured(),
                'name' => 'ABA KHQR',
                'description' => 'Scan QR code to pay via ABA bank',
                'supported_currencies' => ['USD', 'KHR'],
            ],
        ];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('KHQR is not configured.');
        }
    }
}

function command_exists($command): bool
{
    $whereIsCommand = (PHP_OS_FAMILY === 'Windows') ? 'where' : 'which';

    $process = proc_open(
        "{$whereIsCommand} {$command}",
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if ($process === false) {
        return false;
    }

    $output = stream_get_contents($pipes[1]);
    $exitCode = proc_close($process);

    return $exitCode === 0 && ! empty($output);
}
