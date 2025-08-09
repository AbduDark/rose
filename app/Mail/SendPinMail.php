<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPinMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $pin // ✅ Laravel 11 يدعم property promotion مباشرة
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز التحقق - Verification PIN',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pin',
            with: ['pin' => $this->pin],
        );
    }
}
