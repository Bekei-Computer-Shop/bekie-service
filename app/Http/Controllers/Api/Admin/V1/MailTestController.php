<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Mail\MailtrapTestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailTestController extends BaseAdminController
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Mail::to($validated['email'])->send(new MailtrapTestMail);
        } catch (\Throwable $exception) {
            Log::error('Mail configuration test failed.', ['exception' => $exception::class]);

            return $this->error('Unable to send the test email right now.', 503);
        }

        return $this->success(
            ['recipient' => $validated['email']],
            'Test email sent successfully.',
        );
    }
}
