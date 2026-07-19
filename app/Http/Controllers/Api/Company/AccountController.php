<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreExpenseRequest;
use App\Services\Company\AccountService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(private AccountService $service) {}

    public function summary(Request $request)
    {
        $filters = $request->validate([
            'period' => 'nullable|in:day,week,month,year',
        ]);

        return ApiResponse::success($this->service->summary($filters['period'] ?? null), 'Account summary fetched successfully');
    }

    public function invoices(Request $request)
    {
        $filters = $request->validate([
            'search'    => 'nullable|string|max:100',
            'status'    => 'nullable|in:paid,unpaid',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        return ApiResponse::success($this->service->invoices($filters), 'Account invoices fetched successfully');
    }

    public function expenses() { return ApiResponse::success($this->service->expenses(), 'Expenses fetched successfully'); }
    public function storeExpense(StoreExpenseRequest $request) { return ApiResponse::success($this->service->createExpense($request->validated()), 'Expense created successfully', 201); }
    public function bankTransactions() { return ApiResponse::success($this->service->bankTransactions(), 'Bank transactions fetched successfully'); }
    public function syncBankTransactions() { return ApiResponse::success($this->service->syncBankTransactions(), 'Bank transactions synced successfully'); }
    public function profitLoss(Request $request)
    {
        $filters = $request->validate([
            'period' => 'nullable|in:day,week,month,year',
        ]);

        return ApiResponse::success($this->service->profitLoss($filters['period'] ?? null), 'Profit and loss report fetched successfully');
    }
    public function profitLossDownload(Request $request)
{
    $filters = $request->validate(['period' => 'nullable|in:day,week,month,year']);
    return $this->service->downloadPdf($filters['period'] ?? null);
}

public function profitLossWhatsAppLink(Request $request)
{
    $filters = $request->validate([
        'period' => 'nullable|in:day,week,month,year',
    ]);

    return ApiResponse::success(
        $this->service->generateWhatsAppLink($filters['period'] ?? null),
        'WhatsApp link generated successfully'
    );
}
}
