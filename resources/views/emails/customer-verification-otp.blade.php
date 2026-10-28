<x-mail::message>
# Verify your email

Your verification code is:

<x-mail::panel>
## {{ $otp }}
</x-mail::panel>

This code expires in **{{ $expiresMinutes }} minutes** and can be used only once. Do not share it with anyone.

If you did not create an account, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
