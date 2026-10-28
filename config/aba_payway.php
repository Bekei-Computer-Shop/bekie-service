<?php

$baseUrl = rtrim((string) (env('ABA_PAYWAY_BASE_URL') ?: 'https://checkout-sandbox.payway.com.kh'), '/');

return [
    'merchant_id' => env('ABA_PAYWAY_MERCHANT_ID'),
    'api_key' => env('ABA_PAYWAY_API_KEY'),
    'public_key' => env('ABA_PAYWAY_PUBLIC_KEY'),
    'private_key' => env('ABA_PAYWAY_PRIVATE_KEY'),
    'base_url' => $baseUrl,
    'purchase_url' => env('ABA_PAYWAY_PURCHASE_URL', $baseUrl.'/api/payment-gateway/v1/payments/purchase'),
    'check_url' => env('ABA_PAYWAY_CHECK_URL', $baseUrl.'/api/payment-gateway/v1/payments/check-transaction-2'),
    'return_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/payments/aba/callback',
];
