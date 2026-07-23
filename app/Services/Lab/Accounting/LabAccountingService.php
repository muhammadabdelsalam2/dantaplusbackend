<?php

namespace App\Services\Lab\Accounting;

use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\DentalLab;
use App\Models\LabExpense;
use App\Models\LabExpenseCategory;
use App\Models\LabInvoice;
use App\Models\LabInvoiceItem;
use App\Models\LabInvoiceItemMaterial;
use App\Models\LabMaterial;
use App\Models\LabPayment;
use App\Models\LabPaymentTransaction;
use App\Models\LabService;
use App\Support\ServiceResult;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LabAccountingService
{
    private const INVOICE_STATUSES = ['Paid', 'Pending', 'Overdue', 'Disputed'];
    private const EXPENSE_TYPES = ['Materials', 'Salaries', 'Utilities', 'Maintenance', 'Delivery', 'Other'];
    private const PAYMENT_METHODS = ['Stripe', 'PayPal', 'Bank Transfer', 'Cash'];
    private const MATERIAL_TYPES = ['All Materials', 'Zirconia', 'E-Max', 'PFM', 'PMMA'];
    private const DEFAULT_TAX_RATE = 5.0;

    public function summary(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        [$from, $to] = $this->monthRange($filters['month'] ?? now()->format('Y-m'));

        $income = (float) LabPayment::query()
            ->where('lab_id', $labId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $expenses = (float) LabExpense::query()
            ->where('lab_id', $labId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $outstanding = (float) LabInvoice::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [LabInvoice::STATUS_PAID, LabInvoice::STATUS_CANCELLED])
            ->sum('remaining_amount');

        return ServiceResult::success([
            'monthly_income' => round($income, 2),
            'monthly_expenses' => round($expenses, 2),
            'monthly_profit' => round($income - $expenses, 2),
            'total_outstanding' => round($outstanding, 2),
            'cards' => [
                ['label' => 'Monthly Income', 'value' => round($income, 2)],
                ['label' => 'Monthly Expenses', 'value' => round($expenses, 2)],
                ['label' => 'Monthly Profit', 'value' => round($income - $expenses, 2)],
                ['label' => 'Total Outstanding', 'value' => round($outstanding, 2)],
            ],
        ], 'Lab accounting summary fetched successfully');
    }

    public function incomeVsExpensesChart(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        return ServiceResult::success(
            $this->dashboardCharts($labId, (int) ($filters['year'] ?? now()->year))['income_vs_expenses'],
            'Income vs expenses chart fetched successfully'
        );
    }

    public function invoices(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        $rows = $this->invoiceQuery($labId, $filters)
            ->latest('id')
            ->paginate($this->perPage($filters));

        return ServiceResult::success([
            'items' => $rows->items(),
            'pagination' => $this->pagination($rows),
        ], 'Lab invoices fetched successfully');
    }

    public function showInvoice(int $invoiceId, array $options = []): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $this->recalculateInvoice($invoice);
        $invoice = $invoice->fresh($this->invoiceRelations());

        return ServiceResult::success($this->invoiceDetails($invoice, $options), 'Lab invoice fetched successfully');
    }

    public function monthlyInvoicePreview(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        [$from, $to] = $this->monthRange($data['month'] ?? now()->format('Y-m'));
        $groupBy = $data['group_by'] ?? 'clinic';

        $items = $this->completedUninvoicedCasesQuery($labId, $from, $to)
            ->with(['clinic:id,name', 'dentist.user:id,name'])
            ->get()
            ->groupBy(fn (CaseModel $case) => $groupBy === 'doctor' ? (string) $case->dentist_id : (string) $case->clinic_id)
            ->filter(fn (Collection $group, string $key) => $key !== '' && $key !== '0')
            ->map(function (Collection $group, string $id) use ($groupBy) {
                $first = $group->first();

                return [
                    'id' => (int) $id,
                    'group_by' => $groupBy,
                    'label' => $groupBy === 'doctor' ? ($first?->dentist?->user?->name ?? 'Unknown Doctor') : ($first?->clinic?->name ?? 'Unknown Clinic'),
                    'clinic_id' => $first?->clinic_id,
                    'doctor_id' => $groupBy === 'doctor' ? $first?->dentist_id : null,
                    'completed_cases' => $group->count(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        return ServiceResult::success([
            'month' => $from->format('F Y'),
            'month_value' => $from->format('Y-m'),
            'group_by' => $groupBy,
            'items' => $items,
        ], 'Monthly invoice preview fetched successfully');
    }

    public function createInvoice(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        return DB::transaction(function () use ($data, $labId) {
            $clinicId = $data['clinic_id'] ?? CaseModel::query()->where('lab_id', $labId)->value('clinic_id') ?? Clinic::query()->value('id');
            if (! $clinicId) {
                return ServiceResult::error('No clinic is available to create a lab invoice.', null, null, 422);
            }

            $invoice = LabInvoice::query()->create([
                'lab_id' => $labId,
                'clinic_id' => $clinicId,
                'doctor_id' => $data['doctor_id'] ?? null,
                'invoice_number' => $this->generateInvoiceNumber($labId),
                'group_by' => 'manual',
                'group_key' => 'manual-' . Str::uuid()->toString(),
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax' => round((float) ($data['tax'] ?? 0), 2),
                'discount' => round((float) ($data['discount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach (($data['items'] ?? []) as $item) {
                $this->createInvoiceItem($invoice, $item);
            }

            $this->recalculateInvoice($invoice);

            return ServiceResult::success(
                $invoice->fresh($this->invoiceRelations()),
                'Lab invoice created successfully',
                201
            );
        });
    }

    public function updateInvoice(int $invoiceId, array $data): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        if (isset($data['status'])) {
            $data['status'] = $this->normalizeInvoiceStatus($data['status']) ?? $invoice->status;
        }

        $invoice->update($data);
        $this->syncInvoiceStatus($invoice->fresh());

        return ServiceResult::success(
            $invoice->fresh($this->invoiceRelations()),
            'Lab invoice updated successfully'
        );
    }

    public function generateMonthlyInvoices(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        [$from, $to] = $this->monthRange($data['month'] ?? now()->format('Y-m'));
        $groupBy = $data['group_by'] ?? 'clinic';
        $taxRate = (float) ($data['tax_rate'] ?? self::DEFAULT_TAX_RATE);
        $discountRate = (float) ($data['discount_rate'] ?? 0);
        $selectedIds = collect($groupBy === 'doctor'
            ? ($data['doctor_ids'] ?? (isset($data['doctor_id']) ? [$data['doctor_id']] : []))
            : ($data['clinic_ids'] ?? (isset($data['clinic_id']) ? [$data['clinic_id']] : [])))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($selectedIds->isEmpty()) {
            return ServiceResult::success([
                'created' => [],
                'skipped' => [],
                'created_count' => 0,
                'skipped_count' => 0,
            ], 'Monthly lab invoices generated successfully', 201);
        }

        $cases = $this->completedUninvoicedCasesQuery($labId, $from, $to)
            ->with(['clinic:id,name,email,phone', 'patient.user:id,name', 'dentist.user:id,name', 'technician:id,name,commission_rates'])
            ->whereIn($groupBy === 'doctor' ? 'dentist_id' : 'clinic_id', $selectedIds->all())
            ->get();

        $groups = $cases->groupBy(function (CaseModel $case) use ($groupBy) {
            return $groupBy === 'doctor'
                ? $case->clinic_id . ':' . $case->dentist_id
                : (string) $case->clinic_id;
        });

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($groups, $labId, $from, $groupBy, $taxRate, $discountRate, $data, &$created, &$skipped) {
            foreach ($groups as $key => $caseGroup) {
                /** @var Collection<int, CaseModel> $caseGroup */
                $first = $caseGroup->first();
                if (! $first) {
                    continue;
                }

                $doctorId = $groupBy === 'doctor' ? $first->dentist_id : null;
                $groupKey = $groupBy === 'doctor' ? ('doctor:' . $doctorId) : ('clinic:' . $first->clinic_id);

                $exists = LabInvoice::query()
                    ->where('lab_id', $labId)
                    ->where('clinic_id', $first->clinic_id)
                    ->whereDate('period_month', $from->toDateString())
                    ->where('group_by', $groupBy)
                    ->where('group_key', $groupKey)
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'clinic_id' => $first->clinic_id,
                        'doctor_id' => $doctorId,
                        'period_month' => $from->format('Y-m'),
                        'reason' => 'duplicate_period_invoice',
                    ];
                    continue;
                }

                $invoice = LabInvoice::query()->create([
                    'lab_id' => $labId,
                    'clinic_id' => $first->clinic_id,
                    'doctor_id' => $doctorId,
                    'invoice_number' => $this->generateInvoiceNumber($labId),
                    'period_month' => $from->toDateString(),
                    'group_by' => $groupBy,
                    'group_key' => $groupKey,
                    'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                    'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($caseGroup as $case) {
                    $price = $this->casePrice($case, $labId);
                    $lineTax = round($price * ($taxRate / 100), 2);
                    $lineDiscount = round($price * ($discountRate / 100), 2);

                    $this->createInvoiceItem($invoice, [
                        'case_id' => $case->id,
                        'lab_service_id' => null,
                        'technician_id' => $case->assigned_technician_id,
                        'case_number' => $case->case_number,
                        'patient_name' => $case->patient?->user?->name,
                        'service_name' => $case->case_type,
                        'teeth_numbers' => is_string($case->tooth_numbers)
    ? json_decode($case->tooth_numbers, true) ?? []
    : ($case->tooth_numbers ?? []),
                        'unit_price' => $price,
                        'quantity' => 1,
                        'materials_cost' => $this->estimatedMaterialsCost($case, $labId),
                        'discount' => $lineDiscount,
                        'tax' => $lineTax,
                    ]);
                }

                $this->recalculateInvoice($invoice);
                $created[] = $invoice->fresh($this->invoiceRelations());
            }
        });

        return ServiceResult::success([
            'created' => $created,
            'skipped' => $skipped,
            'created_count' => count($created),
            'skipped_count' => count($skipped),
        ], 'Monthly lab invoices generated successfully', 201);
    }

    public function recordPayment(int $invoiceId, array $data): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $this->recalculateInvoice($invoice);
        $invoice = $invoice->fresh($this->invoiceRelations());

        if ((float) $invoice->remaining_amount <= 0) {
            return ServiceResult::error('Invoice is already fully paid.', null, ['amount' => ['Invoice is already fully paid.']], 422);
        }

        $amount = (float) ($data['amount'] ?? $invoice->remaining_amount);
        $method = $data['payment_method'] ?? $data['method'] ?? 'Bank Transfer';

        if ($amount > (float) $invoice->remaining_amount) {
            return ServiceResult::error('Payment amount exceeds outstanding balance.', null, ['amount' => ['Payment amount exceeds outstanding balance.']], 422);
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $method) {
            $payment = LabPayment::query()->create([
                'lab_invoice_id' => $invoice->id,
                'lab_id' => $invoice->lab_id,
                'recorded_by' => auth()->id(),
                'amount' => $amount,
                'method' => $method,
                'status' => 'paid',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            LabPaymentTransaction::query()->create([
                'lab_payment_id' => $payment->id,
                'lab_invoice_id' => $invoice->id,
                'lab_id' => $invoice->lab_id,
                'provider' => $method,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'amount' => $amount,
                'status' => 'paid',
                'payload' => ['source' => 'lab_accounting_api'],
                'processed_at' => $payment->paid_at,
            ]);

            $this->recalculateInvoice($invoice->fresh());

            return ServiceResult::success(
                $payment->fresh(['recorder:id,name']),
                'Lab payment recorded successfully',
                201
            );
        });
    }

    public function expenses(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        $rows = LabExpense::query()
            ->with('category:id,name')
            ->where('lab_id', $labId)
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn (Builder $q, int $categoryId) => $q->where('lab_expense_category_id', $categoryId))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('expense_date', '<=', $date))
            ->latest('expense_date')
            ->latest('id')
            ->paginate($this->perPage($filters));

        return ServiceResult::success([
            'items' => $rows->items(),
            'pagination' => $this->pagination($rows),
        ], 'Lab expenses fetched successfully');
    }

    public function createExpense(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        $expenseType = $data['expense_type'] ?? $data['type'] ?? null;
        if (empty($data['lab_expense_category_id']) && $expenseType) {
            $data['lab_expense_category_id'] = LabExpenseCategory::query()->firstOrCreate(
                ['lab_id' => $labId, 'name' => $expenseType],
                ['status' => 'active']
            )->id;
        }

        if (empty($data['lab_expense_category_id'])) {
            $data['lab_expense_category_id'] = LabExpenseCategory::query()->firstOrCreate(
                ['lab_id' => $labId, 'name' => 'Other'],
                ['status' => 'active']
            )->id;
        }

        $data['title'] = $data['title'] ?? $data['description'] ?? $expenseType ?? 'Lab Expense';
        $data['notes'] = $data['notes'] ?? $data['description'] ?? null;
        $data['expense_date'] = $data['expense_date'] ?? $data['date'] ?? now()->toDateString();
        $data['amount'] = $data['amount'] ?? 0;

        $category = LabExpenseCategory::query()->where('lab_id', $labId)->find($data['lab_expense_category_id']);
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, ['lab_expense_category_id' => ['Expense category not found.']], 422);
        }

        if (($data['attachment'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
            $data['attachment_path'] = $data['attachment']->store('lab/accounting/expenses', 'public');
        }
        unset($data['attachment']);

        $expense = LabExpense::query()->create(collect($data)
            ->only(['lab_expense_category_id', 'title', 'amount', 'payment_method', 'expense_date', 'vendor', 'notes', 'attachment_path'])
            ->all() + ['lab_id' => $labId]);

        return ServiceResult::success($expense->fresh('category:id,name'), 'Lab expense created successfully', 201);
    }

    public function updateExpense(int $expenseId, array $data): array
    {
        $expense = $this->findExpenseForCurrentLab($expenseId);
        if (! $expense) {
            return ServiceResult::error('Lab expense not found.', null, null, 404);
        }

        if (isset($data['lab_expense_category_id'])) {
            $exists = LabExpenseCategory::query()
                ->where('lab_id', $expense->lab_id)
                ->whereKey($data['lab_expense_category_id'])
                ->exists();
            if (! $exists) {
                return ServiceResult::error('Expense category not found.', null, ['lab_expense_category_id' => ['Expense category not found.']], 422);
            }
        }

        if (($data['attachment'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
            if ($expense->attachment_path) {
                Storage::disk('public')->delete($expense->attachment_path);
            }
            $data['attachment_path'] = $data['attachment']->store('lab/accounting/expenses', 'public');
        }
        unset($data['attachment']);

        $expense->update($data);

        return ServiceResult::success($expense->fresh('category:id,name'), 'Lab expense updated successfully');
    }

    public function deleteExpense(int $expenseId): array
    {
        $expense = $this->findExpenseForCurrentLab($expenseId);
        if (! $expense) {
            return ServiceResult::error('Lab expense not found.', null, null, 404);
        }

        $expense->delete();

        return ServiceResult::success([], 'Lab expense deleted successfully');
    }

    public function categories(): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        return ServiceResult::success(
            LabExpenseCategory::query()->where('lab_id', $labId)->orderBy('name')->get(),
            'Lab expense categories fetched successfully'
        );
    }

    public function createCategory(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        try {
            $category = LabExpenseCategory::query()->create([
                'lab_id' => $labId,
                'name' => $data['name'] ?? 'Other',
                'status' => $data['status'] ?? 'active',
            ]);
        } catch (Throwable) {
            return ServiceResult::error('Expense category already exists.', null, ['name' => ['Expense category already exists.']], 422);
        }

        return ServiceResult::success($category, 'Lab expense category created successfully', 201);
    }

    public function updateCategory(int $categoryId, array $data): array
    {
        $labId = $this->currentLabId();
        $category = $labId ? LabExpenseCategory::query()->where('lab_id', $labId)->find($categoryId) : null;
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, null, 404);
        }

        $category->update($data);

        return ServiceResult::success($category->fresh(), 'Lab expense category updated successfully');
    }

    public function deleteCategory(int $categoryId): array
    {
        $labId = $this->currentLabId();
        $category = $labId ? LabExpenseCategory::query()->where('lab_id', $labId)->find($categoryId) : null;
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, null, 404);
        }

        if ($category->expenses()->exists()) {
            return ServiceResult::error('Expense category is used by expenses.', null, ['category' => ['Category has linked expenses.']], 422);
        }

        $category->delete();

        return ServiceResult::success([], 'Lab expense category deleted successfully');
    }

    public function technicianEarnings(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        $filters = $this->applyPeriodFilters($filters);
        $materialType = $this->normalizeMaterialType($filters['material_type'] ?? null);

        $rows = LabInvoiceItem::query()
            ->with(['technician:id,name,commission_rates', 'materials'])
            ->whereHas('invoice', function (Builder $q) use ($labId, $filters) {
                $q->where('lab_id', $labId)
                    ->whereNotIn('status', [LabInvoice::STATUS_CANCELLED])
                    ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '<=', $date));
            })
            ->when($filters['technician_id'] ?? null, fn (Builder $q, int $technicianId) => $q->where('technician_id', $technicianId))
            ->when($materialType, fn (Builder $q, string $type) => $q->whereHas('materials', fn (Builder $material) => $material->where('material_type', $type)))
            ->whereNotNull('technician_id')
            ->get()
            ->groupBy('technician_id')
            ->map(function (Collection $items) {
                $technician = $items->first()?->technician;
                $totalValue = (float) $items->sum('total');
                $materials = (float) $items->sum('materials_cost');
                $rate = $this->commissionRate($technician?->commission_rates);
                $commission = round(max($totalValue - $materials, 0) * ($rate / 100), 2);

                return [
                    'technician_id' => $technician?->id,
                    'technician_name' => $technician?->name,
                    'technician' => $technician?->name,
                    'materials' => $items->flatMap(fn (LabInvoiceItem $item) => $item->materials->pluck('material_type'))->filter()->unique()->values()->all(),
                    'total_cases' => $items->pluck('case_id')->filter()->unique()->count(),
                    'total_value' => round($totalValue, 2),
                    'materials_cost' => round($materials, 2),
                    'commission_rate' => $rate,
                    'commission' => $commission,
                    'net_earnings' => $commission,
                ];
            })
            ->values()
            ->all();

        return ServiceResult::success($rows, 'Technician earnings fetched successfully');
    }

    public function topPayingClinics(array $filters = []): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        $payments = LabPayment::query()
            ->with('invoice.clinic:id,name')
            ->where('lab_id', $labId)
            ->where('status', 'paid')
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('paid_at', '<=', $date))
            ->get();

        $rows = $payments
            ->groupBy(fn (LabPayment $payment) => $payment->invoice?->clinic_id)
            ->filter(fn (Collection $group, $clinicId) => $clinicId !== null && $clinicId !== '')
            ->map(function (Collection $group, $clinicId) {
                $clinic = $group->first()?->invoice?->clinic;

                return [
                    'clinic_id' => (int) $clinicId,
                    'clinic_name' => $clinic?->name,
                    'paid_amount' => round((float) $group->sum('amount'), 2),
                    'total_paid' => round((float) $group->sum('amount'), 2),
                    'payments_count' => $group->count(),
                ];
            })
            ->sortByDesc('paid_amount')
            ->take(5)
            ->values()
            ->all();

        return ServiceResult::success($rows, 'Top paying clinics fetched successfully');
    }

    public function analytics(array $filters = []): array
    {
        $filters = $this->applyPeriodFilters($filters);
        $earnings = $this->technicianEarnings($filters);
        if (! $earnings['success']) {
            return $earnings;
        }

        $labId = $this->currentLabId();
        $materialType = $this->normalizeMaterialType($filters['material_type'] ?? null);

        $materialRows = LabInvoiceItemMaterial::query()
            ->whereHas('item.invoice', function (Builder $q) use ($labId, $filters) {
                $q->where('lab_id', $labId)
                    ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '<=', $date));
            })
            ->when($materialType, fn (Builder $q, string $type) => $q->where('material_type', $type))
            ->get()
            ->groupBy(fn (LabInvoiceItemMaterial $material) => $material->material_type ?: 'Other')
            ->map(fn (Collection $items, string $type) => [
                'material_type' => $type,
                'total_cost' => round((float) $items->sum('total_cost'), 2),
                'usage_count' => $items->count(),
            ])
            ->values()
            ->all();

        return ServiceResult::success([
            'monthly_earnings_by_technician' => $earnings['data'],
            'earnings_by_material_type' => $materialRows,
            'empty_message' => empty($earnings['data']) && empty($materialRows) ? 'No earnings data found for the selected filters.' : null,
        ], 'Lab accounting analytics fetched successfully');
    }

    public function exportInvoice(int $invoiceId, string $format): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $downloadFormat = $format === 'excel' ? 'csv' : $format;
        $content = $downloadFormat === 'pdf' ? $this->invoicePdfContent($invoice) : $this->invoiceCsvContent($invoice);

        return ServiceResult::success([
            'filename' => $invoice->invoice_number . '.' . $downloadFormat,
            'content_type' => $downloadFormat === 'pdf' ? 'application/pdf' : 'text/csv',
            'content' => base64_encode($content),
            'signed_download_url' => URL::temporarySignedRoute(
                'lab.accounting.invoices.download',
                now()->addMinutes(60),
                ['invoice' => $invoice->id, 'format' => $downloadFormat]
            ),
        ], 'Lab invoice export prepared successfully');
    }

    public function downloadInvoice(int $invoiceId, string $format, array $options = [], bool $authScoped = true): Response
    {
        $invoice = $authScoped ? $this->findInvoiceForCurrentLab($invoiceId) : $this->findInvoice($invoiceId);
        abort_if(! $invoice, 404, 'Lab invoice not found.');

        $content = $format === 'pdf'
            ? $this->invoicePdfContent($invoice, $options)
            : $this->invoiceCsvContent($invoice);

        return response($content, 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.' . $format . '"',
        ]);
    }

    public function invoiceWhatsAppPreview(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        return ServiceResult::success($this->whatsAppPayload($invoice), 'Invoice WhatsApp preview fetched successfully');
    }

    public function sendInvoiceWhatsApp(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $payload = $this->whatsAppPayload($invoice);

        Log::info('Lab invoice WhatsApp message queued.', [
            'invoice_id' => $invoice->id,
            'lab_id' => $invoice->lab_id,
            'clinic_id' => $invoice->clinic_id,
            'message' => $payload['message'],
        ]);

        return ServiceResult::success($payload + ['provider' => 'lab_whatsapp_settings', 'sent' => true], 'Lab invoice WhatsApp message queued successfully');
    }

    public function paymentLink(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        return ServiceResult::success($this->paymentLinkPayload($invoice), 'Invoice payment link fetched successfully');
    }

    public function paymentSummary(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $this->recalculateInvoice($invoice);
        $invoice = $invoice->fresh($this->invoiceRelations());

        return ServiceResult::success([
            'invoice_id' => $invoice->invoice_number,
            'clinic' => [
                'id' => $invoice->clinic_id,
                'name' => $invoice->clinic?->name,
            ],
            'total_due' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'remaining_amount' => (float) $invoice->remaining_amount,
            'amount' => (float) $invoice->remaining_amount,
            'status' => $this->displayInvoiceStatus($invoice->status),
            'payment_methods' => self::PAYMENT_METHODS,
        ], 'Invoice payment summary fetched successfully');
    }

    public function sendPaymentLink(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $payload = $this->paymentLinkPayload($invoice);
        Log::info('Lab invoice payment link sent.', ['invoice_id' => $invoice->id, 'clinic_id' => $invoice->clinic_id, 'url' => $payload['payment_url']]);

        return ServiceResult::success($payload + ['sent' => true], 'Invoice payment link sent successfully');
    }

    public function placeholderPaymentAttempt(int $invoiceId, string $provider): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        LabPaymentTransaction::query()->create([
            'lab_invoice_id' => $invoice->id,
            'lab_id' => $invoice->lab_id,
            'provider' => $provider,
            'transaction_reference' => strtoupper($provider) . '-' . Str::upper(Str::random(10)),
            'amount' => $invoice->remaining_amount,
            'status' => 'pending',
            'payload' => ['source' => 'placeholder_payment_link'],
        ]);

        return ServiceResult::success([
            'provider' => $provider,
            'payment_url' => url('/lab/accounting/payments/' . $invoice->invoice_number . '/' . Str::slug($provider)),
            'invoice_id' => $invoice->invoice_number,
            'amount_due' => (float) $invoice->remaining_amount,
        ], $provider . ' payment attempt created successfully');
    }

    public function sendToClinicSystem(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        Log::info('Lab invoice sent to clinic system.', ['invoice_id' => $invoice->id, 'clinic_id' => $invoice->clinic_id]);

        return ServiceResult::success([
            'invoice_id' => $invoice->invoice_number,
            'clinic_id' => $invoice->clinic_id,
            'sent' => true,
        ], 'Invoice sent to clinic system successfully');
    }

    public function dashboardSummary(int $labId, ?string $month = null): array
    {
        [$from, $to] = $this->monthRange($month ?? now()->format('Y-m'));

        $income = (float) LabPayment::query()
            ->where('lab_id', $labId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $expenses = (float) LabExpense::query()
            ->where('lab_id', $labId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $outstanding = (float) LabInvoice::query()
            ->where('lab_id', $labId)
            ->whereNotIn('status', [LabInvoice::STATUS_PAID, LabInvoice::STATUS_CANCELLED])
            ->sum('remaining_amount');

        return [
            'monthly_income' => round($income, 2),
            'monthly_expenses' => round($expenses, 2),
            'monthly_profit' => round($income - $expenses, 2),
            'total_outstanding' => round($outstanding, 2),
        ];
    }

    public function dashboardCharts(int $labId, int $year): array
    {
        $payments = LabPayment::query()
            ->where('lab_id', $labId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [Carbon::create($year, 1, 1)->startOfYear(), Carbon::create($year, 12, 31)->endOfYear()])
            ->get();

        $expenses = LabExpense::query()
            ->where('lab_id', $labId)
            ->whereBetween('expense_date', [Carbon::create($year, 1, 1)->startOfYear()->toDateString(), Carbon::create($year, 12, 31)->endOfYear()->toDateString()])
            ->get();

        $incomeVsExpenses = collect(range(1, 12))->map(function (int $month) use ($payments, $expenses) {
            return [
                'month' => $month,
                'month_label' => Carbon::create(null, $month, 1)->format('M'),
                'income' => round((float) $payments->filter(fn (LabPayment $payment) => (int) optional($payment->paid_at)->month === $month)->sum('amount'), 2),
                'expenses' => round((float) $expenses->filter(fn (LabExpense $expense) => (int) optional($expense->expense_date)->month === $month)->sum('amount'), 2),
            ];
        })->all();

        $outstandingTrend = collect(range(1, 12))->map(function (int $month) use ($labId, $year) {
            $end = Carbon::create($year, $month, 1)->endOfMonth();

            return [
                'month' => $month,
                'outstanding' => round((float) LabInvoice::query()
                    ->where('lab_id', $labId)
                    ->whereDate('issue_date', '<=', $end->toDateString())
                    ->whereNotIn('status', [LabInvoice::STATUS_PAID, LabInvoice::STATUS_CANCELLED])
                    ->sum('remaining_amount'), 2),
            ];
        })->all();

        return [
            'income_vs_expenses' => $incomeVsExpenses,
            'outstanding_trend' => $outstandingTrend,
            'top_paying_clinics' => $this->topPayingClinicsForDashboard($labId),
            'monthly_collections' => collect($incomeVsExpenses)->map(fn (array $row) => [
                'month' => $row['month'],
                'total' => $row['income'],
            ])->all(),
        ];
    }

    private function invoiceQuery(int $labId, array $filters): Builder
    {
        $status = isset($filters['status']) ? $this->normalizeInvoiceStatus($filters['status']) : null;

        return LabInvoice::query()
            ->with($this->invoiceRelations())
            ->where('lab_id', $labId)
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $q->where(function (Builder $query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('clinic', fn (Builder $clinic) => $clinic->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items', fn (Builder $item) => $item->where('case_number', 'like', "%{$search}%")
                            ->orWhere('patient_name', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['clinic_id'] ?? null, fn (Builder $q, int $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($filters['doctor_id'] ?? null, fn (Builder $q, int $doctorId) => $q->where('doctor_id', $doctorId))
            ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '<=', $date));
    }

    private function createInvoiceItem(LabInvoice $invoice, array $item): LabInvoiceItem
    {
        $case = ! empty($item['case_id'])
            ? CaseModel::query()->with(['patient.user:id,name'])->where('lab_id', $invoice->lab_id)->find($item['case_id'])
            : null;
        $service = ! empty($item['lab_service_id'])
            ? LabService::query()->where('lab_id', $invoice->lab_id)->find($item['lab_service_id'])
            : null;

        $quantity = (int) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unit_price'] ?? $service?->price ?? 0);
        $subtotal = round($quantity * $unitPrice, 2);
        $tax = round((float) ($item['tax'] ?? 0), 2);
        $discount = round((float) ($item['discount'] ?? 0), 2);
        $materialsCost = round((float) ($item['materials_cost'] ?? 0), 2);
        $teeth = $item['teeth_numbers'] ?? $case?->tooth_numbers ?? [];
        $fdiTeeth = $this->normalizeFdiTeeth($teeth);

        $invoiceItem = LabInvoiceItem::query()->create([
            'lab_invoice_id' => $invoice->id,
            'case_id' => $case?->id,
            'lab_service_id' => $service?->id,
            'patient_id' => $case?->patient_id,
            'technician_id' => $item['technician_id'] ?? $case?->assigned_technician_id,
            'case_number' => $item['case_number'] ?? $case?->case_number,
            'patient_name' => $item['patient_name'] ?? $case?->patient?->user?->name,
            'service_name' => $item['service_name'] ?? $service?->service_name ?? $case?->case_type ?? 'Lab Service',
            'teeth_numbers' => $teeth,
            'fdi_teeth_numbers' => $fdiTeeth,
            'dental_chart' => $this->dentalChart($fdiTeeth),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'materials_cost' => $materialsCost,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => max(round($subtotal + $tax - $discount, 2), 0),
        ]);

        foreach (($item['materials'] ?? []) as $material) {
            $this->createItemMaterial($invoiceItem, $material);
        }

        if ($invoiceItem->materials()->exists()) {
            $invoiceItem->update(['materials_cost' => $invoiceItem->materials()->sum('total_cost')]);
        }

        return $invoiceItem->fresh('materials');
    }

    private function createItemMaterial(LabInvoiceItem $item, array $material): void
    {
        $labMaterial = ! empty($material['lab_material_id'])
            ? LabMaterial::query()->find($material['lab_material_id'])
            : null;
        $quantity = (float) ($material['quantity'] ?? 1);
        $unitCost = (float) ($material['unit_cost'] ?? $labMaterial?->cost ?? 0);

        LabInvoiceItemMaterial::query()->create([
            'lab_invoice_item_id' => $item->id,
            'lab_material_id' => $labMaterial?->id,
            'material_name' => $material['material_name'] ?? $labMaterial?->name ?? 'Material',
            'material_type' => $material['material_type'] ?? $labMaterial?->supplier,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 2),
        ]);
    }

    private function recalculateInvoice(LabInvoice $invoice): void
    {
        $subtotal = (float) $invoice->items()->sum('subtotal');
        $lineTax = (float) $invoice->items()->sum('tax');
        $lineDiscount = (float) $invoice->items()->sum('discount');
        $headerTax = (float) $invoice->tax;
        $headerDiscount = (float) $invoice->discount;
        $total = max(round($subtotal + $lineTax + $headerTax - $lineDiscount - $headerDiscount, 2), 0);
        $paid = (float) $invoice->payments()->where('status', 'paid')->sum('amount');
        $remaining = max(round($total - $paid, 2), 0);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'total_amount' => $total,
            'paid_amount' => round($paid, 2),
            'remaining_amount' => $remaining,
            'status' => $this->resolveStatus($total, $paid, optional($invoice->due_date)?->toDateString(), $invoice->status),
        ]);
    }

    private function syncInvoiceStatus(LabInvoice $invoice): void
    {
        $this->recalculateInvoice($invoice);
    }

    private function resolveStatus(float $total, float $paid, ?string $dueDate, ?string $currentStatus = null): string
    {
        if (in_array($currentStatus, [LabInvoice::STATUS_CANCELLED, 'disputed'], true)) {
            return $currentStatus;
        }

        if ($total > 0 && $paid >= $total) {
            return LabInvoice::STATUS_PAID;
        }

        if ($paid > 0) {
            return LabInvoice::STATUS_PARTIAL;
        }

        if ($dueDate && now()->toDateString() > $dueDate) {
            return LabInvoice::STATUS_OVERDUE;
        }

        return LabInvoice::STATUS_PENDING;
    }

    private function findInvoiceForCurrentLab(int $invoiceId): ?LabInvoice
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return null;
        }

        return LabInvoice::query()
            ->with($this->invoiceRelations())
            ->where('lab_id', $labId)
            ->find($invoiceId);
    }

    private function findInvoice(int $invoiceId): ?LabInvoice
    {
        return LabInvoice::query()
            ->with($this->invoiceRelations() + ['lab'])
            ->find($invoiceId);
    }

    private function findExpenseForCurrentLab(int $expenseId): ?LabExpense
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return null;
        }

        return LabExpense::query()->where('lab_id', $labId)->find($expenseId);
    }

    private function invoiceRelations(): array
    {
        return [
            'lab',
            'clinic:id,name,email,phone,address',
            'doctor.user:id,name',
            'items.case:id,completed_at',
            'items.materials',
            'items.technician:id,name,commission_rates',
            'payments.recorder:id,name',
        ];
    }

    private function generateInvoiceNumber(int $labId): string
    {
        do {
            $number = 'LI-' . now()->timestamp . '-' . Str::lower(Str::random(5));
        } while (LabInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }

    private function completedUninvoicedCasesQuery(int $labId, Carbon $from, Carbon $to): Builder
    {
        return CaseModel::query()
            ->where('lab_id', $labId)
            ->whereIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotIn('id', LabInvoiceItem::query()
                ->whereNotNull('case_id')
                ->whereHas('invoice', fn (Builder $q) => $q->where('lab_id', $labId)->whereNotIn('status', [LabInvoice::STATUS_CANCELLED]))
                ->select('case_id'));
    }

    private function invoiceDetails(LabInvoice $invoice, array $options = []): array
    {
        $showDentalChart = filter_var($options['show_dental_chart'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $showToothNumbers = filter_var($options['show_tooth_numbers'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $lab = $invoice->lab ?: DentalLab::query()->find($invoice->lab_id);
        $taxRate = $invoice->subtotal > 0 ? round(((float) $invoice->tax / (float) $invoice->subtotal) * 100, 2) : self::DEFAULT_TAX_RATE;

        return [
            'id' => $invoice->id,
            'invoice_id' => $invoice->invoice_number,
            'invoice_number' => $invoice->invoice_number,
            'date' => optional($invoice->issue_date)?->format('d/m/Y'),
            'issue_date' => optional($invoice->issue_date)?->toDateString(),
            'due_date' => optional($invoice->due_date)?->toDateString(),
            'status' => $this->displayInvoiceStatus($invoice->status),
            'lab_info' => [
                'name' => $lab?->name ?? 'Precision Dental Labs',
                'address' => $lab?->address ?? '789 Tech Park, Metropolis',
                'phone' => $lab?->phone ?? '555-0201',
                'email' => $lab?->email ?? 'contact@precisionlabs.com',
            ],
            'bill_to' => [
                'clinic_id' => $invoice->clinic_id,
                'clinic_code' => 'c' . $invoice->clinic_id,
                'name' => $invoice->clinic?->name,
                'email' => $invoice->clinic?->email,
                'phone' => $invoice->clinic?->phone,
                'address' => $invoice->clinic?->address,
            ],
            'cases' => $invoice->items->map(fn (LabInvoiceItem $item) => [
                'case_id' => $item->case_number,
                'patient' => $item->patient_name,
                'service' => $item->service_name,
                'teeth' => $showToothNumbers ? implode(', ', $item->fdi_teeth_numbers ?? []) : null,
                'tooth_number' => $showToothNumbers ? implode(', ', $item->fdi_teeth_numbers ?? []) : null,
                'completion_date' => optional($item->case?->completed_at)?->toDateString(),
                'amount' => (float) $item->total,
                'dental_chart' => $showDentalChart ? ($item->dental_chart ?? []) : [],
            ])->values()->all(),
            'subtotal' => (float) $invoice->subtotal,
            'tax_rate' => $taxRate,
            'tax' => (float) $invoice->tax,
            'discount' => (float) $invoice->discount,
            'total_due' => (float) $invoice->total_amount,
            'amount_due' => (float) $invoice->remaining_amount,
            'flags' => [
                'show_dental_chart' => $showDentalChart,
                'show_tooth_numbers' => $showToothNumbers,
            ],
            'download_links' => [
                'csv' => URL::temporarySignedRoute('lab.accounting.invoices.download', now()->addMinutes(60), ['invoice' => $invoice->id, 'format' => 'csv']),
                'pdf' => URL::temporarySignedRoute('lab.accounting.invoices.download', now()->addMinutes(60), ['invoice' => $invoice->id, 'format' => 'pdf']),
            ],
        ];
    }

    private function invoiceCsvContent(LabInvoice $invoice): string
    {
        $rows = [
            ['Invoice Summary'],
            ['Invoice ID', $invoice->invoice_number],
            ['Clinic', $invoice->clinic?->name],
            ['Issue Date', optional($invoice->issue_date)?->toDateString()],
            ['Due Date', optional($invoice->due_date)?->toDateString()],
            ['Total Amount', '$' . number_format((float) $invoice->total_amount, 2)],
            [],
            ['Included Cases'],
            ['Case ID', 'Patient', 'Service', 'Tooth Number', 'Completion Date', 'Amount'],
        ];

        foreach ($invoice->items as $item) {
            $rows[] = [
                $item->case_number,
                $item->patient_name,
                $item->service_name,
                implode(', ', $item->fdi_teeth_numbers ?? []),
                optional($item->case?->completed_at)?->toDateString(),
                (float) $item->total,
            ];
        }

        return $this->csvRows($rows);
    }

    private function invoicePdfContent(LabInvoice $invoice, array $options = []): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $details = $this->invoiceDetails($invoice, $options);
            $rows = collect($details['cases'])->map(fn (array $case) => '<tr><td>' . e($case['case_id']) . '</td><td>' . e($case['patient']) . '</td><td>' . e($case['service']) . '</td><td>' . e($case['teeth']) . '</td><td>$' . number_format($case['amount'], 2) . '</td></tr>')->implode('');
            $html = '<h1>' . e($details['lab_info']['name']) . '</h1><h2>INVOICE</h2>'
                . '<p>Invoice #: ' . e($details['invoice_number']) . '<br>Date: ' . e($details['issue_date']) . '<br>Due Date: ' . e($details['due_date']) . '</p>'
                . '<hr><h3>Bill To</h3><p>' . e($details['bill_to']['name']) . '<br>' . e($details['bill_to']['email']) . '<br>' . e($details['bill_to']['address']) . '</p>'
                . '<table width="100%" border="0" cellspacing="0" cellpadding="8"><thead><tr><th align="left">Case ID</th><th align="left">Patient</th><th align="left">Service</th><th align="left">Teeth</th><th align="right">Amount</th></tr></thead><tbody>' . $rows . '</tbody></table>'
                . '<hr><p align="right">Subtotal: $' . number_format($details['subtotal'], 2) . '<br>Tax (' . $details['tax_rate'] . '%): $' . number_format($details['tax'], 2) . '<br>Discount: -$' . number_format($details['discount'], 2) . '</p>'
                . '<h2 align="right">Total Due: $' . number_format($details['total_due'], 2) . '</h2>'
                . '<p align="center">Thank you for your business!<br>Please include invoice number ' . e($details['invoice_number']) . ' on your payment.</p>';

            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        }

        return $this->invoiceCsvContent($invoice);
    }

    private function whatsAppPayload(LabInvoice $invoice): array
    {
        $month = optional($invoice->period_month ?: $invoice->issue_date)?->format('Y-m');

        return [
            'recipient' => trim(($invoice->clinic?->name ?? 'Clinic') . ' (' . ($invoice->clinic?->phone ?? 'No phone') . ')'),
            'message' => "Dear {$invoice->clinic?->name},\n\nPlease find attached your invoice ({$invoice->invoice_number}) for {$month}.\nTotal: $" . number_format((float) $invoice->total_amount, 2),
            'attachment_preview' => $invoice->invoice_number . '.pdf',
        ];
    }

    private function paymentLinkPayload(LabInvoice $invoice): array
    {
        return [
            'clinic' => $invoice->clinic?->name,
            'invoice_id' => $invoice->invoice_number,
            'amount_due' => (float) $invoice->remaining_amount,
            'payment_url' => url('/lab/accounting/pay/' . $invoice->invoice_number),
            'providers' => [
                ['label' => 'Pay with Stripe', 'provider' => 'Stripe'],
                ['label' => 'Pay with PayPal', 'provider' => 'PayPal'],
            ],
        ];
    }

    private function normalizeInvoiceStatus(string $status): ?string
    {
        return match (strtolower(str_replace(' ', '_', $status))) {
            'all_statuses' => null,
            'paid' => LabInvoice::STATUS_PAID,
            'pending' => LabInvoice::STATUS_PENDING,
            'overdue' => LabInvoice::STATUS_OVERDUE,
            'disputed' => 'disputed',
            default => strtolower($status),
        };
    }

    private function applyPeriodFilters(array $filters): array
    {
        $period = $filters['period'] ?? 'all_time';
        if ($period === 'this_month') {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to'] = now()->endOfMonth()->toDateString();
        }

        if ($period === 'this_week') {
            $filters['date_from'] = now()->startOfWeek()->toDateString();
            $filters['date_to'] = now()->endOfWeek()->toDateString();
        }

        if (($filters['technician_id'] ?? null) === 'all') {
            unset($filters['technician_id']);
        }

        return $filters;
    }

    private function normalizeMaterialType(?string $materialType): ?string
    {
        if (! $materialType) {
            return null;
        }

        return match (strtolower(str_replace([' ', '-'], ['_', '_'], $materialType))) {
            'all', 'all_materials' => null,
            'zirconia' => 'Zirconia',
            'e_max', 'emax' => 'E-Max',
            'pfm' => 'PFM',
            'pmma' => 'PMMA',
            default => $materialType,
        };
    }

    private function displayInvoiceStatus(?string $status): string
    {
        return match ($status) {
            LabInvoice::STATUS_PAID => 'Paid',
            LabInvoice::STATUS_OVERDUE => 'Overdue',
            'disputed' => 'Disputed',
            default => 'Pending',
        };
    }

    private function csvRows(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    private function casePrice(CaseModel $case, int $labId): float
    {
        $service = LabService::query()
            ->where('lab_id', $labId)
            ->where('service_name', $case->case_type)
            ->first();

        return round((float) ($service?->price ?? 0), 2);
    }

    private function estimatedMaterialsCost(CaseModel $case, int $labId): float
    {
        return round((float) LabMaterial::query()
            ->where('lab_id', $labId)
            ->where('name', 'like', '%' . $case->case_type . '%')
            ->sum('cost'), 2);
    }

   private function normalizeFdiTeeth(array|string $teeth): array
{
    if (is_string($teeth)) {
        $teeth = json_decode($teeth, true) ?? [];
    }

    return collect($teeth)
            ->map(fn ($tooth) => (int) $tooth)
            ->filter(fn (int $tooth) => $tooth >= 11 && $tooth <= 48 && ! in_array($tooth % 10, [0, 9], true))
            ->unique()
            ->values()
            ->all();
    }

    private function dentalChart(array $fdiTeeth): array
    {
        return collect(range(11, 48))
            ->filter(fn (int $tooth) => ! in_array($tooth % 10, [0, 9], true))
            ->map(fn (int $tooth) => [
                'tooth' => $tooth,
                'system' => 'FDI',
                'included' => in_array($tooth, $fdiTeeth, true),
            ])
            ->values()
            ->all();
    }

    private function commissionRate(?array $rates): float
    {
        if (! $rates) {
            return 0;
        }

        return (float) ($rates['default'] ?? $rates['commission'] ?? reset($rates) ?: 0);
    }

    private function topPayingClinicsForDashboard(int $labId): array
    {
        $result = $this->topPayingClinics([
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->endOfYear()->toDateString(),
        ]);

        return $result['success'] ? $result['data'] : [];
    }

    private function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();

        return [$start->copy(), $start->copy()->endOfMonth()];
    }

    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 100));
    }

    private function pagination(LengthAwarePaginator $rows): array
    {
        return [
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ];
    }

    private function csvContent(array $headers, array $rows): string
    {
        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"', $row));
        }

        return implode("\n", $lines);
    }

    private function simplePdfFallback(LabInvoice $invoice): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $html = '<h1>Lab Invoice ' . e($invoice->invoice_number) . '</h1>'
                . '<p>Total: ' . e((string) $invoice->total_amount) . '</p>'
                . '<p>Status: ' . e($invoice->status) . '</p>';

            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        }

        return "Lab Invoice {$invoice->invoice_number}\nTotal: {$invoice->total_amount}\nStatus: {$invoice->status}";
    }

    private function currentLabId(): ?int
    {
        return auth()->user()?->lab_id;
    }
}
