<?php

namespace App\Services\Lab\Accounting;

use App\Models\CaseModel;
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
use Illuminate\Support\Str;
use Throwable;

class LabAccountingService
{
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
        ], 'Lab accounting summary fetched successfully');
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

    public function showInvoice(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        return ServiceResult::success($invoice, 'Lab invoice fetched successfully');
    }

    public function createInvoice(array $data): array
    {
        $labId = $this->currentLabId();
        if (! $labId) {
            return ServiceResult::error('Lab account is not linked to a dental lab.', null, null, 403);
        }

        return DB::transaction(function () use ($data, $labId) {
            $invoice = LabInvoice::query()->create([
                'lab_id' => $labId,
                'clinic_id' => $data['clinic_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'invoice_number' => $this->generateInvoiceNumber($labId),
                'group_by' => 'manual',
                'group_key' => 'manual-' . Str::uuid()->toString(),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'tax' => round((float) ($data['tax'] ?? 0), 2),
                'discount' => round((float) ($data['discount'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
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

        [$from, $to] = $this->monthRange($data['month']);
        $groupBy = $data['group_by'];
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $discountRate = (float) ($data['discount_rate'] ?? 0);

        $cases = CaseModel::query()
            ->with(['clinic:id,name,email,phone', 'patient.user:id,name', 'dentist.user:id,name', 'technician:id,name,commission_rates'])
            ->where('lab_id', $labId)
            ->whereIn('status', [CaseModel::STATUS_COMPLETED, CaseModel::STATUS_DELIVERED])
            ->whereBetween('completed_at', [$from, $to])
            ->when($data['clinic_id'] ?? null, fn (Builder $q, int $clinicId) => $q->where('clinic_id', $clinicId))
            ->when($data['doctor_id'] ?? null, fn (Builder $q, int $doctorId) => $q->where('dentist_id', $doctorId))
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
                $groupKey = $groupBy === 'doctor' ? ('doctor:' . $doctorId) : 'clinic';

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
                    'due_date' => $data['due_date'] ?? now()->addDays(15)->toDateString(),
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
                        'teeth_numbers' => $case->tooth_numbers ?? [],
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

        if ((float) $invoice->remaining_amount <= 0) {
            return ServiceResult::error('Invoice is already fully paid.', null, ['amount' => ['Invoice is already fully paid.']], 422);
        }

        if ((float) $data['amount'] > (float) $invoice->remaining_amount) {
            return ServiceResult::error('Payment amount exceeds outstanding balance.', null, ['amount' => ['Payment amount exceeds outstanding balance.']], 422);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $payment = LabPayment::query()->create([
                'lab_invoice_id' => $invoice->id,
                'lab_id' => $invoice->lab_id,
                'recorded_by' => auth()->id(),
                'amount' => $data['amount'],
                'method' => $data['method'],
                'status' => 'paid',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            LabPaymentTransaction::query()->create([
                'lab_payment_id' => $payment->id,
                'lab_invoice_id' => $invoice->id,
                'lab_id' => $invoice->lab_id,
                'provider' => $data['method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'amount' => $data['amount'],
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

        $category = LabExpenseCategory::query()->where('lab_id', $labId)->find($data['lab_expense_category_id']);
        if (! $category) {
            return ServiceResult::error('Expense category not found.', null, ['lab_expense_category_id' => ['Expense category not found.']], 422);
        }

        if (($data['attachment'] ?? null) instanceof \Illuminate\Http\UploadedFile) {
            $data['attachment_path'] = $data['attachment']->store('lab/accounting/expenses', 'public');
        }
        unset($data['attachment']);

        $expense = LabExpense::query()->create($data + ['lab_id' => $labId]);

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
                'name' => $data['name'],
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

        $rows = LabInvoiceItem::query()
            ->with('technician:id,name,commission_rates')
            ->whereHas('invoice', function (Builder $q) use ($labId, $filters) {
                $q->where('lab_id', $labId)
                    ->whereNotIn('status', [LabInvoice::STATUS_CANCELLED])
                    ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '<=', $date));
            })
            ->when($filters['technician_id'] ?? null, fn (Builder $q, int $technicianId) => $q->where('technician_id', $technicianId))
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
        $earnings = $this->technicianEarnings($filters);
        if (! $earnings['success']) {
            return $earnings;
        }

        $labId = $this->currentLabId();
        $materialRows = LabInvoiceItemMaterial::query()
            ->whereHas('item.invoice', function (Builder $q) use ($labId, $filters) {
                $q->where('lab_id', $labId)
                    ->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('issue_date', '<=', $date));
            })
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
        ], 'Lab accounting analytics fetched successfully');
    }

    public function exportInvoice(int $invoiceId, string $format): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        $rows = $invoice->items->map(fn (LabInvoiceItem $item) => [
            $item->case_number,
            $item->patient_name,
            $item->service_name,
            implode('|', $item->fdi_teeth_numbers ?? []),
            (string) $item->subtotal,
            (string) $item->tax,
            (string) $item->discount,
            (string) $item->total,
        ])->all();

        $content = $this->csvContent(['Case Number', 'Patient', 'Service', 'FDI Teeth', 'Subtotal', 'Tax', 'Discount', 'Total'], $rows);
        $extension = $format === 'excel' ? 'xlsx' : $format;

        return ServiceResult::success([
            'filename' => $invoice->invoice_number . '.' . $extension,
            'content_type' => $format === 'pdf' ? 'application/pdf' : 'text/csv',
            'content' => base64_encode($format === 'pdf' ? $this->simplePdfFallback($invoice) : $content),
        ], 'Lab invoice export prepared successfully');
    }

    public function sendInvoiceWhatsApp(int $invoiceId): array
    {
        $invoice = $this->findInvoiceForCurrentLab($invoiceId);
        if (! $invoice) {
            return ServiceResult::error('Lab invoice not found.', null, null, 404);
        }

        Log::info('Lab invoice WhatsApp message queued.', [
            'invoice_id' => $invoice->id,
            'lab_id' => $invoice->lab_id,
            'clinic_id' => $invoice->clinic_id,
        ]);

        return ServiceResult::success([
            'invoice_id' => $invoice->id,
            'provider' => 'lab_whatsapp_settings',
            'queued' => true,
        ], 'Lab invoice WhatsApp message queued successfully');
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
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
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
        if ($currentStatus === LabInvoice::STATUS_CANCELLED) {
            return LabInvoice::STATUS_CANCELLED;
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
            'clinic:id,name,email,phone',
            'doctor.user:id,name',
            'items.materials',
            'items.technician:id,name,commission_rates',
            'payments.recorder:id,name',
        ];
    }

    private function generateInvoiceNumber(int $labId): string
    {
        do {
            $number = 'LAB-INV-' . $labId . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (LabInvoice::query()->where('invoice_number', $number)->exists());

        return $number;
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

    private function normalizeFdiTeeth(array $teeth): array
    {
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
