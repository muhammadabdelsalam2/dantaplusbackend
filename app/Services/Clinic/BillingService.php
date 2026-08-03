<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicExpenseCategoryResource;
use App\Http\Resources\Clinic\ClinicExpenseResource;
use App\Http\Resources\Clinic\ClinicInvoiceResource;
use App\Http\Resources\Clinic\ClinicPaymentResource;
use App\Models\ClinicAppointment;
use App\Models\Clinic;
use App\Models\BillingReportDelivery;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\LabInvoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\WhatsappMessage;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Clinic\Billing\ClinicBillingRepositoryInterface;
use App\Services\Clinic\WhatsappBot\Providers\WhatsAppProviderInterface;
use App\Support\ServiceResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class BillingService
{
    private const PROFIT_LOSS_DOWNLOAD_FILENAME = 'profit-loss-report.pdf';
    private const PROFIT_LOSS_DOWNLOAD_ROUTE = 'clinic.billing.profit-loss.download.signed';
    private const PROFIT_LOSS_DOWNLOAD_TTL_MINUTES = 10;

    public function __construct(
        private ClinicBillingRepositoryInterface $repository,
        private WhatsAppProviderInterface $whatsAppProvider,
    )
    {
    }

    public function indexInvoices(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $this->syncInvoiceStatuses($clinicId);

        $rows = $this->repository->paginateInvoices($clinicId, $filters);

        return ServiceResult::success([
            'items' => ClinicInvoiceResource::collection($rows->items())->resolve(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 'Invoices fetched successfully');
    }

    public function dashboardCards(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $current = [now()->startOfMonth(), now()->endOfMonth()];
        $previous = [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()];

        $cards = [
            $this->billingCard('Total Billed', 'total_billed', $this->invoiceSum($clinicId, 'total', $current), $this->invoiceSum($clinicId, 'total', $previous)),
            $this->billingCard('Outstanding', 'outstanding', $this->invoiceSum($clinicId, 'remaining', $current), $this->invoiceSum($clinicId, 'remaining', $previous)),
            $this->billingCard('Total Paid', 'total_paid', $this->paymentSum($clinicId, $current), $this->paymentSum($clinicId, $previous)),
        ];

        return ServiceResult::success(['cards' => $cards], 'Billing cards fetched successfully');
    }

    public function createInvoice(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $patient = Patient::query()->where('clinic_id', $clinicId)->find($data['patient_id']);

        if (! $patient) {
            return ServiceResult::error('Patient not found.', null, ['patient_id' => ['Patient not found.']], 422);
        }

        $doctor = User::query()->where('clinic_id', $clinicId)->role('doctor')->find($data['doctor_user_id']);
        if (! $doctor) {
            return ServiceResult::error('Doctor not found.', null, ['doctor_user_id' => ['Doctor not found.']], 422);
        }

        $appointment = ! empty($data['appointment_id'])
            ? ClinicAppointment::query()->where('clinic_id', $clinicId)->find($data['appointment_id'])
            : null;

        if (! empty($data['appointment_id']) && ! $appointment) {
            return ServiceResult::error('Appointment not found.', null, ['appointment_id' => ['Appointment not found.']], 422);
        }

        $items = collect($data['items']);
        $total = round((float) $items->sum(fn (array $item) => (float) $item['amount']), 2);
        $paid = min(round((float) ($data['paid'] ?? 0), 2), $total);

        try {
            $invoice = DB::transaction(function () use ($appointment, $clinicId, $data, $doctor, $items, $paid, $patient, $total) {
                $invoice = $this->repository->createInvoice([
                    'clinic_id' => $clinicId,
                    'patient_id' => $patient->id,
                    'doctor_user_id' => $doctor->id,
                    'appointment_id' => $appointment?->id,
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => max($total - $paid, 0),
                    'status' => $this->resolveInvoiceStatus($total, $paid, $data['due_date'] ?? null),
                    'payment_method' => $data['payment_method'] ?? null,
                    'issued_at' => $data['issued_at'] ?? now()->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($items as $item) {
                    $this->repository->createInvoiceItem([
                        'clinic_invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                    ]);
                }

                if ($paid > 0) {
                    $this->repository->createPayment([
                        'clinic_invoice_id' => $invoice->id,
                        'clinic_id' => $clinicId,
                        'recorded_by' => auth()->id(),
                        'amount' => $paid,
                        'method' => $data['payment_method'] ?? null,
                        'paid_at' => $data['issued_at'] ?? now(),
                        'notes' => 'Initial payment',
                    ]);
                }

                return $invoice;
            });
        } catch (Throwable $exception) {
            return ServiceResult::error('Failed to create invoice.', null, ['server' => [$exception->getMessage()]], 500);
        }

        return ServiceResult::success(
            (new ClinicInvoiceResource($this->repository->findInvoice($clinicId, $invoice->id)))->resolve(),
            'Invoice created successfully',
            201
        );
    }

    public function recordPayment(int $invoiceId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $invoice = $this->repository->findInvoice($clinicId, $invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        if ((float) $invoice->remaining <= 0) {
            return ServiceResult::error('Invoice is already fully paid.', null, ['amount' => ['Invoice is already fully paid.']], 422);
        }

        $amount = (float) ($data['amount'] ?? $data['amount_to_pay']);
        if ($amount > (float) $invoice->remaining) {
            return ServiceResult::error('Payment amount exceeds remaining balance.', null, ['amount' => ['Payment amount exceeds remaining balance.']], 422);
        }

        $payment = DB::transaction(function () use ($amount, $invoice, $data) {
            $payment = $this->repository->createPayment([
                'clinic_invoice_id' => $invoice->id,
                'clinic_id' => $invoice->clinic_id,
                'recorded_by' => auth()->id(),
                'amount' => $amount,
                'method' => $data['method'] ?? $data['payment_method'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaid = round((float) $invoice->paid + (float) $payment->amount, 2);
            $remaining = max(round((float) $invoice->total - $newPaid, 2), 0);

            $this->repository->updateInvoice($invoice, [
                'paid' => $newPaid,
                'remaining' => $remaining,
                'status' => $this->resolveInvoiceStatus((float) $invoice->total, $newPaid, optional($invoice->due_date)?->toDateString()),
            ]);

            return $payment->fresh('recorder:id,name');
        });

        return ServiceResult::success((new ClinicPaymentResource($payment))->resolve(), 'Payment recorded successfully', 201);
    }

    public function indexPayments(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $this->syncInvoiceStatuses($clinicId);

        $rows = $this->repository->paginatePayments($clinicId, $filters);

        return ServiceResult::success([
            'items' => ClinicPaymentResource::collection($rows->items())->resolve(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ], 'Payments fetched successfully');
    }

    public function indexExpenses(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = $this->repository->paginateExpenses($clinicId, $filters);
        $summary = $this->repository->expenseSummary($clinicId, $filters);

        return ServiceResult::success([
            'items' => ClinicExpenseResource::collection($rows->items())->resolve(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'summary' => $summary,
        ], 'Expenses fetched successfully');
    }

    public function expenseCards(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $summary = $this->repository->expenseSummary($clinicId, $filters);

        return ServiceResult::success([
            'cards' => [
                ['label' => 'Total Expenses Filtered', 'key' => 'total_expenses_filtered', 'value' => $summary['total_expenses']],
                ['label' => 'Top Category', 'key' => 'top_category', 'value' => $summary['top_category']['name'] ?? null, 'amount' => $summary['top_category']['amount'] ?? 0],
                ['label' => 'Monthly Trend', 'key' => 'monthly_trend', 'value' => $summary['monthly_trend_percent'], 'trend' => $summary['monthly_trend_percent'] >= 0 ? 'up' : 'down'],
            ],
        ], 'Expense cards fetched successfully');
    }

    public function createExpense(array $data, $attachment = null): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $category = $this->repository->findExpenseCategory($clinicId, (int) ($data['expense_category_id'] ?? $data['category']));
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, ['expense_category_id' => ['Expense category not found.']], 422);
        }

        $assignedToUserId = $data['assigned_to_user_id'] ?? $data['assigned_to'] ?? null;
        if (! empty($assignedToUserId)) {
            $assignedUser = User::query()->where('clinic_id', $clinicId)->find($assignedToUserId);
            if (! $assignedUser) {
                return ServiceResult::error('Assigned user not found.', null, ['assigned_to_user_id' => ['Assigned user not found.']], 422);
            }
        }

        $expense = $this->repository->createExpense([
            'clinic_id' => $clinicId,
            'expense_category_id' => $category->id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? null,
            'expense_date' => $data['expense_date'] ?? $data['date'],
            'assigned_to_user_id' => $assignedToUserId,
            'notes' => $data['notes'] ?? null,
            'attachment_path' => $attachment ? $attachment->store('clinic/expenses', 'public') : null,
        ])->load(['category:id,name', 'assignee:id,name']);

        return ServiceResult::success((new ClinicExpenseResource($expense))->resolve(), 'Expense created successfully', 201);
    }

    public function updateExpense(int $expenseId, array $data, $attachment = null): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $expense = $this->repository->findExpense($clinicId, $expenseId);
        if (! $expense) {
            return ServiceResult::error('Expense not found.', null, null, 404);
        }

        $payload = array_filter([
            'title' => $data['title'] ?? null,
            'expense_category_id' => $data['expense_category_id'] ?? $data['category'] ?? null,
            'amount' => $data['amount'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'expense_date' => $data['expense_date'] ?? $data['date'] ?? null,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'attachment_path' => $attachment ? $attachment->store('clinic/expenses', 'public') : null,
        ], static fn ($value) => $value !== null);

        if (isset($payload['expense_category_id']) && ! $this->repository->findExpenseCategory($clinicId, (int) $payload['expense_category_id'])) {
            return ServiceResult::error('Expense category not found.', null, ['expense_category_id' => ['Expense category not found.']], 422);
        }

        if (isset($payload['assigned_to_user_id']) && ! User::query()->where('clinic_id', $clinicId)->whereKey($payload['assigned_to_user_id'])->exists()) {
            return ServiceResult::error('Assigned user not found.', null, ['assigned_to_user_id' => ['Assigned user not found.']], 422);
        }

        return ServiceResult::success(
            (new ClinicExpenseResource($this->repository->updateExpense($expense, $payload)))->resolve(),
            'Expense updated successfully'
        );
    }

    public function deleteExpense(int $expenseId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $expense = $this->repository->findExpense($clinicId, $expenseId);
        if (! $expense) {
            return ServiceResult::error('Expense not found.', null, null, 404);
        }

        $expense->delete();

        return ServiceResult::success(null, 'Expense deleted successfully');
    }

    public function expenseMonthlyBreakdown(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $from = Carbon::parse($filters['date_from'] ?? now()->startOfYear());
        $to = Carbon::parse($filters['date_to'] ?? now()->endOfMonth());

        $items = collect(CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->endOfMonth()))
            ->map(function (Carbon $month) use ($clinicId) {
                return [
                    'month' => $month->format('M'),
                    'expenses' => round((float) \App\Models\ClinicExpense::query()
                        ->where('clinic_id', $clinicId)
                        ->whereBetween('expense_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                        ->sum('amount'), 2),
                ];
            })
            ->values()
            ->all();

        return ServiceResult::success($items, 'Expense monthly breakdown fetched successfully');
    }

    public function sendInvoiceReminder(int $invoiceId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $invoice = $this->repository->findInvoice($clinicId, $invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        if ($invoice->reminder_sent) {
            return ServiceResult::error(
                'Reminder already sent for this invoice.',
                null,
                [
                    'invoice' => ['Reminder already sent for this invoice.'],
                    'invoice_id' => [$invoice->id],
                    'reminder_sent_at' => [optional($invoice->reminder_sent_at)?->toISOString()],
                ],
                422
            );
        }

        $phone = $invoice->patient?->user?->phone ?: $invoice->patient?->phone;
        if (! $phone) {
            return ServiceResult::error('Patient phone not found.', null, ['phone' => ['Patient phone not found.']], 422);
        }

        $message = sprintf(
            'Reminder: invoice %s is currently %s. Total %.2f, paid %.2f, remaining %.2f. Please contact the clinic if you need any support.',
            $invoice->invoice_number,
            (string) $invoice->status,
            (float) $invoice->total,
            (float) $invoice->paid,
            (float) $invoice->remaining
        );

        $providerResult = $this->whatsAppProvider->sendMessage($phone, $message, $invoice->clinic);

        WhatsappMessage::query()->create([
            'clinic_id' => $clinicId,
            'patient_phone' => $phone,
            'message' => $message,
            'reply' => null,
            'intent' => 'invoice_reminder',
            'created_at' => now(),
        ]);

        $this->repository->updateInvoice($invoice, [
            'reminder_sent' => true,
            'reminder_sent_at' => now(),
            'reminder_sent_by' => auth()->id(),
        ]);

        return ServiceResult::success([
            'already_sent' => false,
            'queued' => (bool) ($providerResult['success'] ?? false),
            'invoice_id' => $invoice->id,
            'reminder_sent' => true,
            'remaining' => (float) $invoice->remaining,
            'phone' => $phone,
            'message' => $message,
            'provider' => $providerResult['provider'] ?? null,
        ], 'Invoice reminder processed successfully');
    }

    public function profitLoss(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $summary = $this->repository->profitLossSummary($clinicId, $filters);
        $download = $this->profitLossDownloadUrlData($clinicId, $filters);

        return ServiceResult::success(
            array_merge($summary, [
                'download_url' => $download['download_url'],
                'download_expires_at' => $download['expires_at'],
                'download_filename' => $download['filename'],
            ]),
            'Profit and loss fetched successfully'
        );
    }

    public function profitLossCards(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $summary = $this->profitLossBreakdown($clinicId, $filters);

        return ServiceResult::success([
            'cards' => [
                ['label' => 'Total Revenue', 'key' => 'total_revenue', 'value' => $summary['total_revenue']],
                ['label' => 'Cost of Goods', 'key' => 'cost_of_goods', 'value' => $summary['cost_of_goods']],
                ['label' => 'Operating Expenses', 'key' => 'operating_expenses', 'value' => $summary['operating_expenses']],
                ['label' => 'Net Profit', 'key' => 'net_profit', 'value' => $summary['net_profit']],
            ],
        ], 'Profit and loss cards fetched successfully');
    }

    public function profitMonthlyTrend(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $from = Carbon::parse($filters['date_from'] ?? now()->startOfYear());
        $to = Carbon::parse($filters['date_to'] ?? now()->endOfMonth());

        $items = collect(CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->endOfMonth()))
            ->map(function (Carbon $month) use ($clinicId) {
                $summary = $this->profitLossBreakdown($clinicId, [
                    'date_from' => $month->copy()->startOfMonth()->toDateString(),
                    'date_to' => $month->copy()->endOfMonth()->toDateString(),
                ]);

                return [
                    'month' => $month->format('M'),
                    'profit' => $summary['net_profit'],
                ];
            })
            ->values()
            ->all();

        return ServiceResult::success($items, 'Monthly profit trend fetched successfully');
    }

    public function expenseCategories(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            ClinicExpenseCategoryResource::collection($this->repository->listExpenseCategories($clinicId))->resolve(),
            'Expense categories fetched successfully'
        );
    }

    public function clinicDoctors(): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            User::query()
                ->where('clinic_id', $clinicId)
                ->role('doctor')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn (User $doctor) => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'email' => $doctor->email,
                    'phone' => $doctor->phone,
                ])
                ->values()
                ->all(),
            'Doctors fetched successfully'
        );
    }

    public function createExpenseCategory(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $category = ClinicExpenseCategory::query()->create([
            'clinic_id' => $clinicId,
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
        ]);

        return ServiceResult::success((new ClinicExpenseCategoryResource($category))->resolve(), 'Expense category created successfully', 201);
    }

    public function updateExpenseCategory(int $categoryId, array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $category = $this->repository->findExpenseCategory($clinicId, $categoryId);
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, null, 404);
        }

        $category->update($data);

        return ServiceResult::success((new ClinicExpenseCategoryResource($category->fresh()))->resolve(), 'Expense category updated successfully');
    }

    public function deleteExpenseCategory(int $categoryId): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $category = $this->repository->findExpenseCategory($clinicId, $categoryId);
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, null, 404);
        }

        if ($category->expenses()->exists()) {
            return ServiceResult::error('Expense category is used by expenses.', null, ['category' => ['Category has linked expenses.']], 422);
        }

        $category->delete();

        return ServiceResult::success(null, 'Expense category deleted successfully');
    }

    public function profitLossChart(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return $this->profitLossChartForClinic($clinicId, $filters);
    }

    public function profitLossChartForClinic(int $clinicId, array $filters = []): array
    {
        $groupBy = $filters['group_by'] ?? 'month';
        $from = Carbon::parse($filters['date_from'] ?? now()->subMonths(5)->startOfMonth());
        $to = Carbon::parse($filters['date_to'] ?? now()->endOfMonth());
        $summary = $this->repository->profitLossSummary($clinicId, [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
        ]);

        $series = $this->buildProfitLossSeries($clinicId, $from, $to, $groupBy);

        return ServiceResult::success([
            'summary' => $summary,
            'group_by' => $groupBy,
            'series' => $series,
        ], 'Profit and loss chart fetched successfully');
    }

    public function labInvoices(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = LabInvoice::query()
            ->with(['lab:id,name', 'doctor.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['lab_id'] ?? null, fn ($query, int $labId) => $query->where('lab_id', $labId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', strtolower($status)))
            ->when($filters['date_from'] ?? $filters['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['date_to'] ?? $filters['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->latest('issue_date')
            ->paginate($filters['per_page'] ?? 15);

        $items = collect($rows->items())->map(fn (LabInvoice $invoice) => $this->mapLabInvoice($invoice))->values()->all();

        return ServiceResult::success([
            'items' => $items,
            'empty_message' => $items === [] ? 'No invoices received from labs yet.' : null,
            'pagination' => $this->pagination($rows),
        ], 'Lab invoices fetched successfully');
    }

    public function labInvoiceShow(int $invoiceId): array
    {
        $invoice = LabInvoice::query()
            ->with(['lab:id,name,email,phone', 'doctor.user:id,name', 'items'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($invoiceId);

        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        return ServiceResult::success($this->mapLabInvoice($invoice) + [
            'items' => $invoice->items->map(fn ($item) => [
                'service_name' => $item->service_name,
                'patient_name' => $item->patient_name,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->values()->all(),
        ], 'Lab invoice fetched successfully');
    }

    public function updateLabInvoiceStatus(int $invoiceId, array $data): array
    {
        $invoice = LabInvoice::query()
            ->with('lab:id,name')
            ->where('clinic_id', $this->currentClinicId())
            ->find($invoiceId);

        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $status = strtolower($data['status']) === 'paid' ? 'paid' : 'disputed';
        $invoice->update([
            'status' => $status,
            'paid_amount' => $status === 'paid' ? $invoice->total_amount : $invoice->paid_amount,
            'remaining_amount' => $status === 'paid' ? 0 : $invoice->remaining_amount,
        ]);

        Notification::query()->create([
            'title' => 'Lab invoice status updated',
            'message' => 'Invoice ' . $invoice->invoice_number . ' was marked as ' . ucfirst($status) . '.',
            'type' => 'Billing',
            'status' => 'sent',
            'audience_type' => 'lab',
            'audience_id' => $invoice->lab_id,
            'priority' => 'Normal',
            'delivery_method' => ['InApp'],
            'delivery_methods' => ['InApp'],
            'sender_id' => auth()->id(),
            'sender_name' => auth()->user()?->name,
            'link' => '/lab/billing/invoices/' . $invoice->id,
        ]);

        return ServiceResult::success([
            'invoice' => $this->mapLabInvoice($invoice->fresh('lab:id,name')),
            'notification' => [
                'title' => 'Lab invoice status updated',
                'message' => 'Invoice ' . $invoice->invoice_number . ' was marked as ' . ucfirst($status) . '.',
                'type' => 'Billing',
                'audience' => 'Lab',
                'deliveryMethod' => 'InApp',
                'priority' => 'Normal',
            ],
        ], 'Lab invoice status updated successfully');
    }

    public function materialInvoices(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->with(['supplierCompany:id,name,email,phone,website,address', 'items.product:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['date_from'] ?? $filters['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('order_date', '>=', $date))
            ->when($filters['date_to'] ?? $filters['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('order_date', '<=', $date))
            ->latest('order_date')
            ->paginate($filters['per_page'] ?? 15);

        return ServiceResult::success([
            'items' => collect($rows->items())->map(fn (Order $order) => $this->mapMaterialInvoice($order))->values()->all(),
            'pagination' => $this->pagination($rows),
        ], 'Material invoices fetched successfully');
    }

    public function materialInvoiceShow(int $orderId): array
    {
        $order = Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->with(['supplierCompany:id,name,email,phone,website,address', 'items.product:id,name'])
            ->where('clinic_id', $this->currentClinicId())
            ->find($orderId);

        if (! $order) {
            return ServiceResult::error('Material invoice not found.', null, null, 404);
        }

        return ServiceResult::success($this->mapMaterialInvoice($order), 'Material invoice fetched successfully');
    }

    public function materialInvoiceContact(int $orderId): array
    {
        $order = Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->with('supplierCompany:id,name,email,phone,website,address')
            ->where('clinic_id', $this->currentClinicId())
            ->find($orderId);

        if (! $order || ! $order->supplierCompany) {
            return ServiceResult::error('Material company not found.', null, null, 404);
        }

        return ServiceResult::success([
            'communication_url' => '/clinic/communication?company_id=' . $order->supplierCompany->id,
            'company' => [
                'id' => $order->supplierCompany->id,
                'name' => $order->supplierCompany->name,
                'email' => $order->supplierCompany->email,
                'phone' => $order->supplierCompany->phone,
                'website' => $order->supplierCompany->website,
                'address' => $order->supplierCompany->address,
            ],
        ], 'Material company contact fetched successfully');
    }

    public function doctorEarnings(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rangeFilters = $this->applyDateRangeFilter($filters);
        $caseType = $this->normalizeCaseType($filters['case_type'] ?? null);

        $payments = \App\Models\ClinicPayment::query()
            ->with(['invoice.doctor:id,name,commission_rates', 'invoice.patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereHas('invoice', fn ($query) => $query->whereNotNull('doctor_user_id'))
            ->when($filters['doctor_id'] ?? $filters['doctor'] ?? null, fn ($query, int $doctorId) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('doctor_user_id', $doctorId)))
            ->when($caseType, fn ($query, string $type) => $query->where('method', $type))
            ->when($rangeFilters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($rangeFilters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '<=', $date))
            ->latest('paid_at')
            ->get();

        $items = $payments->map(function ($payment) {
            $invoice = $payment->invoice;
            $doctor = $invoice?->doctor;
            $method = $this->normalizeCaseType($payment->method ?? $invoice?->payment_method) ?? 'Cash';
            $rate = $this->doctorCommissionRate($doctor, $method);

            return [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'doctor_id' => $doctor?->id,
                'doctorName' => $doctor?->name,
                'doctor_name' => $doctor?->name,
                'patient_name' => $invoice?->patient?->user?->name,
                'date' => optional($payment->paid_at)?->toDateString(),
                'caseType' => $method,
                'payment_method' => $method,
                'amount' => (float) $payment->amount,
                'commission_rate' => $rate,
                'earning' => round((float) $payment->amount * ($rate / 100), 2),
            ];
        })->values();

        $aggregated = $items->groupBy('doctor_id')->map(function ($rows) {
            return [
                'doctor_id' => $rows->first()['doctor_id'],
                'doctorName' => $rows->first()['doctorName'],
                'doctor_name' => $rows->first()['doctorName'],
                'totalCases' => $rows->pluck('invoice_id')->unique()->count(),
                'totalValue' => round((float) $rows->sum('amount'), 2),
                'totalEarnings' => round((float) $rows->sum('earning'), 2),
                'commissionRates' => $rows->pluck('commission_rate')->unique()->values()->all(),
                'caseTypes' => $rows->pluck('caseType')->unique()->values()->all(),
            ];
        })->values();

        $monthly = $items->groupBy(fn ($row) => Carbon::parse($row['date'])->format('Y-m'))->map(fn ($rows, $month) => [
            'month' => $month,
            'doctors' => $rows->groupBy('doctorName')->map(fn ($doctorRows, $doctorName) => [
                'doctorName' => $doctorName,
                'totalEarnings' => round((float) $doctorRows->sum('earning'), 2),
                'totalValue' => round((float) $doctorRows->sum('amount'), 2),
            ])->values()->all(),
        ])->values()->all();

        $caseTypes = $items->groupBy('caseType')->map(fn ($rows, $type) => [
            'caseType' => $type,
            'totalValue' => round((float) $rows->sum('amount'), 2),
            'totalEarnings' => round((float) $rows->sum('earning'), 2),
        ])->values()->all();

        return ServiceResult::success([
            'items' => $aggregated->all(),
            'details' => $items->all(),
            'summary' => [
                'total_collected' => round((float) $items->sum('amount'), 2),
                'total_earnings' => round((float) $items->sum('earning'), 2),
            ],
            'filters' => [
                'date_ranges' => ['All Time', 'This Week', 'This Month', 'This Year'],
                'doctors' => $this->clinicDoctors()['data'] ?? [],
                'case_types' => ['All Case Types', 'Cash', 'Insurance'],
            ],
            'analytics' => [
                'monthlyEarningsByDoctor' => $monthly,
                'earningsByCaseType' => $caseTypes,
                'empty_message' => $items->isEmpty() ? 'No earnings data for selected filters.' : null,
                'no_data_message' => $items->isEmpty() ? 'No data available.' : null,
            ],
            'download_urls' => [
                'pdf' => $this->billingDownloadUrl($clinicId, 'doctor_earnings', 'pdf', null, $filters),
                'excel' => $this->billingDownloadUrl($clinicId, 'doctor_earnings', 'excel', null, $filters),
            ],
        ], 'Doctor earnings fetched successfully');
    }

    public function extractAccounts(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rangeFilters = $this->applyDateRangeFilter($filters);
        $doctorId = $filters['doctor_id'] ?? $filters['doctor'] ?? null;
        $doctor = $doctorId ? User::query()->where('clinic_id', $clinicId)->find($doctorId) : null;

        $invoices = ClinicInvoice::query()
            ->with(['patient.user:id,name', 'doctor:id,name', 'items'])
            ->where('clinic_id', $clinicId)
            ->when($doctorId, fn ($query, int $id) => $query->where('doctor_user_id', $id))
            ->when($rangeFilters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($rangeFilters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('issued_at', '<=', $date))
            ->latest('issued_at')
            ->get();

        $rows = $invoices->map(fn (ClinicInvoice $invoice) => [
            'invoiceId' => $invoice->invoice_number,
            'invoice_id' => $invoice->id,
            'invoice_url' => '/clinic/billing/invoices/' . $invoice->id,
            'patient' => $invoice->patient?->user?->name,
            'date' => optional($invoice->issued_at)?->toDateString(),
            'services' => $invoice->items->pluck('description')->filter()->implode(', '),
            'amount' => (float) $invoice->total,
        ])->values();

        $deliveryLog = BillingReportDelivery::query()
            ->where('clinic_id', $clinicId)
            ->where('report_type', 'extract_accounts')
            ->latest('sent_at')
            ->get()
            ->map(fn (BillingReportDelivery $delivery) => [
                'sentAt' => optional($delivery->sent_at)->toDateTimeString(),
                'sentTo' => $delivery->sent_to,
                'channel' => $delivery->channel,
                'status' => $delivery->status,
            ])
            ->values()
            ->all();

        return ServiceResult::success([
            'title' => 'Found ' . $rows->count() . ' invoices for ' . ($doctor?->name ?? 'All Doctors'),
            'filters' => [
                'date_from' => $rangeFilters['date_from'] ?? null,
                'date_to' => $rangeFilters['date_to'] ?? null,
                'quick_ranges' => ['This Week', 'This Month', 'This Year'],
                'doctors' => $this->clinicDoctors()['data'] ?? [],
            ],
            'items' => $rows->all(),
            'total' => round((float) $rows->sum('amount'), 2),
            'deliveryLog' => $deliveryLog,
            'delivery_empty_message' => $deliveryLog === [] ? 'No reports have been sent yet.' : null,
        ], 'Extract accounts fetched successfully');
    }

    public function sendExtractAccountWhatsApp(array $data): array
    {
        $extract = $this->sendExtractAccountsReport([
            'doctor_id' => $data['doctor_id'],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'channel' => 'WhatsApp',
            'sent_to' => User::query()->where('clinic_id', $this->currentClinicId())->find($data['doctor_id'])?->phone ?? '',
        ]);
        return $extract;
    }

    public function sendDoctorEarnings(int $doctorId, array $filters = []): array
    {
        $doctor = User::query()
            ->where('clinic_id', $this->currentClinicId())
            ->role('doctor')
            ->find($doctorId);

        if (! $doctor) {
            return ServiceResult::error('Doctor not found.', null, null, 404);
        }

        $earnings = $this->doctorEarnings($filters + ['doctor_id' => $doctorId]);
        if (! $earnings['success']) {
            return $earnings;
        }

        $row = collect($earnings['data']['items'])->firstWhere('doctor_id', $doctorId);
        $delivery = BillingReportDelivery::query()->create([
            'clinic_id' => $this->currentClinicId(),
            'doctor_user_id' => $doctorId,
            'report_type' => 'doctor_earnings',
            'sent_to' => $filters['sent_to'] ?? $doctor->email ?? $doctor->phone ?? $doctor->name,
            'channel' => $filters['channel'] ?? 'InApp',
            'status' => 'Sent',
            'filters' => $filters,
            'payload' => $row,
            'sent_at' => now(),
        ]);

        return ServiceResult::success([
            'sent' => true,
            'delivery_id' => $delivery->id,
            'doctor_report' => $row,
        ], 'Doctor earnings report sent successfully');
    }

    public function sendExtractAccountsReport(array $data): array
    {
        $extract = $this->extractAccounts($data);
        if (! $extract['success']) {
            return $extract;
        }

        if (($data['channel'] ?? 'WhatsApp') === 'WhatsApp' && empty($data['sent_to'])) {
            return ServiceResult::error('Recipient is required.', null, ['sent_to' => ['Recipient is required.']], 422);
        }

        $delivery = BillingReportDelivery::query()->create([
            'clinic_id' => $this->currentClinicId(),
            'doctor_user_id' => $data['doctor_id'] ?? null,
            'report_type' => 'extract_accounts',
            'sent_to' => $data['sent_to'],
            'channel' => $data['channel'] ?? 'WhatsApp',
            'status' => 'Sent',
            'filters' => $data,
            'payload' => $extract['data'],
            'sent_at' => now(),
        ]);

        return ServiceResult::success([
            'sent' => true,
            'delivery_id' => $delivery->id,
            'report' => $extract['data'],
        ], 'Extract account report sent successfully');
    }

    public function doctorEarningsDownloadUrl(array $filters): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success([
            'download_url' => $this->billingDownloadUrl($clinicId, 'doctor_earnings', $filters['format'], null, $filters),
            'format' => $filters['format'],
        ], 'Doctor earnings download link generated successfully');
    }

    public function signedBillingDownload(array $data): array
    {
        $clinicId = (int) $data['clinic_id'];
        $type = $data['type'];
        $format = $data['format'];

        if ($type === 'lab_invoice') {
            $invoice = LabInvoice::query()->with(['lab:id,name', 'items'])->where('clinic_id', $clinicId)->find($data['id'] ?? null);
            if (! $invoice) {
                return ServiceResult::error('Lab invoice not found.', null, null, 404);
            }

            return ServiceResult::success($this->downloadPayload('lab-invoice-' . $invoice->id, $format, [
                ['Invoice', $invoice->invoice_number],
                ['Lab', $invoice->lab?->name],
                ['Amount', (float) $invoice->total_amount],
                ['Status', $this->billingStatus($invoice->status)],
            ]), 'Lab invoice download generated successfully');
        }

        if ($type === 'material_invoice') {
            $order = Order::query()->withoutGlobalScope(\App\Scopes\CompanyScope::class)->with(['supplierCompany:id,name', 'items.product:id,name'])->where('clinic_id', $clinicId)->find($data['id'] ?? null);
            if (! $order) {
                return ServiceResult::error('Material invoice not found.', null, null, 404);
            }

            return ServiceResult::success($this->downloadPayload('material-invoice-' . $order->id, $format, [
                ['Invoice', $order->order_code],
                ['Company', $order->supplierCompany?->name],
                ['Amount', (float) ($order->total_amount ?? $order->amount_total)],
                ['Status', $this->billingStatus($order->payment_status)],
            ]), 'Material invoice download generated successfully');
        }

        $filters = array_filter($data, fn ($value) => $value !== null && $value !== '');
        $currentUser = auth()->user();
        auth()->onceUsingId(User::query()->where('clinic_id', $clinicId)->value('id') ?? $currentUser?->id);
        $earnings = $this->doctorEarnings($filters);
        if (! $earnings['success']) {
            return $earnings;
        }

        $rows = collect($earnings['data']['items'])->map(fn ($row) => [
            $row['doctorName'],
            $row['totalCases'],
            $row['totalValue'],
            $row['totalEarnings'],
            implode(', ', $row['commissionRates']),
            implode(', ', $row['caseTypes']),
        ])->prepend(['Doctor', 'Total Cases', 'Total Value', 'Total Earnings', 'Commission Rates', 'Case Types'])->all();

        return ServiceResult::success($this->downloadPayload('doctor-earnings', $format, $rows), 'Doctor earnings download generated successfully');
    }

    public function exportProfitLoss(array $filters = []): array
    {
        $chart = $this->profitLossChart($filters);
        if (! $chart['success']) {
            return $chart;
        }

        if (! class_exists(Pdf::class)) {
            return ServiceResult::error(
                'PDF generator is not installed. Install barryvdh/laravel-dompdf and enable required PHP extensions.',
                null,
                ['pdf' => ['barryvdh/laravel-dompdf is required for Profit & Loss PDF export.']],
                500
            );
        }

        return ServiceResult::success([
            'filename' => 'profit-loss-' . now()->format('YmdHis') . '.pdf',
            'content_type' => 'application/pdf',
            'content' => base64_encode($this->renderProfitLossPdf($chart['data'], $filters)),
        ], 'Profit and loss export generated successfully');
    }

    public function profitLossPdfPayload(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return $this->profitLossPdfPayloadForClinic($clinicId, $filters);
    }

    public function profitLossPdfPayloadForClinic(int $clinicId, array $filters = []): array
    {
        if (! Clinic::query()->whereKey($clinicId)->exists()) {
            return ServiceResult::error('Clinic was not found.', null, null, 404);
        }

        $chart = $this->profitLossChartForClinic($clinicId, $filters);
        if (! $chart['success']) {
            return $chart;
        }

        return ServiceResult::success([
            'filename' => self::PROFIT_LOSS_DOWNLOAD_FILENAME,
            'content_type' => 'application/pdf',
            'content' => $this->renderProfitLossPdf($chart['data'], $filters),
        ], 'Profit and loss PDF generated successfully');
    }

    public function profitLossDownloadUrl(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        return ServiceResult::success(
            $this->profitLossDownloadUrlData($clinicId, $filters),
            'Profit and loss download link generated successfully'
        );
    }

    public function sendProfitLossWhatsApp(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $summary = $this->repository->profitLossSummary($clinicId, $data);
        $message = sprintf(
            'Profit & Loss summary: revenue %.2f, expenses %.2f, profit %.2f.',
            $summary['revenue'],
            $summary['expenses'],
            $summary['profit']
        );

        $providerResult = $this->whatsAppProvider->sendMessage($data['to'], $message);

        WhatsappMessage::query()->create([
            'clinic_id' => $clinicId,
            'patient_phone' => $data['to'],
            'message' => $message,
            'reply' => null,
            'intent' => 'profit_loss_report',
            'created_at' => now(),
        ]);

        return ServiceResult::success([
            'queued' => (bool) ($providerResult['success'] ?? false),
            'to' => $data['to'],
            'message' => $message,
            'summary' => $summary,
        ], 'Profit and loss WhatsApp message processed successfully');
    }

    private function profitLossDownloadUrlData(int $clinicId, array $filters = []): array
    {
        $expiresAt = now()->addMinutes(self::PROFIT_LOSS_DOWNLOAD_TTL_MINUTES);
        $params = [
            'clinic_id' => $clinicId,
        ];

        foreach (['date_from', 'date_to', 'group_by'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $params[$key] = $filters[$key];
            }
        }

        return [
            'download_url' => URL::temporarySignedRoute(
                self::PROFIT_LOSS_DOWNLOAD_ROUTE,
                $expiresAt,
                $params
            ),
            'expires_at' => $expiresAt->toISOString(),
            'filename' => self::PROFIT_LOSS_DOWNLOAD_FILENAME,
        ];
    }

    private function mapLabInvoice(LabInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'labName' => $invoice->lab?->name,
            'lab_name' => $invoice->lab?->name,
            'syncedAt' => optional($invoice->created_at)->toISOString(),
            'received_on' => optional($invoice->created_at)->toDateString(),
            'dueDate' => optional($invoice->due_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'amount' => (float) $invoice->total_amount,
            'status' => $this->billingStatus($invoice->status),
            'view_url' => '/clinic/billing/lab-invoices/' . $invoice->id,
        ];
    }

    private function mapMaterialInvoice(Order $order): array
    {
        return [
            'id' => $order->id,
            'company' => $order->supplierCompany?->name,
            'totalAmount' => (float) ($order->total_amount ?? $order->amount_total),
            'paymentMethod' => strtolower((string) ($order->payment_method ?? 'pay_later')),
            'status' => $this->billingStatus($order->payment_status ?: $order->status),
            'orderSummary' => [
                'items' => $order->items->map(fn ($item) => [
                    'productName' => $item->product?->name ?? $item->item_name,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->unit_price,
                ])->values()->all(),
            ],
            'viewPdfUrl' => $this->billingDownloadUrl($order->clinic_id, 'material_invoice', 'pdf', $order->id),
            'downloadUrl' => $this->billingDownloadUrl($order->clinic_id, 'material_invoice', 'pdf', $order->id),
            'contact' => [
                'communication_url' => '/clinic/communication?company_id=' . $order->supplierCompany?->id,
                'email' => $order->supplierCompany?->email,
                'phone' => $order->supplierCompany?->phone,
            ],
        ];
    }

    private function billingStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'paid', 'completed' => 'Paid',
            'overdue' => 'Overdue',
            'disputed' => 'Disputed',
            'rejected', 'cancelled' => 'Disputed',
            default => 'Pending',
        };
    }

    private function applyDateRangeFilter(array $filters): array
    {
        $range = strtolower(str_replace(' ', '_', (string) ($filters['date_range'] ?? '')));

        return match ($range) {
            'this_week' => ['date_from' => now()->startOfWeek()->toDateString(), 'date_to' => now()->endOfWeek()->toDateString()],
            'this_month' => ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()],
            'this_year' => ['date_from' => now()->startOfYear()->toDateString(), 'date_to' => now()->endOfYear()->toDateString()],
            default => [
                'date_from' => $filters['date_from'] ?? $filters['start_date'] ?? null,
                'date_to' => $filters['date_to'] ?? $filters['end_date'] ?? null,
            ],
        };
    }

    private function normalizeCaseType(?string $caseType): ?string
    {
        return match (strtolower((string) $caseType)) {
            'cash' => 'Cash',
            'insurance' => 'Insurance',
            default => null,
        };
    }

    private function billingDownloadUrl(int $clinicId, string $type, string $format, ?int $id = null, array $filters = []): string
    {
        $params = array_filter([
            'clinic_id' => $clinicId,
            'type' => $type,
            'format' => $format,
            'id' => $id,
            'date_range' => $filters['date_range'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'doctor_id' => $filters['doctor_id'] ?? $filters['doctor'] ?? null,
            'case_type' => $filters['case_type'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return URL::temporarySignedRoute('clinic.billing.download.signed', now()->addMinutes(30), $params);
    }

    private function downloadPayload(string $baseName, string $format, array $rows): array
    {
        if ($format === 'excel') {
            $content = collect($rows)
                ->map(fn ($row) => collect($row)->map(fn ($cell) => '"' . str_replace('"', '""', (string) $cell) . '"')->implode(','))
                ->implode("\n");

            return [
                'filename' => $baseName . '.csv',
                'content_type' => 'text/csv',
                'content' => $content,
            ];
        }

        $htmlRows = collect($rows)->map(fn ($row) => '<tr>' . collect($row)->map(fn ($cell) => '<td>' . e((string) $cell) . '</td>')->implode('') . '</tr>')->implode('');

        return [
            'filename' => $baseName . '.pdf',
            'content_type' => 'application/pdf',
            'content' => Pdf::loadHTML('<html><body><table border="1" cellpadding="6" cellspacing="0">' . $htmlRows . '</table></body></html>')->setPaper('a4')->output(),
        ];
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }

    private function generateInvoiceNumber(): string
    {
        do {
            $number = 'INV-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (ClinicInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }

    private function resolveInvoiceStatus(float $total, float $paid, ?string $dueDate): string
    {
        if ($total > 0 && $paid >= $total) {
            return 'paid';
        }

        if ($paid > 0 && $paid < $total) {
            return 'partial';
        }

        if ($dueDate && now()->toDateString() > $dueDate && $paid < $total) {
            return 'overdue';
        }

        return 'pending';
    }

    private function syncInvoiceStatuses(int $clinicId): void
    {
        ClinicInvoice::query()
            ->where('clinic_id', $clinicId)
            ->get()
            ->each(function (ClinicInvoice $invoice) {
                $status = $this->resolveInvoiceStatus(
                    (float) $invoice->total,
                    (float) $invoice->paid,
                    optional($invoice->due_date)?->toDateString()
                );

                if ($invoice->status !== $status || (float) $invoice->remaining !== max(round((float) $invoice->total - (float) $invoice->paid, 2), 0)) {
                    $invoice->update([
                        'remaining' => max(round((float) $invoice->total - (float) $invoice->paid, 2), 0),
                        'status' => $status,
                    ]);
                }
            });
    }

    private function buildProfitLossSeries(int $clinicId, Carbon $from, Carbon $to, string $groupBy): array
    {
        $interval = match ($groupBy) {
            'day' => '1 day',
            'week' => '1 week',
            default => '1 month',
        };

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $interval, $to->copy()->endOfDay()))
            ->map(function (Carbon $periodStart) use ($clinicId, $groupBy, $to) {
                $periodEnd = match ($groupBy) {
                    'day' => $periodStart->copy()->endOfDay(),
                    'week' => $periodStart->copy()->endOfWeek(),
                    default => $periodStart->copy()->endOfMonth(),
                };

                if ($periodEnd->greaterThan($to)) {
                    $periodEnd = $to->copy();
                }

                $summary = $this->repository->profitLossSummary($clinicId, [
                    'date_from' => $periodStart->toDateString(),
                    'date_to' => $periodEnd->toDateString(),
                ]);

                return [
                    'period' => match ($groupBy) {
                        'day' => $periodStart->format('Y-m-d'),
                        'week' => $periodStart->format('o-\WW'),
                        default => $periodStart->format('Y-m'),
                    },
                    ...$summary,
                ];
            })
            ->values()
            ->all();
    }

    private function billingCard(string $label, string $key, float $current, float $previous): array
    {
        $change = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 2)
            : ($current > 0 ? 100.0 : 0.0);

        return [
            'label' => $label,
            'key' => $key,
            'value' => round($current, 2),
            'change_percentage' => $change,
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    private function invoiceSum(int $clinicId, string $column, array $range): float
    {
        return round((float) ClinicInvoice::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('issued_at', [$range[0]->toDateString(), $range[1]->toDateString()])
            ->sum($column), 2);
    }

    private function paymentSum(int $clinicId, array $range): float
    {
        return round((float) \App\Models\ClinicPayment::query()
            ->where('clinic_id', $clinicId)
            ->whereBetween('paid_at', [$range[0]->startOfDay(), $range[1]->endOfDay()])
            ->sum('amount'), 2);
    }

    private function profitLossBreakdown(int $clinicId, array $filters): array
    {
        $from = $filters['date_from'] ?? $filters['start_date'] ?? null;
        $to = $filters['date_to'] ?? $filters['end_date'] ?? null;

        $revenue = (float) \App\Models\ClinicPayment::query()
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('paid_at', '<=', $date))
            ->sum('amount');

        $operatingExpenses = (float) \App\Models\ClinicExpense::query()
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($query, string $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('expense_date', '<=', $date))
            ->sum('amount');

        $costOfGoods = (float) LabInvoice::query()
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->sum('total_amount');

        $costOfGoods += (float) Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->where('clinic_id', $clinicId)
            ->when($from, fn ($query, string $date) => $query->whereDate('order_date', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('order_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(COALESCE(total_amount, amount_total, 0)), 0) as total')
            ->value('total');

        return [
            'total_revenue' => round($revenue, 2),
            'cost_of_goods' => round($costOfGoods, 2),
            'operating_expenses' => round($operatingExpenses, 2),
            'net_profit' => round($revenue - $costOfGoods - $operatingExpenses, 2),
        ];
    }

    private function doctorCommissionRate(?User $doctor, ?string $method): float
    {
        $rates = is_array($doctor?->commission_rates) ? $doctor->commission_rates : [];
        $key = strtolower((string) $method) === 'insurance' ? 'insurance_commission' : 'cash_commission';

        return (float) ($rates[$key] ?? $rates['default'] ?? 0);
    }

    private function pagination($rows): array
    {
        return [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ];
    }

    private function renderProfitLossPdf(array $report, array $filters): string
    {
        $clinic = Clinic::query()->find($this->currentClinicId());
        $from = $filters['date_from'] ?? now()->subMonths(5)->startOfMonth()->toDateString();
        $to = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        return Pdf::loadView('pdf.profit-loss', [
            'clinic' => $clinic,
            'from' => $from,
            'to' => $to,
            'groupBy' => $report['group_by'] ?? 'month',
            'summary' => $report['summary'] ?? [],
            'series' => $report['series'] ?? [],
        ])
            ->setPaper('a4')
            ->output();
    }
}
