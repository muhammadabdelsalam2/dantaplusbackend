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
    public function summary(): array
    {
        $revenue = Invoice::query()->where('status', 'paid')->sum('total_amount');
        $expenses = CompanyExpense::query()->sum('amount');

        return [
            'revenue' => (float) $revenue,
            'expenses' => (float) $expenses,
            'net_profit' => (float) ($revenue - $expenses),
        ];
    }

    public function invoices(): array
    {
        return Invoice::query()->latest('id')->get()->map(fn ($invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total_amount' => (float) $invoice->total_amount,
            'issue_date' => optional($invoice->issue_date)?->toDateString(),
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

    public function profitLoss(): array
    {
        $summary = $this->summary();
        return [
            'income' => $summary['revenue'],
            'expenses' => $summary['expenses'],
            'profit' => $summary['net_profit'],
            'generated_at' => now()->toISOString(),
        ];
    }
}
