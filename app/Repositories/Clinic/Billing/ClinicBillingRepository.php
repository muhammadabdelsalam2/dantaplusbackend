<?php

namespace App\Repositories\Clinic\Billing;

use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\ClinicInvoiceItem;
use App\Models\ClinicPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicBillingRepository implements ClinicBillingRepositoryInterface
{
    public function paginateInvoices(int $clinicId, array $filters): LengthAwarePaginator
    {
        return ClinicInvoice::query()
            ->with(['patient.user:id,name', 'doctor:id,name', 'items', 'payments.recorder:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['patient_id'] ?? null, fn (Builder $query, int $patientId) => $query->where('patient_id', $patientId))
            ->when($filters['doctor_id'] ?? null, fn (Builder $query, int $doctorId) => $query->where('doctor_user_id', $doctorId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findInvoice(int $clinicId, int $invoiceId): ?ClinicInvoice
    {
        return ClinicInvoice::query()
            ->with(['patient.user:id,name', 'doctor:id,name', 'items', 'payments.recorder:id,name'])
            ->where('clinic_id', $clinicId)
            ->find($invoiceId);
    }

    public function createInvoice(array $data): ClinicInvoice
    {
        return ClinicInvoice::query()->create($data);
    }

    public function updateInvoice(ClinicInvoice $invoice, array $data): ClinicInvoice
    {
        $invoice->update($data);

        return $invoice->refresh()->load(['patient.user:id,name', 'doctor:id,name', 'items', 'payments.recorder:id,name']);
    }

    public function createInvoiceItem(array $data): ClinicInvoiceItem
    {
        return ClinicInvoiceItem::query()->create($data);
    }

    public function createPayment(array $data): ClinicPayment
    {
        return ClinicPayment::query()->create($data);
    }

    public function paginatePayments(int $clinicId, array $filters): LengthAwarePaginator
    {
        return ClinicPayment::query()
            ->with(['invoice.patient.user:id,name', 'invoice.doctor:id,name', 'recorder:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['invoice_id'] ?? null, fn (Builder $query, int $invoiceId) => $query->where('clinic_invoice_id', $invoiceId))
            ->when($filters['doctor_id'] ?? null, fn (Builder $query, int $doctorId) => $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('doctor_user_id', $doctorId)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '<=', $date))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function paginateExpenses(int $clinicId, array $filters): LengthAwarePaginator
    {
        return ClinicExpense::query()
            ->with(['category:id,name', 'assignee:id,name'])
            ->where('clinic_id', $clinicId)
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, int $userId) => $query->where('assigned_to_user_id', $userId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '<=', $date))
            ->latest('expense_date')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function createExpense(array $data): ClinicExpense
    {
        return ClinicExpense::query()->create($data);
    }

    public function expenseSummary(int $clinicId, array $filters): array
    {
        $baseQuery = ClinicExpense::query()
            ->with('category:id,name')
            ->where('clinic_id', $clinicId)
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '<=', $date));

        $topCategory = (clone $baseQuery)
            ->selectRaw('expense_category_id, SUM(amount) as total_amount')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->first();

        $monthlyTotal = (clone $baseQuery)
            ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $previousMonthlyTotal = (clone $baseQuery)
            ->whereBetween('expense_date', [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->sum('amount');

        $trend = $previousMonthlyTotal > 0
            ? round((($monthlyTotal - $previousMonthlyTotal) / $previousMonthlyTotal) * 100, 2)
            : ($monthlyTotal > 0 ? 100.0 : 0.0);

        return [
            'total_expenses' => round((float) (clone $baseQuery)->sum('amount'), 2),
            'top_category' => $topCategory
                ? [
                    'id' => $topCategory->expense_category_id,
                    'name' => optional($topCategory->category)->name,
                    'amount' => round((float) $topCategory->total_amount, 2),
                ]
                : null,
            'monthly_trend_percent' => $trend,
        ];
    }

    public function profitLossSummary(int $clinicId, array $filters): array
    {
        $revenueQuery = ClinicPayment::query()
            ->where('clinic_id', $clinicId)
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '<=', $date));

        $expenseQuery = ClinicExpense::query()
            ->where('clinic_id', $clinicId)
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('expense_date', '<=', $date));

        $revenue = (float) $revenueQuery->sum('amount');
        $expenses = (float) $expenseQuery->sum('amount');

        return [
            'revenue' => round($revenue, 2),
            'expenses' => round($expenses, 2),
            'profit' => round($revenue - $expenses, 2),
        ];
    }

    public function listExpenseCategories(int $clinicId): Collection
    {
        return ClinicExpenseCategory::query()
            ->where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get();
    }

    public function findExpenseCategory(int $clinicId, int $categoryId): ?ClinicExpenseCategory
    {
        return ClinicExpenseCategory::query()
            ->where('clinic_id', $clinicId)
            ->find($categoryId);
    }

    public function firstOrCreateExpenseCategory(array $attributes, array $values = []): ClinicExpenseCategory
    {
        return ClinicExpenseCategory::query()->firstOrCreate($attributes, $values);
    }
}
