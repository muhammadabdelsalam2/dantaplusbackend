<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreInvoiceRequest;
use App\Http\Requests\Company\StorePaymentRequest;
use App\Http\Requests\Company\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\Company\BillingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(private BillingService $service) {}

   public function index(Request $request)
{
    $filters = $request->validate([
        'search'   => 'nullable|string|max:100',
        'status'   => 'nullable|in:paid,unpaid',
        'date_from'=> 'nullable|date',
        'date_to'  => 'nullable|date|after_or_equal:date_from',
        'per_page' => 'nullable|integer|min:1|max:100',
    ]);

    return ApiResponse::success($this->service->paginate($filters), 'Invoices fetched successfully');
}
    public function show(Invoice $id) { return ApiResponse::success($this->service->show($id), 'Invoice fetched successfully'); }
    public function store(StoreInvoiceRequest $request) { return ApiResponse::success($this->service->create($request->validated()), 'Invoice created successfully', 201); }
    public function update(UpdateInvoiceRequest $request, Invoice $id) { return ApiResponse::success($this->service->update($id, $request->validated()), 'Invoice updated successfully'); }
    public function markPaid(Invoice $id) { return ApiResponse::success($this->service->markPaid($id), 'Invoice marked as paid successfully'); }
    public function send(Invoice $id) { return ApiResponse::success($this->service->send($id), 'Invoice send queued successfully'); }
    public function download(Invoice $id)
    {
        $payload = $this->service->download($id);
        $filename = $payload['filename'];
        $content = base64_decode($payload['content']);
        $contentType = str_ends_with($filename, '.pdf') ? 'application/pdf' : 'application/json';

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
    public function downloadSigned(Request $request, int $id)
{
    if (! $request->hasValidSignature()) {
        abort(403, 'Invalid or expired link.');
    }

    $invoice = \App\Models\Invoice::query()->find($id);

    if (! $invoice) {
        return ApiResponse::error('Invoice not found', 404);
    }

    $payload = $this->service->downloadForInvoice($invoice);
    $filename = $payload['filename'];
    $content = base64_decode($payload['content']);
    $contentType = str_ends_with($filename, '.pdf') ? 'application/pdf' : 'application/json';

    return response($content, 200, [
        'Content-Type' => $contentType,
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}
    public function payments(StorePaymentRequest $request) { return ApiResponse::success($this->service->payment($request->validated()), 'Payment created successfully', 201); }
}
