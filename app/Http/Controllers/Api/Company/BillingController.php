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
    public function download(Invoice $id) { return ApiResponse::success($this->service->download($id), 'Invoice download prepared successfully'); }
    public function payments(StorePaymentRequest $request) { return ApiResponse::success($this->service->payment($request->validated()), 'Payment created successfully', 201); }
}
