<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetOtpMail extends Mailable
{
    public function __construct(
        public readonly string $otp,
        public readonly int $expiresMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Password reset code for '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.password-reset-otp',
            with: ['otp' => $this->otp, 'expiresMinutes' => $this->expiresMinutes],
        );
    }
}
