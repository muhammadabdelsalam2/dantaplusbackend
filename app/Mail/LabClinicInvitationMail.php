<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LabClinicInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $labName,
        public string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->labName} invited you to join Denta+",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.lab-clinic-invitation',
            with: [
                'labName' => $this->labName,
                'inviteUrl' => $this->inviteUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
