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

        $html = view()->exists('pdf.clinic-invoice')
            ? view('pdf.clinic-invoice', ['invoice' => $invoice])->render()
            : view('emails.invoice', ['invoice' => $invoice])->render();

        $pdf = Pdf::loadHTML($html)->output();
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

    return $this->renderInvoicePdf($invoice);
}

private function renderInvoicePdf(ClinicInvoice $invoice)
{
    $html = view()->exists('pdf.clinic-invoice')
        ? view('pdf.clinic-invoice', ['invoice' => $invoice])->render()
        : view('emails.invoice', ['invoice' => $invoice])->render();

    $pdf = Pdf::loadHTML($html)->output();
    $filename = ($invoice->invoice_number ?: 'patient-invoice-' . $invoice->id) . '.pdf';

    return response($pdf, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"',
    ]);
}
}
