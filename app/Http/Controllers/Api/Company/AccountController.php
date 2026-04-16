<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreExpenseRequest;
use App\Services\Company\AccountService;
use App\Support\ApiResponse;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(private AccountService $service) {}

    public function summary() { return ApiResponse::success($this->service->summary(), 'Account summary fetched successfully'); }
    public function invoices() { return ApiResponse::success($this->service->invoices(), 'Account invoices fetched successfully'); }
    public function expenses() { return ApiResponse::success($this->service->expenses(), 'Expenses fetched successfully'); }
    public function storeExpense(StoreExpenseRequest $request) { return ApiResponse::success($this->service->createExpense($request->validated()), 'Expense created successfully', 201); }
    public function bankTransactions() { return ApiResponse::success($this->service->bankTransactions(), 'Bank transactions fetched successfully'); }
    public function syncBankTransactions() { return ApiResponse::success($this->service->syncBankTransactions(), 'Bank transactions synced successfully'); }
    public function profitLoss() { return ApiResponse::success($this->service->profitLoss(), 'Profit and loss report fetched successfully'); }
}
