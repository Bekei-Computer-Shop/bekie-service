<x-mail::message>
# Email delivery test

Hello,

This is a test message from **{{ config('app.name') }}**.

Your application successfully connected to its configured email service and delivered this message.

<x-mail::panel>
**Delivery check passed**

The message was accepted by your configured mail transport.
</x-mail::panel>

If you weren't expecting this test, no action is needed.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
