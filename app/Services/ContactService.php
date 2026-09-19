<?php

namespace App\Services;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactService
{
    public function send(string $name, string $email, string $subject, string $message): void
    {
        try {
            Mail::to(config('otp.support_email'))->queue(new ContactMessageMail($name, $email, $subject, $message));
        } catch (\Throwable $exception) {
            Log::error('Contact email could not be queued.', ['exception' => $exception::class]);

            throw $exception;
        }
    }
}
