<?php

namespace App\Services\Company;

use App\Http\Resources\Company\ExpenseResource;
use App\Models\BankTransaction;
use App\Models\CompanyExpense;
use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountService
{
    public function summary(?string $period = null): array
    {
        [$start, $end, $previousStart, $previousEnd] = $this->periodBounds($period);

        $revenueQuery = Invoice::query()->where('status', 'paid');
        $expenseQuery = CompanyExpense::query();

        if ($start && $end) {
            $revenueQuery->whereBetween('issue_date', [$start, $end]);
            $expenseQuery->whereBetween('expense_date', [$start, $end]);
        }

        $revenue = $revenueQuery->sum('total_amount');
        $expenses = $expenseQuery->sum('amount');

        $previousRevenue = 0;
        $previousExpenses = 0;
        if ($previousStart && $previousEnd) {
            $previousRevenue = Invoice::query()
                ->where('status', 'paid')
                ->whereBetween('issue_date', [$previousStart, $previousEnd])
                ->sum('total_amount');
            $previousExpenses = CompanyExpense::query()
                ->whereBetween('expense_date', [$previousStart, $previousEnd])
                ->sum('amount');
        }

        return [
            'period' => $period,
            'revenue' => (float) $revenue,
            'expenses' => (float) $expenses,
            'net_profit' => (float) ($revenue - $expenses),
            'previous_period' => [
                'revenue' => (float) $previousRevenue,
                'expenses' => (float) $previousExpenses,
                'net_profit' => (float) ($previousRevenue - $previousExpenses),
            ],
        ];
    }

  public function invoices(array $filters = []): array
{
    $query = Invoice::query()->latest('id');

    // Search by invoice number or amount
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', "%{$search}%")
              ->orWhere('total_amount', 'like', "%{$search}%");
        });
    }

    // Filter by status
    if (!empty($filters['status']) && in_array($filters['status'], ['paid', 'unpaid'])) {
        $query->where('status', $filters['status']);
    }

    // Filter by date range
    if (!empty($filters['date_from'])) {
        $query->whereDate('issue_date', '>=', $filters['date_from']);
    }

    if (!empty($filters['date_to'])) {
        $query->whereDate('issue_date', '<=', $filters['date_to']);
    }

    return $query->get()->map(fn ($invoice) => [
        'id'             => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'status'         => $invoice->status,
        'total_amount'   => (float) $invoice->total_amount,
        'issue_date'     => optional($invoice->issue_date)?->toDateString(),
    ])->all();
}

    public function expenses(): array
    {
        return ExpenseResource::collection(CompanyExpense::query()->latest('expense_date')->get())->resolve();
    }

    public function createExpense(array $data): array
    {
        if (($data['receipt'] ?? null) instanceof UploadedFile) {
            $data['receipt_path'] = $data['receipt']->store('company/expenses', 'public');
        }
        unset($data['receipt']);
        $data['company_id'] = auth()->user()->company_id;
        return (new ExpenseResource(CompanyExpense::create($data)))->resolve();
    }

    public function bankTransactions(): array
    {
        return BankTransaction::query()->latest('transaction_date')->get()->map(fn ($transaction) => [
            'id' => $transaction->id,
            'transaction_id' => $transaction->transaction_id,
            'transaction_date' => optional($transaction->transaction_date)?->toDateString(),
            'amount' => (float) $transaction->amount,
            'source' => $transaction->source,
            'status' => $transaction->status,
            'type' => $transaction->type,
            'matched_invoice_id' => $transaction->matched_invoice_id,
        ])->all();
    }

    public function syncBankTransactions(): array
    {
        $transaction = BankTransaction::create([
            'company_id' => auth()->user()->company_id,
            'transaction_id' => 'BANK-' . Str::upper(Str::random(10)),
            'transaction_date' => now()->toDateString(),
            'amount' => 1250,
            'source' => 'manual_sync',
            'status' => 'synced',
            'type' => 'credit',
        ]);

        return ['created_transaction_id' => $transaction->id];
    }

    public function profitLoss(?string $period = null): array
    {
        $summary = $this->summary($period);

        [$start, $end] = $this->periodBounds($period);
        $expenseBreakdown = CompanyExpense::query()
            ->when($start && $end, fn ($query) => $query->whereBetween('expense_date', [$start, $end]))
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
            ])
            ->values();

        $chart = [
            ['label' => 'income', 'value' => $summary['revenue']],
            ['label' => 'expenses', 'value' => $summary['expenses']],
            ['label' => 'profit', 'value' => $summary['net_profit']],
        ];

        return [
            'period' => $period,
            'income' => $summary['revenue'],
            'expenses' => $summary['expenses'],
            'profit' => $summary['net_profit'],
            'previous_period' => $summary['previous_period'],
            'expense_breakdown' => $expenseBreakdown,
            'chart' => $chart,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function periodBounds(?string $period): array
    {
        if (! $period) {
            return [null, null, null, null];
        }

        $now = now();

        [$start, $end] = match ($period) {
            'day' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $previousStart = match ($period) {
            'day' => $start->copy()->subDay(),
            'week' => $start->copy()->subWeek(),
            'year' => $start->copy()->subYear(),
            default => $start->copy()->subMonth(),
        };

        $previousEnd = match ($period) {
            'day' => $end->copy()->subDay(),
            'week' => $end->copy()->subWeek(),
            'year' => $end->copy()->subYear(),
            default => $end->copy()->subMonth(),
        };

        return [$start, $end, $previousStart, $previousEnd];
    }
}
