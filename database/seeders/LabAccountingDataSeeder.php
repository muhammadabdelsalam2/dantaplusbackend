<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LabAccountingDataSeeder extends Seeder
{
    /**
     * Seeds full Lab Accounting data for lab_id = 12
     *
     * Prerequisites (must exist in DB):
     *  - dental_labs: id = 12
     *  - clinics:     id = 20  (clinic_id used by lab 12 cases)
     *  - users:       id = 35  (Techie Tom — lab_technician, lab_id = 1... adjust if needed)
     *  - cases:       id = 1 (Completed, clinic 20), id = 2 (Accepted, clinic 20)
     *
     * What this seeder creates:
     *  1. Expense Categories (6 default)
     *  2. Expenses (4 entries across June 2026)
     *  3. Invoices (3 invoices — paid, pending, overdue)
     *  4. Invoice Items linked to case ids 1 & 2
     *  5. Payments (full + partial)
     *  6. Payment Transactions log
     */

    private int $labId      = 12;
    private int $clinicId   = 20;   // clinic used by cases 1 & 2
    private int $techId     = 35;   // Techie Tom
    private int $case1      = 1;    // Completed — Zirconia Crown, teeth 24,25
    private int $case2      = 2;    // Accepted  — Bridge, tooth 21

    public function run(): void
    {
        $this->seedExpenseCategories();
        $this->seedExpenses();
        $this->seedInvoices();
    }

    // ─────────────────────────────────────────────
    // 1. EXPENSE CATEGORIES
    // ─────────────────────────────────────────────
    private function seedExpenseCategories(): void
    {
        $categories = [
            'Materials',
            'Salaries',
            'Utilities',
            'Maintenance',
            'Delivery',
            'Other',
        ];

        foreach ($categories as $name) {
            DB::table('lab_expense_categories')->updateOrInsert(
                ['lab_id' => $this->labId, 'name' => $name],
                ['status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('✅ Expense categories seeded.');
    }

    // ─────────────────────────────────────────────
    // 2. EXPENSES
    // ─────────────────────────────────────────────
    private function seedExpenses(): void
    {
        $materialsId    = $this->categoryId('Materials');
        $salariesId     = $this->categoryId('Salaries');
        $utilitiesId    = $this->categoryId('Utilities');
        $maintenanceId  = $this->categoryId('Maintenance');

        $expenses = [
            [
                'lab_id'                  => $this->labId,
                'lab_expense_category_id' => $materialsId,
                'title'                   => 'Bulk order of Zirconia pucks',
                'amount'                  => 850.00,
                'payment_method'          => 'Bank Transfer',
                'vendor'                  => 'DentalSupply Co.',
                'expense_date'            => '2026-06-12',
                'notes'                   => 'Monthly zirconia restock',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'lab_id'                  => $this->labId,
                'lab_expense_category_id' => $salariesId,
                'title'                   => 'Monthly payroll for technicians',
                'amount'                  => 2500.00,
                'payment_method'          => 'Bank Transfer',
                'vendor'                  => null,
                'expense_date'            => '2026-06-14',
                'notes'                   => 'June 2026 payroll',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'lab_id'                  => $this->labId,
                'lab_expense_category_id' => $utilitiesId,
                'title'                   => 'Electricity and water bill',
                'amount'                  => 350.00,
                'payment_method'          => 'Cash',
                'vendor'                  => null,
                'expense_date'            => '2026-06-16',
                'notes'                   => null,
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
            [
                'lab_id'                  => $this->labId,
                'lab_expense_category_id' => $maintenanceId,
                'title'                   => 'Milling machine annual service',
                'amount'                  => 500.00,
                'payment_method'          => 'Cash',
                'vendor'                  => 'TechService Ltd.',
                'expense_date'            => '2026-06-02',
                'notes'                   => 'Annual CAD/CAM maintenance contract',
                'created_at'              => now(),
                'updated_at'              => now(),
            ],
        ];

        // Avoid duplicates on re-run
        foreach ($expenses as $expense) {
            $exists = DB::table('lab_expenses')
                ->where('lab_id', $this->labId)
                ->where('title', $expense['title'])
                ->where('expense_date', $expense['expense_date'])
                ->exists();

            if (! $exists) {
                DB::table('lab_expenses')->insert($expense);
            }
        }

        $this->command->info('✅ Expenses seeded (4 entries, total $4,200).');
    }

    // ─────────────────────────────────────────────
    // 3. INVOICES + ITEMS + PAYMENTS
    // ─────────────────────────────────────────────
    private function seedInvoices(): void
    {
        // ── Invoice 1: PAID ─────────────────────
        // Linked to case 1 (Completed — Zirconia Crown, teeth 24,25)
        $inv1Id = $this->createInvoice([
            'lab_id'         => $this->labId,
            'clinic_id'      => $this->clinicId,
            'doctor_id'      => null,
            'invoice_number' => 'LI-001',
            'issue_date'     => '2026-06-16',
            'due_date'       => '2026-07-16',
            'subtotal'       => 120.00,
            'tax'            => 0.00,
            'discount'       => 0.00,
            'total'          => 120.00,
            'paid_amount'    => 120.00,
            'remaining_amount' => 0.00,
            'status'         => 'paid',
            'notes'          => null,
            'period'         => '2026-06',
        ]);

        $this->createInvoiceItem($inv1Id, [
            'case_id'       => $this->case1,
            'service_name'  => 'Zirconia Crown',
            'teeth_numbers' => json_encode([24, 25]),
            'quantity'      => 1,
            'unit_price'    => 120.00,
            'total'         => 120.00,
            'technician_id' => $this->techId,
            'material_type' => 'zirconia',
            'material_cost' => 45.00,
        ]);

        $pay1Id = $this->createPayment($inv1Id, [
            'amount'                => 120.00,
            'method'                => 'Bank Transfer',
            'transaction_reference' => 'BT-2026-001',
            'paid_at'               => '2026-06-16',
            'notes'                 => 'Full payment received',
            'status'                => 'paid',
        ]);

        $this->createTransaction($inv1Id, $pay1Id, 120.00, 'Bank Transfer');

        // ── Invoice 2: PENDING ───────────────────
        // Linked to case 2 (Accepted — Bridge, tooth 21)
        $inv2Id = $this->createInvoice([
            'lab_id'           => $this->labId,
            'clinic_id'        => $this->clinicId,
            'doctor_id'        => null,
            'invoice_number'   => 'LI-002',
            'issue_date'       => '2026-06-17',
            'due_date'         => '2026-07-17',
            'subtotal'         => 200.00,
            'tax'              => 0.00,
            'discount'         => 0.00,
            'total'            => 200.00,
            'paid_amount'      => 0.00,
            'remaining_amount' => 200.00,
            'status'           => 'pending',
            'notes'            => 'Awaiting clinic payment',
            'period'           => '2026-06',
        ]);

        $this->createInvoiceItem($inv2Id, [
            'case_id'       => $this->case2,
            'service_name'  => 'PFM Bridge',
            'teeth_numbers' => json_encode([21]),
            'quantity'      => 1,
            'unit_price'    => 200.00,
            'total'         => 200.00,
            'technician_id' => $this->techId,
            'material_type' => 'pfm',
            'material_cost' => 60.00,
        ]);

        // ── Invoice 3: OVERDUE ───────────────────
        // Manual invoice with no linked case — overdue from May
        $inv3Id = $this->createInvoice([
            'lab_id'           => $this->labId,
            'clinic_id'        => $this->clinicId,
            'doctor_id'        => null,
            'invoice_number'   => 'LI-003',
            'issue_date'       => '2026-05-01',
            'due_date'         => '2026-05-31',
            'subtotal'         => 340.00,
            'tax'              => 0.00,
            'discount'         => 0.00,
            'total'            => 340.00,
            'paid_amount'      => 0.00,
            'remaining_amount' => 340.00,
            'status'           => 'overdue',
            'notes'            => 'May services — unpaid',
            'period'           => '2026-05',
        ]);

        $this->createInvoiceItem($inv3Id, [
            'case_id'       => null,
            'service_name'  => 'E-Max Veneer',
            'teeth_numbers' => json_encode([11, 21]),
            'quantity'      => 2,
            'unit_price'    => 170.00,
            'total'         => 340.00,
            'technician_id' => $this->techId,
            'material_type' => 'emax',
            'material_cost' => 80.00,
        ]);

        $this->command->info('✅ Invoices seeded: LI-001 (paid $120), LI-002 (pending $200), LI-003 (overdue $340).');
        $this->command->info('✅ Payments seeded: 1 full payment on LI-001.');
        $this->command->info('');
        $this->command->info('📊 Expected Summary (June 2026):');
        $this->command->info('   monthly_income    = $120.00  (paid payment in June)');
        $this->command->info('   monthly_expenses  = $4,200.00');
        $this->command->info('   monthly_profit    = -$4,080.00');
        $this->command->info('   total_outstanding = $540.00  (LI-002 $200 + LI-003 $340)');
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    private function createInvoice(array $data): int
    {
        $exists = DB::table('lab_invoices')
            ->where('lab_id', $data['lab_id'])
            ->where('invoice_number', $data['invoice_number'])
            ->value('id');

        if ($exists) {
            return $exists;
        }

        return DB::table('lab_invoices')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function createInvoiceItem(int $invoiceId, array $data): void
    {
        $exists = DB::table('lab_invoice_items')
            ->where('lab_invoice_id', $invoiceId)
            ->where('service_name', $data['service_name'])
            ->exists();

        if (! $exists) {
            DB::table('lab_invoice_items')->insert(array_merge($data, [
                'lab_invoice_id' => $invoiceId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]));
        }
    }

    private function createPayment(int $invoiceId, array $data): int
    {
        $exists = DB::table('lab_payments')
            ->where('lab_invoice_id', $invoiceId)
            ->where('transaction_reference', $data['transaction_reference'] ?? null)
            ->value('id');

        if ($exists) {
            return $exists;
        }

        return DB::table('lab_payments')->insertGetId(array_merge($data, [
            'lab_id'          => $this->labId,
            'lab_invoice_id'  => $invoiceId,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]));
    }

    private function createTransaction(int $invoiceId, int $paymentId, float $amount, string $method): void
    {
        $exists = DB::table('lab_payment_transactions')
            ->where('lab_invoice_id', $invoiceId)
            ->where('lab_payment_id', $paymentId)
            ->exists();

        if (! $exists) {
            DB::table('lab_payment_transactions')->insert([
                'lab_id'          => $this->labId,
                'lab_invoice_id'  => $invoiceId,
                'lab_payment_id'  => $paymentId,
                'amount'          => $amount,
                'type'            => 'payment',
                'payment_method'  => $method,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    private function categoryId(string $name): int
    {
        return DB::table('lab_expense_categories')
            ->where('lab_id', $this->labId)
            ->where('name', $name)
            ->value('id');
    }
}
