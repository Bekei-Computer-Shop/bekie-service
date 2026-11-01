<x-mail::message>
# Email delivery test

Hello,

This is a test message from **{{ config('app.name') }}**.

Your application successfully submitted this message to its configured email service.

<x-mail::panel>
**Delivery check passed**

The configured mail transport accepted the message for delivery.
</x-mail::panel>

If you weren't expecting this test, no action is needed.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
