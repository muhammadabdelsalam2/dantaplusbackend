<?php

namespace App\Repositories\Clinic\Billing;

use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\ClinicInvoiceItem;
use App\Models\ClinicPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClinicBillingRepositoryInterface
{
    public function paginateInvoices(int $clinicId, array $filters): LengthAwarePaginator;

    public function findInvoice(int $clinicId, int $invoiceId): ?ClinicInvoice;

    public function createInvoice(array $data): ClinicInvoice;

    public function updateInvoice(ClinicInvoice $invoice, array $data): ClinicInvoice;

    public function createInvoiceItem(array $data): ClinicInvoiceItem;

    public function createPayment(array $data): ClinicPayment;

    public function paginatePayments(int $clinicId, array $filters): LengthAwarePaginator;

    public function paginateExpenses(int $clinicId, array $filters): LengthAwarePaginator;

    public function createExpense(array $data): ClinicExpense;

    public function expenseSummary(int $clinicId, array $filters): array;

    public function profitLossSummary(int $clinicId, array $filters): array;

    public function listExpenseCategories(int $clinicId): Collection;

    public function findExpenseCategory(int $clinicId, int $categoryId): ?ClinicExpenseCategory;

    public function firstOrCreateExpenseCategory(array $attributes, array $values = []): ClinicExpenseCategory;
}
