<x-mail::message>
# Admin password recovery

Your recovery code is:

<x-mail::panel>
## {{ $otp }}
</x-mail::panel>

This code expires in **{{ $expiresMinutes }} minutes** and can be used only once. Do not share it with anyone.

If you did not request admin account recovery, contact your system administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
