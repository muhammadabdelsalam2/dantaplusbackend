<?php

namespace App\Services\Clinic;

use App\Http\Resources\Clinic\ClinicExpenseCategoryResource;
use App\Http\Resources\Clinic\ClinicExpenseResource;
use App\Http\Resources\Clinic\ClinicInvoiceResource;
use App\Http\Resources\Clinic\ClinicPaymentResource;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\Patient;
use App\Models\User;
use App\Repositories\Clinic\Billing\ClinicBillingRepositoryInterface;
use App\Support\ServiceResult;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingService
{
    public function __construct(private ClinicBillingRepositoryInterface $repository)
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

    public function showInvoice(int $id): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $this->syncInvoiceStatuses($clinicId);

        $invoice = $this->repository->findInvoice($clinicId, $id);
        if (! $invoice) {
            return ServiceResult::error('Invoice not found.', null, null, 404);
        }

        return ServiceResult::success((new ClinicInvoiceResource($invoice))->resolve(), 'Invoice fetched successfully');
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

        return $this->showInvoice($invoice->id);
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

        if ((float) $data['amount'] > (float) $invoice->remaining) {
            return ServiceResult::error('Payment amount exceeds remaining balance.', null, ['amount' => ['Payment amount exceeds remaining balance.']], 422);
        }

        $payment = DB::transaction(function () use ($invoice, $data) {
            $payment = $this->repository->createPayment([
                'clinic_invoice_id' => $invoice->id,
                'clinic_id' => $invoice->clinic_id,
                'recorded_by' => auth()->id(),
                'amount' => $data['amount'],
                'method' => $data['method'] ?? null,
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

    public function createExpense(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $category = $this->repository->findExpenseCategory($clinicId, (int) $data['expense_category_id']);
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
            'expense_date' => $data['expense_date'],
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->load(['category:id,name', 'assignee:id,name']);

        return ServiceResult::success((new ClinicExpenseResource($expense))->resolve(), 'Expense created successfully', 201);
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
}
