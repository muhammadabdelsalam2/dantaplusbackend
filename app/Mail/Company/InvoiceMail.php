<?php

namespace App\Mail\Company;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invoice ' . $this->invoice->invoice_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice', with: ['invoice' => $this->invoice]);
    }
}
