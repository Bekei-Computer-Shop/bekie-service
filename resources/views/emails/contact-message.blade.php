<x-mail::message>
# Contact request

**From:** {{ $senderName }} ({{ $senderEmail }})

**Subject:** {{ $contactSubject }}

{{ $contactMessage }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
