<x-mail::message>
# Password reset

Your password reset code is:

## {{ $otp }}

This code expires in {{ $expiresMinutes }} minutes. If you did not request a password reset, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
