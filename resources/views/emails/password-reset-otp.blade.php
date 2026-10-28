
<x-mail::message>
# Password Reset Request

Hello,

Use this code to reset your password:

<x-mail::panel>
# {{ $otp }}
</x-mail::panel>

This code will expire in **{{ $expiresMinutes }} minutes**.

For your security, please do not share this code with anyone.

If you did not request a password reset, you can ignore this email. Your password will not change unless the code is used.

Best regards,
**{{ config('app.name') }} Support Team**

<x-slot:subcopy>
Need help? Contact {{ config('app.name') }} support.
</x-slot:subcopy>
</x-mail::message>
