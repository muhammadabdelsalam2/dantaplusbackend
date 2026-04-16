<?php

namespace App\Services\Company;

use App\Http\Resources\Company\InvoiceResource;
use App\Http\Resources\Company\PaymentResource;
use App\Jobs\Company\SendInvoiceWhatsAppJob;
use App\Mail\Company\InvoiceMail;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BillingService
{
    public function paginate(array $filters): array
    {
        $invoices = Invoice::query()
            ->with(['order:id,order_code', 'clinic:id,name', 'payments'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));

        return [
            'items' => InvoiceResource::collection($invoices->items())->resolve(),
            'meta' => ['page' => $invoices->currentPage(), 'per_page' => $invoices->perPage(), 'total' => $invoices->total()],
        ];
    }

    public function show(Invoice $invoice): array
    {
        $invoice->load(['order:id,order_code', 'clinic:id,name', 'payments']);
        return (new InvoiceResource($invoice))->resolve();
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['company_id'] = auth()->user()->company_id;
            $data['invoice_number'] = $data['invoice_number'] ?? ('INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)));
            $invoice = Invoice::create($data);
            return $this->show($invoice);
        });
    }

    public function update(Invoice $invoice, array $data): array
    {
        $invoice->update($data);
        return $this->show($invoice->fresh());
    }

    public function markPaid(Invoice $invoice): array
    {
        $invoice->update(['status' => 'paid', 'completion_date' => now()]);
        return $this->show($invoice->fresh());
    }

    public function send(Invoice $invoice): array
    {
        $invoice->loadMissing(['company', 'clinic']);
        if ($invoice->clinic?->email) {
            Mail::to($invoice->clinic->email)->send(new InvoiceMail($invoice));
        }
        SendInvoiceWhatsAppJob::dispatch($invoice->id);
        return ['invoice_id' => $invoice->id, 'queued' => true];
    }

    public function download(Invoice $invoice): array
    {
        $payload = $this->show($invoice);

        if (class_exists(Pdf::class)) {
            $pdf = Pdf::loadHTML(view('emails.invoice', ['invoice' => $invoice->loadMissing('clinic', 'order')])->render());
            return ['filename' => $invoice->invoice_number . '.pdf', 'content' => base64_encode($pdf->output())];
        }

        return ['filename' => $invoice->invoice_number . '.json', 'content' => base64_encode(json_encode($payload))];
    }

    public function payment(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['company_id'] = auth()->user()->company_id;
            $payment = Payment::create($data);
            if (($data['status'] ?? null) === 'paid') {
                $payment->invoice()->update(['status' => 'paid', 'completion_date' => $data['paid_at'] ?? now()]);
            }
            return (new PaymentResource($payment))->resolve();
        });
    }
}
