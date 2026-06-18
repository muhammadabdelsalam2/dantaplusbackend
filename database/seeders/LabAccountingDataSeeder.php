<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LabAccountingDataSeeder extends Seeder
{
    /**
     * Seeds full Lab Accounting data for lab_id = 12
     *
     * Prerequisites:
     *  - dental_labs: id = 12
     *  - clinics:     id = 20
     *  - users:       id = 35 (Techie Tom — lab_technician)
     *  - cases:       id = 1 (Completed, clinic 20), id = 2 (Accepted, clinic 20)
     *
     * Tables written:
     *  lab_expense_categories, lab_expenses,
     *  lab_invoices, lab_invoice_items, lab_invoice_item_materials,
     *  lab_payments, lab_payment_transactions
     */

    private int $labId    = 12;
    private int $clinicId = 20;
    private int $techId   = 35;   // Techie Tom
    private int $case1    = 1;    // Completed — Zirconia Crown, teeth 24,25
    private int $case2    = 2;    // Accepted  — Bridge, tooth 21

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
        foreach (['Materials','Salaries','Utilities','Maintenance','Delivery','Other'] as $name) {
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
        $matId  = $this->categoryId('Materials');
        $salId  = $this->categoryId('Salaries');
        $utlId  = $this->categoryId('Utilities');
        $mntId  = $this->categoryId('Maintenance');

        $rows = [
            [$mntId,  'Milling machine annual service',   500.00,  'Cash',          'TechService Ltd.',  '2026-06-02'],
            [$matId,  'Bulk order of Zirconia pucks',     850.00,  'Bank Transfer',  'DentalSupply Co.',  '2026-06-12'],
            [$salId,  'Monthly payroll for technicians', 2500.00,  'Bank Transfer',  null,                '2026-06-14'],
            [$utlId,  'Electricity and water bill',       350.00,  'Cash',           null,                '2026-06-16'],
        ];

        foreach ($rows as [$catId, $title, $amount, $method, $vendor, $date]) {
            $exists = DB::table('lab_expenses')
                ->where('lab_id', $this->labId)
                ->where('title', $title)
                ->where('expense_date', $date)
                ->exists();

            if (! $exists) {
                DB::table('lab_expenses')->insert([
                    'lab_id'                  => $this->labId,
                    'lab_expense_category_id' => $catId,
                    'title'                   => $title,
                    'amount'                  => $amount,
                    'payment_method'          => $method,
                    'vendor'                  => $vendor,
                    'expense_date'            => $date,
                    'notes'                   => null,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }
        }

        $this->command->info('✅ Expenses seeded — 4 entries, total $4,200.');
    }

    // ─────────────────────────────────────────────
    // 3. INVOICES + ITEMS + MATERIALS + PAYMENTS
    // ─────────────────────────────────────────────
    private function seedInvoices(): void
    {
        // ── INVOICE 1: PAID ($120) ───────────────
        $inv1 = $this->createInvoice([
            'invoice_number'   => 'LI-001',
            'period_month'     => '2026-06-01',
            'group_by'         => 'clinic',
            'group_key'        => '12_20_2026-06',
            'issue_date'       => '2026-06-16',
            'due_date'         => '2026-07-16',
            'subtotal'         => 120.00,
            'tax'              => 0.00,
            'discount'         => 0.00,
            'total_amount'     => 120.00,
            'paid_amount'      => 120.00,
            'remaining_amount' => 0.00,
            'status'           => 'paid',
            'notes'            => null,
        ]);

        $item1 = $this->createInvoiceItem($inv1, [
            'case_id'         => $this->case1,
            'case_number'     => 'CASE-20260312-MDY1Q1',
            'patient_name'    => 'Patient A',
            'lab_service_id'  => null,
            'service_name'    => 'Zirconia Crown',
            'teeth_numbers'   => json_encode([24, 25]),
            'fdi_teeth_numbers' => json_encode([24, 25]),
            'quantity'        => 1,
            'unit_price'      => 120.00,
            'subtotal'        => 120.00,
            'tax'             => 0.00,
            'discount'        => 0.00,
            'total'           => 120.00,
            'technician_id'   => $this->techId,
            'dental_chart'    => json_encode(['treated' => [24, 25]]),
        ]);

        $this->createItemMaterial($item1, [
            'material_name' => 'Zirconia Puck',
            'material_type' => 'zirconia',
            'quantity'      => 1,
            'unit_cost'     => 45.00,
            'total_cost'    => 45.00,
        ]);

        $pay1 = $this->createPayment($inv1, [
            'amount'                => 120.00,
            'method'                => 'Bank Transfer',
            'status'                => 'paid',
            'transaction_reference' => 'BT-2026-001',
            'paid_at'               => '2026-06-16',
            'recorded_by'           => $this->techId,
            'notes'                 => 'Full payment received',
        ]);

        $this->createTransaction($inv1, $pay1, 120.00, 'Bank Transfer');

        // ── INVOICE 2: PENDING ($200) ────────────
        $inv2 = $this->createInvoice([
            'invoice_number'   => 'LI-002',
            'period_month'     => '2026-06-01',
            'group_by'         => 'clinic',
            'group_key'        => '12_20_2026-06_b',
            'issue_date'       => '2026-06-17',
            'due_date'         => '2026-07-17',
            'subtotal'         => 200.00,
            'tax'              => 0.00,
            'discount'         => 0.00,
            'total_amount'     => 200.00,
            'paid_amount'      => 0.00,
            'remaining_amount' => 200.00,
            'status'           => 'pending',
            'notes'            => 'Awaiting clinic payment',
        ]);

        $item2 = $this->createInvoiceItem($inv2, [
            'case_id'           => $this->case2,
            'case_number'       => 'CASE-20260312-EEW1FK',
            'patient_name'      => 'Patient B',
            'lab_service_id'    => null,
            'service_name'      => 'PFM Bridge',
            'teeth_numbers'     => json_encode([21]),
            'fdi_teeth_numbers' => json_encode([21]),
            'quantity'          => 1,
            'unit_price'        => 200.00,
            'subtotal'          => 200.00,
            'tax'               => 0.00,
            'discount'          => 0.00,
            'total'             => 200.00,
            'technician_id'     => $this->techId,
            'dental_chart'      => json_encode(['treated' => [21]]),
        ]);

        $this->createItemMaterial($item2, [
            'material_name' => 'PFM Framework',
            'material_type' => 'pfm',
            'quantity'      => 1,
            'unit_cost'     => 60.00,
            'total_cost'    => 60.00,
        ]);

        // ── INVOICE 3: OVERDUE ($340) ────────────
        $inv3 = $this->createInvoice([
            'invoice_number'   => 'LI-003',
            'period_month'     => '2026-05-01',
            'group_by'         => 'clinic',
            'group_key'        => '12_20_2026-05',
            'issue_date'       => '2026-05-01',
            'due_date'         => '2026-05-31',
            'subtotal'         => 340.00,
            'tax'              => 0.00,
            'discount'         => 0.00,
            'total_amount'     => 340.00,
            'paid_amount'      => 0.00,
            'remaining_amount' => 340.00,
            'status'           => 'overdue',
            'notes'            => 'May services — unpaid',
        ]);

        $item3 = $this->createInvoiceItem($inv3, [
            'case_id'           => null,
            'case_number'       => null,
            'patient_name'      => 'Liam Smith',
            'lab_service_id'    => null,
            'service_name'      => 'E-Max Veneer',
            'teeth_numbers'     => json_encode([11, 21]),
            'fdi_teeth_numbers' => json_encode([11, 21]),
            'quantity'          => 2,
            'unit_price'        => 170.00,
            'subtotal'          => 340.00,
            'tax'               => 0.00,
            'discount'          => 0.00,
            'total'             => 340.00,
            'technician_id'     => $this->techId,
            'dental_chart'      => json_encode(['treated' => [11, 21]]),
        ]);

        $this->createItemMaterial($item3, [
            'material_name' => 'E-Max Press',
            'material_type' => 'emax',
            'quantity'      => 2,
            'unit_cost'     => 40.00,
            'total_cost'    => 80.00,
        ]);

        $this->command->info('✅ Invoices seeded:');
        $this->command->info('   LI-001 → paid      $120.00  (case 1 — Zirconia Crown)');
        $this->command->info('   LI-002 → pending   $200.00  (case 2 — PFM Bridge)');
        $this->command->info('   LI-003 → overdue   $340.00  (E-Max Veneer — May)');
        $this->command->info('');
        $this->command->info('📊 Expected /summary?month=2026-06:');
        $this->command->info('   monthly_income    = $120.00');
        $this->command->info('   monthly_expenses  = $4,200.00');
        $this->command->info('   monthly_profit    = -$4,080.00');
        $this->command->info('   total_outstanding = $540.00');
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    private function createInvoice(array $data): int
    {
        $existing = DB::table('lab_invoices')
            ->where('lab_id', $this->labId)
            ->where('invoice_number', $data['invoice_number'])
            ->value('id');

        if ($existing) return $existing;

        return DB::table('lab_invoices')->insertGetId(array_merge([
            'lab_id'    => $this->labId,
            'clinic_id' => $this->clinicId,
            'doctor_id' => null,
        ], $data, ['created_at' => now(), 'updated_at' => now()]));
    }

    private function createInvoiceItem(int $invoiceId, array $data): int
    {
        $existing = DB::table('lab_invoice_items')
            ->where('lab_invoice_id', $invoiceId)
            ->where('service_name', $data['service_name'])
            ->value('id');

        if ($existing) return $existing;

        return DB::table('lab_invoice_items')->insertGetId(array_merge(
            ['lab_invoice_id' => $invoiceId],
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));
    }

    private function createItemMaterial(int $itemId, array $data): void
    {
        $exists = DB::table('lab_invoice_item_materials')
            ->where('lab_invoice_item_id', $itemId)
            ->where('material_name', $data['material_name'])
            ->exists();

        if (! $exists) {
            DB::table('lab_invoice_item_materials')->insert(array_merge(
                ['lab_invoice_item_id' => $itemId, 'lab_material_id' => null],
                $data,
                ['created_at' => now(), 'updated_at' => now()]
            ));
        }
    }

    private function createPayment(int $invoiceId, array $data): int
    {
        $existing = DB::table('lab_payments')
            ->where('lab_invoice_id', $invoiceId)
            ->where('transaction_reference', $data['transaction_reference'] ?? '')
            ->value('id');

        if ($existing) return $existing;

        return DB::table('lab_payments')->insertGetId(array_merge(
            ['lab_id' => $this->labId, 'lab_invoice_id' => $invoiceId],
            $data,
            ['created_at' => now(), 'updated_at' => now()]
        ));
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
