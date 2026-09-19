<x-mail::message>
# Admin password recovery

Your recovery code is:

## {{ $otp }}

This code expires in {{ $expiresMinutes }} minutes and can be used only once.
If you did not request a password reset, contact your system administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
