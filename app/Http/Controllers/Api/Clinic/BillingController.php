<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicBillingRequest;
use App\Http\Requests\Clinic\StoreClinicExpenseRequest;
use App\Http\Requests\Clinic\StoreClinicInvoiceRequest;
use App\Http\Requests\Clinic\StoreClinicPaymentRequest;
use App\Services\Clinic\BillingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

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
        $result = $this->service->createExpense($request->validated(), $request->file('attachment'));

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

    public function sendInvoiceReminder(int $id)
    {
        $result = $this->service->sendInvoiceReminder($id);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function storeExpenseCategory(Request $request)
    {
        $result = $this->service->createExpenseCategory($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateExpenseCategory(Request $request, int $id)
    {
        $result = $this->service->updateExpenseCategory($id, $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function destroyExpenseCategory(int $id)
    {
        $result = $this->service->deleteExpenseCategory($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function profitLossChart(Request $request)
    {
        $result = $this->service->profitLossChart($request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', 'in:month,week,day'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function exportProfitLoss(Request $request)
    {
        $result = $this->service->exportProfitLoss($request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'in:pdf'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function sendProfitLossWhatsApp(Request $request)
    {
        $result = $this->service->sendProfitLossWhatsApp($request->validate([
            'to' => ['required', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
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
