<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $systemLink,
        public string $email,
        public string $plainPassword,
        public ?array $subscription = null,
        public ?string $dashboardLink = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Denta+ access details');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.system-access');
    }
}
