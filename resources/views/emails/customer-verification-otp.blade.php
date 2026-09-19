<x-mail::message>
# Verify your email

Your verification code is:

## {{ $otp }}

This code expires in {{ $expiresMinutes }} minutes and can be used only once.
If you did not request this code, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
