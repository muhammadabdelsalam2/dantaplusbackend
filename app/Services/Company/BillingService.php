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
use Illuminate\Support\Facades\Storage;

class BillingService
{
   public function paginate(array $filters): array
{
    $invoices = Invoice::query()
        ->with(['order:id,order_code', 'clinic:id,name', 'payments'])
        ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
        ->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('clinic', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        })
        ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
        ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '<=', $date))
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

   
private function generateAndStoreInvoicePdf(Invoice $invoice): string
{
    $invoice->loadMissing('clinic', 'order');
    $pdf = Pdf::loadHTML(view('emails.invoice', ['invoice' => $invoice])->render());

    $filename = 'invoice-' . $invoice->invoice_number . '-' . $invoice->id . '.pdf';
    $path = 'company/invoices/' . $filename;

    Storage::disk('public')->put($path, $pdf->output());

    return $path;
}

public function create(array $data): array
{
    return DB::transaction(function () use ($data) {
        $data['company_id'] = auth()->user()->company_id;
        $data['invoice_number'] = $data['invoice_number'] ?? ('INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)));
        $invoice = Invoice::create($data);
        $invoice->update(['file_path' => $this->generateAndStoreInvoicePdf($invoice)]);
        return $this->show($invoice->fresh());
    });
}

public function update(Invoice $invoice, array $data): array
{
    $invoice->update($data);
    $invoice->refresh();
    $invoice->update(['file_path' => $this->generateAndStoreInvoicePdf($invoice)]);
    return $this->show($invoice->fresh());
}

public function markPaid(Invoice $invoice): array
{
    $invoice->update(['status' => 'paid', 'completion_date' => now()]);
    $invoice->refresh();
    $invoice->update(['file_path' => $this->generateAndStoreInvoicePdf($invoice)]);
    return $this->show($invoice->fresh());
}

// خليه fallback لو فيه فواتير قديمة لسه ملهاش ملف مخزن
public function download(Invoice $invoice): array
{
    if (!$invoice->file_path || !Storage::disk('public')->exists($invoice->file_path)) {
        $invoice->update(['file_path' => $this->generateAndStoreInvoicePdf($invoice)]);
        $invoice->refresh();
    }

    return [
        'filename' => basename($invoice->file_path),
        'content'  => base64_encode(Storage::disk('public')->get($invoice->file_path)),
    ];
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
