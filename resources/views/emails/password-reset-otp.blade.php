
<x-mail::message>
# Password Reset Request

Hello,

We received a request to reset the password associated with your account.

Use the verification code below to reset your password.

<x-mail::panel>
# {{ $otp }}
</x-mail::panel>

This code will expire in **{{ $expiresMinutes }} minutes**.

For your security, please do not share this code with anyone.

If you did not request a password reset, you can safely ignore this email. Your account remains secure, and no changes will be made to your password.

Best regards,
**{{ config('app.name') }} Support Team**

<x-slot:subcopy>
If you are having trouble with the password reset process, please contact our support team.
</x-slot:subcopy>
</x-mail::message>
