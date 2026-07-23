<?php

namespace App\Http\Controllers\Api\Lab\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\Accounting\GenerateMonthlyInvoicesRequest;
use App\Http\Requests\Lab\Accounting\IndexLabAccountingRequest;
use App\Http\Requests\Lab\Accounting\StoreLabExpenseCategoryRequest;
use App\Http\Requests\Lab\Accounting\StoreLabExpenseRequest;
use App\Http\Requests\Lab\Accounting\StoreLabInvoiceRequest;
use App\Http\Requests\Lab\Accounting\StoreLabPaymentRequest;
use App\Http\Requests\Lab\Accounting\UpdateLabExpenseCategoryRequest;
use App\Http\Requests\Lab\Accounting\UpdateLabExpenseRequest;
use App\Http\Requests\Lab\Accounting\UpdateLabInvoiceRequest;
use App\Http\Resources\Lab\Accounting\LabExpenseCategoryResource;
use App\Http\Resources\Lab\Accounting\LabExpenseResource;
use App\Http\Resources\Lab\Accounting\LabInvoiceResource;
use App\Http\Resources\Lab\Accounting\LabPaymentResource;
use App\Services\Lab\Accounting\LabAccountingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LabAccountingController extends Controller
{
    use ApiResponse;

    public function __construct(private LabAccountingService $service)
    {
    }

    public function summary(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->summary($request->validated()));
    }

    public function incomeVsExpensesChart(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->incomeVsExpensesChart($request->validated()));
    }

    public function invoices(IndexLabAccountingRequest $request): JsonResponse
    {
        $result = $this->service->invoices($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success([
            'items' => LabInvoiceResource::collection($result['data']['items'])->resolve(),
            'pagination' => $result['data']['pagination'],
        ], $result['message'], $result['code']);
    }

    public function showInvoice(int $invoice): JsonResponse
    {
        $result = $this->service->showInvoice($invoice, request()->query());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function monthlyInvoicePreview(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->monthlyInvoicePreview($request->validated()));
    }

    public function storeInvoice(StoreLabInvoiceRequest $request): JsonResponse
    {
        $result = $this->service->createInvoice($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabInvoiceResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function updateInvoice(UpdateLabInvoiceRequest $request, int $invoice): JsonResponse
    {
        $result = $this->service->updateInvoice($invoice, $request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabInvoiceResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function generateMonthlyInvoices(GenerateMonthlyInvoicesRequest $request): JsonResponse
    {
        $result = $this->service->generateMonthlyInvoices($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success([
            'created' => LabInvoiceResource::collection($result['data']['created'])->resolve(),
            'skipped' => $result['data']['skipped'],
            'created_count' => $result['data']['created_count'],
            'skipped_count' => $result['data']['skipped_count'],
        ], $result['message'], $result['code']);
    }

    public function recordPayment(StoreLabPaymentRequest $request, int $invoice): JsonResponse
    {
        $result = $this->service->recordPayment($invoice, $request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabPaymentResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function expenses(IndexLabAccountingRequest $request): JsonResponse
    {
        $result = $this->service->expenses($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success([
            'items' => LabExpenseResource::collection($result['data']['items'])->resolve(),
            'pagination' => $result['data']['pagination'],
        ], $result['message'], $result['code']);
    }

    public function storeExpense(StoreLabExpenseRequest $request): JsonResponse
    {
        $result = $this->service->createExpense($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabExpenseResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function updateExpense(UpdateLabExpenseRequest $request, int $expense): JsonResponse
    {
        $result = $this->service->updateExpense($expense, $request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabExpenseResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function deleteExpense(int $expense): JsonResponse
    {
        return $this->respond($this->service->deleteExpense($expense));
    }

    public function categories(): JsonResponse
    {
        $result = $this->service->categories();
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success(LabExpenseCategoryResource::collection($result['data'])->resolve(), $result['message'], $result['code']);
    }

    public function storeCategory(StoreLabExpenseCategoryRequest $request): JsonResponse
    {
        $result = $this->service->createCategory($request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabExpenseCategoryResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function updateCategory(UpdateLabExpenseCategoryRequest $request, int $category): JsonResponse
    {
        $result = $this->service->updateCategory($category, $request->validated());
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success((new LabExpenseCategoryResource($result['data']))->resolve(), $result['message'], $result['code']);
    }

    public function deleteCategory(int $category): JsonResponse
    {
        return $this->respond($this->service->deleteCategory($category));
    }

    public function technicianEarnings(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->technicianEarnings($request->validated()));
    }

    public function topPayingClinics(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->topPayingClinics($request->validated()));
    }

    public function analytics(IndexLabAccountingRequest $request): JsonResponse
    {
        return $this->respond($this->service->analytics($request->validated()));
    }

    public function exportInvoice(Request $request, int $invoice): JsonResponse
    {
        $format = (string) $request->query('format', 'csv');
        if (! in_array($format, ['csv', 'excel', 'pdf'], true)) {
            return ApiResponse::error('Invalid export format.', 422, ['format' => ['Allowed: csv, excel, pdf']]);
        }

        return $this->respond($this->service->exportInvoice($invoice, $format));
    }

    public function sendInvoiceWhatsApp(int $invoice): JsonResponse
    {
        return $this->respond($this->service->sendInvoiceWhatsApp($invoice));
    }

    public function invoicePdf(Request $request, int $invoice): Response
    {
        return $this->service->downloadInvoice($invoice, 'pdf', $request->query());
    }

    public function invoiceCsv(Request $request, int $invoice): Response
    {
        return $this->service->downloadInvoice($invoice, 'csv', $request->query());
    }

    public function downloadInvoice(Request $request, int $invoice, string $format): Response
    {
        return $this->service->downloadInvoice($invoice, $format, $request->query(), false);
    }

    public function invoiceWhatsAppPreview(int $invoice): JsonResponse
    {
        return $this->respond($this->service->invoiceWhatsAppPreview($invoice));
    }

    public function paymentLink(int $invoice): JsonResponse
    {
        return $this->respond($this->service->paymentLink($invoice));
    }

    public function sendPaymentLink(int $invoice): JsonResponse
    {
        return $this->respond($this->service->sendPaymentLink($invoice));
    }

    public function payWithStripe(int $invoice): JsonResponse
    {
        return $this->respond($this->service->placeholderPaymentAttempt($invoice, 'Stripe'));
    }

    public function payWithPayPal(int $invoice): JsonResponse
    {
        return $this->respond($this->service->placeholderPaymentAttempt($invoice, 'PayPal'));
    }

    public function sendToClinicSystem(int $invoice): JsonResponse
    {
        return $this->respond($this->service->sendToClinicSystem($invoice));
    }

    private function respond(array $result): JsonResponse
    {
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
