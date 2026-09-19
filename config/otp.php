<?php

return [
    'length' => 6,
    'expires_minutes' => (int) env('OTP_EXPIRES_MINUTES', 10),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'reset_token_minutes' => (int) env('OTP_RESET_TOKEN_MINUTES', 10),
    'support_email' => env('CONTACT_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
];
