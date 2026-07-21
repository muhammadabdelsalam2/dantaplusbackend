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
    public function __construct(private AccountService $service)
    {
    }
    public function summary(Request $request)
    {
        $filters = $request->validate(['period' => 'nullable|in:day,week,month,year',]);
        return ApiResponse::success($this->service->summary($filters['period'] ?? null), 'Account summary fetched successfully');
    }
    public function invoices(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:paid,unpaid', 'date_from' => 'nullable|date', 'date_to' => 'nullable|date|after_or_equal:date_from',]);
        return ApiResponse::success($this->service->invoices($filters), 'Account invoices fetched successfully');
    }
    public function expenses()
    {
        return ApiResponse::success($this->service->expenses(), 'Expenses fetched successfully');
    }
    public function storeExpense(StoreExpenseRequest $request)
    {
        return ApiResponse::success($this->service->createExpense($request->validated()), 'Expense created successfully', 201);
    }
    public function bankTransactions()
    {
        return ApiResponse::success($this->service->bankTransactions(), 'Bank transactions fetched successfully');
    }
    public function syncBankTransactions()
    {
        return ApiResponse::success($this->service->syncBankTransactions(), 'Bank transactions synced successfully');
    }
    public function profitLoss(Request $request)
    {
        $filters = $request->validate(['period' => 'nullable|in:day,week,month,year',]);
        $data = $this->service->profitLoss($filters['period'] ?? null);
        $data['download_url'] = \Illuminate\Support\Facades\URL::temporarySignedRoute('company.profit-loss.download.signed', now()->addDays(7), ['company_id' => auth()->user()->company_id, 'period' => $filters['period'] ?? null,]);
        return ApiResponse::success($data, 'Profit and loss report fetched successfully');
    }
    public function profitLossDownloadSigned(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }
        $companyId = (int) $request->query('company_id');
        $period = $request->query('period') ?: null;
        $payload = $this->service->generateProfitLossPdfBinaryForCompany($companyId, $period);
        return response($payload['content'], 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $payload['filename'] . '"',]);
    }
    public function profitLossWhatsAppLink(Request $request)
    {
        $filters = $request->validate(['period' => 'nullable|in:day,week,month,year',]);
        return ApiResponse::success($this->service->generateWhatsAppLink($filters['period'] ?? null), 'WhatsApp link generated successfully');
    }
}
