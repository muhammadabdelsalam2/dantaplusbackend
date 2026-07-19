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
    $companyId = auth()->user()->company_id;
    [$start, $end, $previousStart, $previousEnd] = $this->periodBounds($period);

    $revenueQuery = Invoice::query()->where('company_id', $companyId)->where('status', 'paid');
    $expenseQuery = CompanyExpense::query()->where('company_id', $companyId);
    $pendingQuery = Invoice::query()->where('company_id', $companyId)->where('status', 'unpaid');

    if ($start && $end) {
        $revenueQuery->whereBetween('issue_date', [$start, $end]);
        $expenseQuery->whereBetween('expense_date', [$start, $end]);
        $pendingQuery->whereBetween('issue_date', [$start, $end]);
    }

    $revenue  = (clone $revenueQuery)->sum('total_amount');
    $expenses = (clone $expenseQuery)->sum('amount');
    $pending  = (clone $pendingQuery)->sum('total_amount');

    $previousRevenue = 0;
    $previousExpenses = 0;
    if ($previousStart && $previousEnd) {
        $previousRevenue = Invoice::query()->where('company_id', $companyId)->where('status', 'paid')
            ->whereBetween('issue_date', [$previousStart, $previousEnd])->sum('total_amount');
        $previousExpenses = CompanyExpense::query()->where('company_id', $companyId)
            ->whereBetween('expense_date', [$previousStart, $previousEnd])->sum('amount');
    }

    return [
        'period' => $period,
        'revenue' => (float) $revenue,
        'expenses' => (float) $expenses,
        'net_profit' => (float) ($revenue - $expenses),
        'pending_invoices' => (float) $pending,
        'paid_invoices' => (float) $revenue, // فعليًا نفس الـ revenue لأن revenue = مجموع الفواتير المدفوعة
        'previous_period' => [
            'revenue' => (float) $previousRevenue,
            'expenses' => (float) $previousExpenses,
            'net_profit' => (float) ($previousRevenue - $previousExpenses),
        ],
        'chart' => $this->revenueExpenseTrend($companyId, $period, $start, $end),
        'top_clients' => $this->topClientsByRevenue($companyId, $start, $end),
    ];
}

private function revenueExpenseTrend(int $companyId, ?string $period, $start, $end): array
{
    // لو مفيش period محدد، هنعرض آخر 6 شهور كـ default trend
    if (!$start || !$end) {
        $start = now()->copy()->subMonths(5)->startOfMonth();
        $end = now()->copy()->endOfMonth();
    }

    $groupFormat = match ($period) {
        'day' => '%Y-%m-%d %H:00', // بالساعة
        'week' => '%Y-%m-%d',      // بالليوم
        'year' => '%Y-%m',         // بالشهر
        default => '%Y-%m-%d',     // month -> بالليوم
    };

    $revenueRows = Invoice::query()
        ->where('company_id', $companyId)->where('status', 'paid')
        ->whereBetween('issue_date', [$start, $end])
        ->selectRaw("DATE_FORMAT(issue_date, '{$groupFormat}') as bucket, SUM(total_amount) as total")
        ->groupBy('bucket')->pluck('total', 'bucket');

    $expenseRows = CompanyExpense::query()
        ->where('company_id', $companyId)
        ->whereBetween('expense_date', [$start, $end])
        ->selectRaw("DATE_FORMAT(expense_date, '{$groupFormat}') as bucket, SUM(amount) as total")
        ->groupBy('bucket')->pluck('total', 'bucket');

    $buckets = $revenueRows->keys()->merge($expenseRows->keys())->unique()->sort()->values();

    return $buckets->map(fn ($bucket) => [
        'label' => $bucket,
        'revenue' => (float) ($revenueRows[$bucket] ?? 0),
        'expenses' => (float) ($expenseRows[$bucket] ?? 0),
    ])->all();
}

private function topClientsByRevenue(int $companyId, $start, $end, int $limit = 5): array
{
    $query = Invoice::query()
        ->where('company_id', $companyId)
        ->where('status', 'paid')
        ->when($start && $end, fn ($q) => $q->whereBetween('issue_date', [$start, $end]))
        ->join('clinics', 'clinics.id', '=', 'invoices.clinic_id')
        ->selectRaw('clinics.id as clinic_id, clinics.name as clinic_name, SUM(invoices.total_amount) as total_revenue')
        ->groupBy('clinics.id', 'clinics.name')
        ->orderByDesc('total_revenue')
        ->limit($limit)
        ->get();

    return $query->map(fn ($row) => [
        'clinic_id' => $row->clinic_id,
        'clinic_name' => $row->clinic_name,
        'total_revenue' => (float) $row->total_revenue,
    ])->all();
}
  public function invoices(array $filters = []): array
{
    $query = Invoice::query()->where('company_id', auth()->user()->company_id)->latest('id');

    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function ($q) use ($search) {
            $q->where('invoice_number', 'like', "%{$search}%")
              ->orWhere('total_amount', 'like', "%{$search}%");
        });
    }

    if (!empty($filters['status']) && in_array($filters['status'], ['paid', 'unpaid'])) {
        $query->where('status', $filters['status']);
    }

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
    return ExpenseResource::collection(
        CompanyExpense::query()
            ->where('company_id', auth()->user()->company_id)
            ->latest('expense_date')
            ->get()
    )->resolve();
}

public function bankTransactions(): array
{
    return BankTransaction::query()
        ->where('company_id', auth()->user()->company_id)
        ->latest('transaction_date')
        ->get()
        ->map(fn ($transaction) => [
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
    public function createExpense(array $data): array
    {
        if (($data['receipt'] ?? null) instanceof UploadedFile) {
            $data['receipt_path'] = $data['receipt']->store('company/expenses', 'public');
        }
        unset($data['receipt']);
        $data['company_id'] = auth()->user()->company_id;
        return (new ExpenseResource(CompanyExpense::create($data)))->resolve();
    }

    // public function bankTransactions(): array
    // {
    //     return BankTransaction::query()->latest('transaction_date')->get()->map(fn ($transaction) => [
    //         'id' => $transaction->id,
    //         'transaction_id' => $transaction->transaction_id,
    //         'transaction_date' => optional($transaction->transaction_date)?->toDateString(),
    //         'amount' => (float) $transaction->amount,
    //         'source' => $transaction->source,
    //         'status' => $transaction->status,
    //         'type' => $transaction->type,
    //         'matched_invoice_id' => $transaction->matched_invoice_id,
    //     ])->all();
    // }

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
    $companyId = auth()->user()->company_id;
    $summary = $this->summary($period);

    [$start, $end] = $this->periodBounds($period);
    $expenseBreakdown = CompanyExpense::query()
        ->where('company_id', $companyId)
        ->when($start && $end, fn ($query) => $query->whereBetween('expense_date', [$start, $end]))
        ->selectRaw('category, SUM(amount) as total')
        ->groupBy('category')
        ->get()
        ->map(fn ($row) => ['category' => $row->category, 'total' => (float) $row->total])
        ->values();

    return [
        'period' => $period,
        'income' => $summary['revenue'],
        'expenses' => $summary['expenses'],
        'profit' => $summary['net_profit'],
        'previous_period' => $summary['previous_period'],
        'expense_breakdown' => $expenseBreakdown,
        'monthly_trend' => $this->monthlyProfitTrend($companyId), // آخر 6 شهور زي البروتوتايب
        'generated_at' => now()->toISOString(),
    ];
}

private function monthlyProfitTrend(int $companyId, int $months = 6): array
{
    $result = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $monthStart = now()->copy()->subMonths($i)->startOfMonth();
        $monthEnd = now()->copy()->subMonths($i)->endOfMonth();

        $revenue = Invoice::query()->where('company_id', $companyId)->where('status', 'paid')
            ->whereBetween('issue_date', [$monthStart, $monthEnd])->sum('total_amount');
        $expenses = CompanyExpense::query()->where('company_id', $companyId)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');

        $result[] = [
            'month' => $monthStart->format('M'),
            'profit' => (float) ($revenue - $expenses),
        ];
    }
    return $result;
}

public function downloadPdf(?string $period = null): \Illuminate\Http\Response
{
    $data = $this->profitLoss($period);

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.profit-loss', ['report' => $data]);
    return $pdf->download('profit-loss-report.pdf');
}

public function generateWhatsAppLink(?string $period = null): array
{
    $data = $this->profitLoss($period);
    $company = auth()->user()->company;

    $phone = $company->phone ?? null;

    if (!$phone) {
        throw new \Exception('Company phone number is not set.');
    }

    $message = "تقرير الأرباح والخسائر\n"
        . "الفترة: " . ($period ?? 'كل الفترات') . "\n"
        . "الإيرادات: {$data['income']}\n"
        . "المصروفات: {$data['expenses']}\n"
        . "صافي الربح: {$data['profit']}";

    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

    return [
        'whatsapp_link' => "https://wa.me/{$cleanPhone}?text=" . urlencode($message),
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
