<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Resources\Patient\PatientInvoiceResource;
use App\Models\ClinicInvoice;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PatientInvoiceController extends BasePatientController
{
    public function index(Request $request)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $invoices = $this->invoiceQuery($patient)
            ->with(['appointment', 'doctor', 'payments'])
            ->latest('issued_at')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::success(PatientInvoiceResource::collection($invoices), 'Patient invoices retrieved successfully');
    }

    public function show(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $invoice = $this->invoiceQuery($patient)
            ->with(['appointment', 'doctor', 'payments'])
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return ApiResponse::error('Invoice not found', 404);
        }

        return ApiResponse::success(new PatientInvoiceResource($invoice), 'Patient invoice retrieved successfully');
    }

    public function download(Request $request, int $id)
    {
        $patient = $this->currentPatient($request);

        if ($this->isResponse($patient)) {
            return $patient;
        }

        $invoice = $this->invoiceQuery($patient)
            ->with(['appointment', 'doctor', 'payments', 'items', 'clinic', 'patient.user'])
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return ApiResponse::error('Invoice not found', 404);
        }

        $pdf = Pdf::loadView('pdf.simple-invoice', ['invoiceData' => $this->invoicePdfData($invoice)])->output();
        $filename = ($invoice->invoice_number ?: 'patient-invoice-' . $invoice->id) . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function invoiceQuery($patient)
    {
        return ClinicInvoice::query()
            ->where('patient_id', $patient->id)
            ->where('clinic_id', $patient->clinic_id);
    }
 public function downloadSigned(Request $request, int $id)
{
    if (! $request->hasValidSignature()) {
        abort(403, 'Invalid or expired link.');
    }

    $invoice = ClinicInvoice::query()
        ->with(['appointment', 'doctor', 'payments', 'items', 'clinic', 'patient.user'])
        ->find($id);

    if (! $invoice) {
        return ApiResponse::error('Invoice not found', 404);
    }

    $disposition = $request->query('mode') === 'view' ? 'inline' : 'attachment';

    return $this->renderInvoicePdf($invoice, $disposition);
}

private function renderInvoicePdf(ClinicInvoice $invoice, string $disposition = 'attachment')
{
    $pdf = Pdf::loadView('pdf.simple-invoice', ['invoiceData' => $this->invoicePdfData($invoice)])->output();
    $filename = ($invoice->invoice_number ?: 'patient-invoice-' . $invoice->id) . '.pdf';

    return response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
    ]);
}

private function invoicePdfData(ClinicInvoice $invoice): array
{
    $invoice->loadMissing(['clinic', 'patient.user', 'items']);
    $items = $invoice->items;

    return [
        'invoice_number' => $invoice->invoice_number,
        'date' => optional($invoice->issued_at)->format('m/d/Y'),
        'due_date' => optional($invoice->due_date)->format('m/d/Y'),
        'company' => [
            'name' => $invoice->clinic?->name,
            'address' => $invoice->clinic?->address,
            'phone' => $invoice->clinic?->phone,
            'email' => $invoice->clinic?->email,
        ],
        'bill_to' => [
            'name' => $invoice->patient?->user?->name,
            'address' => $invoice->patient?->address,
            'phone' => $invoice->patient?->phone,
        ],
        'items' => $items->isNotEmpty()
            ? $items->map(fn ($item) => [
                'description' => $item->description,
                'amount' => $item->amount,
            ])->values()->all()
            : [[
                'description' => $invoice->notes ?: 'Dental services',
                'amount' => $invoice->total,
            ]],
        'total' => (float) $invoice->total,
        'paid' => (float) $invoice->paid,
        'remaining' => (float) $invoice->remaining,
        'total_due' => (float) $invoice->remaining,
        'footer_message' => 'Thank you for your visit!',
    ];
}


}
