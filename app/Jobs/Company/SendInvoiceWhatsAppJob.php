<?php

namespace App\Jobs\Company;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendInvoiceWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invoiceId)
    {
    }

    public function handle(): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if (! $invoice) {
            return;
        }

        Log::info('Supplier invoice WhatsApp dispatch simulated.', [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
        ]);
    }
}
