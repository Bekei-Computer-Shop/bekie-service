<?php

namespace App\Http\Controllers\Api\Admin\V1;

use App\Mail\MailtrapTestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailTestController extends BaseAdminController
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Mail::to($validated['email'])->send(new MailtrapTestMail);

        return $this->success(
            ['recipient' => $validated['email']],
            'Test email sent successfully.',
        );
    }
}
