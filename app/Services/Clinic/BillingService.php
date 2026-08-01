<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicExpenseCategoryResource;
use App\Http\Resources\Clinic\ClinicExpenseResource;
use App\Http\Resources\Clinic\ClinicInvoiceResource;
use App\Http\Resources\Clinic\ClinicPaymentResource;
use App\Models\ClinicAppointment;
use App\Models\Clinic;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\LabInvoice;
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
use Throwable;

class BillingService
{
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

        if (! empty($data['assigned_to_user_id'])) {
            $assignedUser = User::query()->where('clinic_id', $clinicId)->find($data['assigned_to_user_id']);
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
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
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
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'attachment_path' => $attachment ? $attachment->store('clinic/expenses', 'public') : null,
        ], static fn ($value) => $value !== null);

        if (isset($payload['expense_category_id']) && ! $this->repository->findExpenseCategory($clinicId, (int) $payload['expense_category_id'])) {
            return ServiceResult::error('Expense category not found.', null, ['expense_category_id' => ['Expense category not found.']], 422);
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

        if ((float) $invoice->remaining <= 0) {
            return ServiceResult::error('Invoice is already fully paid.', null, ['remaining' => ['Invoice has no remaining balance.']], 422);
        }

        if ($invoice->reminder_sent) {
            return ServiceResult::success([
                'already_sent' => true,
                'invoice_id' => $invoice->id,
                'reminder_sent' => true,
                'reminder_sent_at' => optional($invoice->reminder_sent_at)?->toISOString(),
                'remaining' => (float) $invoice->remaining,
            ], 'Invoice reminder was already sent');
        }

        $phone = $invoice->patient?->user?->phone ?: $invoice->patient?->phone;
        if (! $phone) {
            return ServiceResult::error('Patient phone not found.', null, ['phone' => ['Patient phone not found.']], 422);
        }

        $message = sprintf(
            'Reminder: invoice %s has a remaining balance of %.2f. Please contact the clinic to complete payment.',
            $invoice->invoice_number,
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

        return ServiceResult::success(
            $this->repository->profitLossSummary($clinicId, $filters),
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
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? $filters['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['date_to'] ?? $filters['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('issue_date', '<=', $date))
            ->latest('issue_date')
            ->paginate($filters['per_page'] ?? 15);

        return ServiceResult::success([
            'items' => collect($rows->items())->map(fn (LabInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_number,
                'lab_name' => $invoice->lab?->name,
                'doctor_name' => $invoice->doctor?->user?->name,
                'date' => optional($invoice->issue_date)?->toDateString(),
                'due_date' => optional($invoice->due_date)?->toDateString(),
                'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->paid_amount,
                'remaining' => (float) $invoice->remaining_amount,
                'status' => $invoice->status,
            ])->values()->all(),
            'pagination' => $this->pagination($rows),
        ], 'Lab invoices fetched successfully');
    }

    public function materialInvoices(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = Order::query()
            ->withoutGlobalScope(\App\Scopes\CompanyScope::class)
            ->with(['supplierCompany:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['date_from'] ?? $filters['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('order_date', '>=', $date))
            ->when($filters['date_to'] ?? $filters['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('order_date', '<=', $date))
            ->latest('order_date')
            ->paginate($filters['per_page'] ?? 15);

        return ServiceResult::success([
            'items' => collect($rows->items())->map(fn (Order $order) => [
                'id' => $order->id,
                'invoice_id' => $order->order_code,
                'supplier_name' => $order->supplierCompany?->name,
                'date' => optional($order->order_date)?->toDateString(),
                'total' => (float) ($order->total_amount ?? $order->amount_total),
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
            ])->values()->all(),
            'pagination' => $this->pagination($rows),
        ], 'Material invoices fetched successfully');
    }

    public function doctorEarnings(array $filters = []): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $payments = \App\Models\ClinicPayment::query()
            ->with(['invoice.doctor:id,name,commission_rates', 'invoice.patient.user:id,name'])
            ->where('clinic_id', $clinicId)
            ->whereHas('invoice', fn ($query) => $query->whereNotNull('doctor_user_id'))
            ->when($filters['doctor_id'] ?? $filters['doctor'] ?? null, fn ($query, int $doctorId) => $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('doctor_user_id', $doctorId)))
            ->when($filters['payment_method'] ?? null, fn ($query, string $method) => $query->where('method', $method))
            ->when($filters['date_from'] ?? $filters['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? $filters['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('paid_at', '<=', $date))
            ->latest('paid_at')
            ->get();

        $items = $payments->map(function ($payment) {
            $invoice = $payment->invoice;
            $doctor = $invoice?->doctor;
            $method = $payment->method ?? $invoice?->payment_method ?? 'Cash';
            $rate = $this->doctorCommissionRate($doctor, $method);

            return [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'doctor_id' => $doctor?->id,
                'doctor_name' => $doctor?->name,
                'patient_name' => $invoice?->patient?->user?->name,
                'date' => optional($payment->paid_at)?->toDateString(),
                'payment_method' => $method,
                'amount' => (float) $payment->amount,
                'commission_rate' => $rate,
                'earning' => round((float) $payment->amount * ($rate / 100), 2),
            ];
        })->values();

        return ServiceResult::success([
            'items' => $items->all(),
            'summary' => [
                'total_collected' => round((float) $items->sum('amount'), 2),
                'total_earnings' => round((float) $items->sum('earning'), 2),
            ],
        ], 'Doctor earnings fetched successfully');
    }

    public function extractAccounts(array $filters = []): array
    {
        $earnings = $this->doctorEarnings($filters);
        if (! $earnings['success']) {
            return $earnings;
        }

        $grouped = collect($earnings['data']['items'])
            ->groupBy('doctor_id')
            ->map(fn ($rows, $doctorId) => [
                'doctor_id' => (int) $doctorId,
                'doctor_name' => $rows->first()['doctor_name'],
                'total_collected' => round((float) $rows->sum('amount'), 2),
                'total_earnings' => round((float) $rows->sum('earning'), 2),
                'payments_count' => $rows->count(),
            ])
            ->values()
            ->all();

        return ServiceResult::success([
            'accounts' => $grouped,
            'details' => $earnings['data']['items'],
        ], 'Extract accounts fetched successfully');
    }

    public function sendExtractAccountWhatsApp(array $data): array
    {
        $filters = $data;
        $extract = $this->extractAccounts($filters);
        if (! $extract['success']) {
            return $extract;
        }

        $doctor = User::query()
            ->where('clinic_id', $this->currentClinicId())
            ->role('doctor')
            ->find($data['doctor_id']);

        if (! $doctor || ! $doctor->phone) {
            return ServiceResult::error('Doctor phone not found.', null, ['doctor_id' => ['Doctor phone not found.']], 422);
        }

        $account = collect($extract['data']['accounts'])->firstWhere('doctor_id', $doctor->id);
        $message = sprintf(
            'Doctor account extract: collected %.2f, earnings %.2f, payments %d.',
            (float) ($account['total_collected'] ?? 0),
            (float) ($account['total_earnings'] ?? 0),
            (int) ($account['payments_count'] ?? 0)
        );

        $providerResult = $this->whatsAppProvider->sendMessage($doctor->phone, $message);

        return ServiceResult::success([
            'queued' => (bool) ($providerResult['success'] ?? false),
            'to' => $doctor->phone,
            'message' => $message,
            'account' => $account,
        ], 'Extract account WhatsApp message processed successfully');
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
        $chart = $this->profitLossChart($filters);
        if (! $chart['success']) {
            return $chart;
        }

        return ServiceResult::success([
            'filename' => 'profit-loss-' . now()->format('YmdHis') . '.pdf',
            'content_type' => 'application/pdf',
            'content' => $this->renderProfitLossPdf($chart['data'], $filters),
        ], 'Profit and loss PDF generated successfully');
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
