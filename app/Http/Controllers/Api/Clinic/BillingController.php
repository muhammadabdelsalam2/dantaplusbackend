<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicBillingRequest;
use App\Http\Requests\Clinic\StoreClinicExpenseRequest;
use App\Http\Requests\Clinic\StoreClinicInvoiceRequest;
use App\Http\Requests\Clinic\StoreClinicPaymentRequest;
use App\Services\Clinic\BillingService;
use App\Support\ApiResponse;

class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(private BillingService $service)
    {
    }

    public function index(IndexClinicBillingRequest $request)
    {
        $result = $this->service->indexInvoices($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function store(StoreClinicInvoiceRequest $request)
    {
        $result = $this->service->createInvoice($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function show(int $id)
    {
        $result = $this->service->showInvoice($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function payment(StoreClinicPaymentRequest $request, int $invoice)
    {
        $result = $this->service->recordPayment($invoice, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function payments(IndexClinicBillingRequest $request)
    {
        $result = $this->service->indexPayments($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function expenses(IndexClinicBillingRequest $request)
    {
        $result = $this->service->indexExpenses($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeExpense(StoreClinicExpenseRequest $request)
    {
        $result = $this->service->createExpense($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function profitLoss(IndexClinicBillingRequest $request)
    {
        $result = $this->service->profitLoss($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function expenseCategories()
    {
        $result = $this->service->expenseCategories();

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
