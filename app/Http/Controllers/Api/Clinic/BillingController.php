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
use Illuminate\Validation\Rule;

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

    public function cards()
    {
        $result = $this->service->dashboardCards();

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
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

    public function expenseCards(IndexClinicBillingRequest $request)
    {
        $result = $this->service->expenseCards($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateExpense(Request $request, int $id)
    {
        $result = $this->service->updateExpense($id, $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'expense_category_id' => ['sometimes', 'integer', 'exists:clinic_expense_categories,id'],
            'category' => ['sometimes', 'integer', 'exists:clinic_expense_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'expense_date' => ['sometimes', 'date'],
            'date' => ['sometimes', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'assigned_to_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'attachment' => ['sometimes', 'nullable', 'file', 'max:10240'],
        ]), $request->file('attachment'));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function destroyExpense(int $id)
    {
        $result = $this->service->deleteExpense($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function expenseMonthlyBreakdown(IndexClinicBillingRequest $request)
    {
        $result = $this->service->expenseMonthlyBreakdown($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function profitLoss(IndexClinicBillingRequest $request)
    {
        $result = $this->service->profitLoss($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function profitLossCards(IndexClinicBillingRequest $request)
    {
        $result = $this->service->profitLossCards($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function profitMonthlyTrend(IndexClinicBillingRequest $request)
    {
        $result = $this->service->profitMonthlyTrend($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
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
        $clinicId = auth()->user()?->clinic_id;
        $result = $this->service->createExpenseCategory($request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clinic_expense_categories', 'name')->where('clinic_id', $clinicId),
            ],
            'status' => ['nullable', 'in:active,inactive'],
        ], [
            'name.unique' => 'This category name already exists for your clinic.',
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateExpenseCategory(Request $request, int $id)
    {
        $clinicId = auth()->user()?->clinic_id;
        $result = $this->service->updateExpenseCategory($id, $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('clinic_expense_categories', 'name')->where('clinic_id', $clinicId)->ignore($id),
            ],
            'status' => ['sometimes', 'in:active,inactive'],
        ], [
            'name.unique' => 'This category name already exists for your clinic.',
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateExpenseCategoryStatus(Request $request, int $id)
    {
        $result = $this->service->updateExpenseCategory($id, $request->validate([
            'status' => ['required', 'in:active,inactive'],
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

    public function doctors()
    {
        $result = $this->service->clinicDoctors();

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function downloadProfitLoss(Request $request)
    {
        $result = $this->service->profitLossDownloadUrl($request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', 'in:month,week,day'],
        ]));

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function downloadProfitLossSigned(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', 'in:month,week,day'],
        ]);

        $clinicId = (int) $data['clinic_id'];
        unset($data['clinic_id']);

        $result = $this->service->profitLossPdfPayloadForClinic($clinicId, $data);

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return response($result['data']['content'], 200, [
            'Content-Type' => $result['data']['content_type'],
            'Content-Disposition' => 'attachment; filename="profit-loss-report.pdf"',
        ]);
    }

    public function labInvoices(IndexClinicBillingRequest $request)
    {
        $result = $this->service->labInvoices($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function labInvoiceShow(int $id)
    {
        $result = $this->service->labInvoiceShow($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function updateLabInvoiceStatus(Request $request, int $id)
    {
        $result = $this->service->updateLabInvoiceStatus($id, $request->validate([
            'status' => ['required', 'in:Paid,Disputed,paid,disputed'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function materialInvoices(IndexClinicBillingRequest $request)
    {
        $result = $this->service->materialInvoices($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function materialInvoiceShow(int $id)
    {
        $result = $this->service->materialInvoiceShow($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function materialInvoiceContact(int $id)
    {
        $result = $this->service->materialInvoiceContact($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function doctorEarnings(IndexClinicBillingRequest $request)
    {
        $result = $this->service->doctorEarnings($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function doctorEarningsDownload(Request $request)
    {
        $result = $this->service->doctorEarningsDownloadUrl($request->validate([
            'date_range' => ['nullable', 'in:all_time,this_week,this_month,this_year,All Time,This Week,This Month,This Year'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'doctor_id' => ['nullable', 'integer'],
            'case_type' => ['nullable', 'in:All Case Types,Cash,Insurance,all,cash,insurance'],
            'format' => ['required', 'in:pdf,excel'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function sendDoctorEarnings(Request $request, int $doctor)
    {
        $result = $this->service->sendDoctorEarnings($doctor, $request->validate([
            'date_range' => ['nullable', 'in:all_time,this_week,this_month,this_year,All Time,This Week,This Month,This Year'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'case_type' => ['nullable', 'in:All Case Types,Cash,Insurance,all,cash,insurance'],
            'channel' => ['nullable', 'string', 'max:50'],
            'sent_to' => ['nullable', 'string', 'max:255'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function extractAccounts(IndexClinicBillingRequest $request)
    {
        $result = $this->service->extractAccounts($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function sendExtractAccountsReport(Request $request)
    {
        $result = $this->service->sendExtractAccountsReport($request->validate([
            'doctor_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'channel' => ['nullable', 'string', 'max:50'],
            'sent_to' => ['required', 'string', 'max:255'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

   public function billingDownloadSigned(Request $request)
{
    $result = $this->service->signedBillingDownload($request->validate([
        'clinic_id' => ['nullable', 'integer'],
        'type' => ['nullable', 'in:lab_invoice,material_invoice,doctor_earnings,extract_accounts'], // ⭐ أضيف extract_accounts
        'format' => ['nullable', 'in:pdf,excel'],
        'id' => ['nullable', 'integer'],
        'date_range' => ['nullable', 'string'],
        'date_from' => ['nullable', 'date'],
        'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        'doctor_id' => ['nullable', 'integer'],
        'case_type' => ['nullable', 'string'],
    ]));

    if (! $result['success']) {
        return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    return response($result['data']['content'], 200, [
        'Content-Type' => $result['data']['content_type'],
        'Content-Disposition' => 'attachment; filename="' . $result['data']['filename'] . '"',
    ]);
}

    public function sendExtractAccountWhatsApp(Request $request)
    {
        $result = $this->service->sendExtractAccountWhatsApp($request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]));

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
