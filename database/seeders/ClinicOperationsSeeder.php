<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Clinic;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\MaterialCompany;
use App\Models\MaterialProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProcurementOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicOperationsSeeder extends Seeder
{
    private const CLINIC_ID = 26;

    public function run(): void
    {
        $clinic = Clinic::query()->find(self::CLINIC_ID);

        if (! $clinic) {
            $this->command?->warn('Clinic 26 was not found. Clinic operations seeder skipped.');
            return;
        }

        DB::transaction(function () use ($clinic) {
            $supplier = MaterialCompany::query()->firstOrCreate(
                ['name' => 'Dental Supply Hub'],
                [
                    'email' => 'supplier26@example.com',
                    'commission_percentage' => 8,
                    'phone' => '01000000026',
                    'status' => 'Active',
                ]
            );

            $endoFiles = $this->firstOrCreateMaterial($supplier->id, [
                'name' => 'Endo Files',
                'brand' => 'Kerr',
                'barcode' => 'ENDO-26-001',
                'description' => 'Flexible endodontic files',
                'category' => 'endodontics',
                'price' => 320,
                'stock' => 200,
                'status' => 'active',
            ]);

            $putty = $this->firstOrCreateMaterial($supplier->id, [
                'name' => 'Impression Putty',
                'brand' => 'Dentsply',
                'barcode' => 'PUTTY-26-001',
                'description' => 'High precision impression material',
                'category' => 'prosthodontics',
                'price' => 185,
                'stock' => 150,
                'status' => 'active',
            ]);

            $gloves = $this->firstOrCreateMaterial($supplier->id, [
                'name' => 'Nitrile Gloves',
                'brand' => 'SafeTouch',
                'barcode' => 'GLOVES-26-001',
                'description' => 'Powder free nitrile gloves',
                'category' => 'restorative',
                'price' => 95,
                'stock' => 400,
                'status' => 'active',
            ]);

            InventoryItem::query()->withoutGlobalScopes()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'product_id' => $endoFiles->id],
                [
                    'company_id' => $supplier->id,
                    'barcode' => $endoFiles->barcode,
                    'product_name' => $endoFiles->name,
                    'category_name' => $endoFiles->category,
                    'description' => $endoFiles->description,
                    'quantity' => 4,
                    'minimum_stock_level' => 5,
                    'reorder_quantity' => 10,
                    'unit' => 'box',
                    'supplier' => $supplier->name,
                    'status' => 'low_stock',
                    'last_updated_at' => now(),
                ]
            );

            InventoryItem::query()->withoutGlobalScopes()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'product_id' => $putty->id],
                [
                    'company_id' => $supplier->id,
                    'barcode' => $putty->barcode,
                    'product_name' => $putty->name,
                    'category_name' => $putty->category,
                    'description' => $putty->description,
                    'quantity' => 6,
                    'minimum_stock_level' => 8,
                    'reorder_quantity' => 12,
                    'unit' => 'kit',
                    'supplier' => $supplier->name,
                    'status' => 'low_stock',
                    'last_updated_at' => now(),
                ]
            );

            $this->seedProcurementOrder($clinic->id, $endoFiles->id, $supplier->id, 'Dental Supply Hub', 8, 320, 'ordered');
            $this->seedProcurementOrder($clinic->id, $putty->id, $supplier->id, 'Dental Supply Hub', 5, 185, 'pending');

            $this->seedOrder($clinic->id, $supplier->id, 'ORD-124', OrderStatus::PENDING_SUPPLIER_CONFIRMATION, 'visa', 'pending_payment', true, [
                ['product' => $endoFiles, 'qty_original' => 6, 'qty_modified' => 8, 'unit_price' => 320, 'unit' => 'box'],
            ], 'Supplier increased quantity based on available stock.');

            $this->seedOrder($clinic->id, $supplier->id, 'ORD-130', OrderStatus::PROCESSING, 'cash', 'pending_cash', false, [
                ['product' => $putty, 'qty_original' => 4, 'qty_modified' => null, 'unit_price' => 185, 'unit' => 'kit'],
            ]);

            $this->seedOrder($clinic->id, $supplier->id, 'ORD-138', OrderStatus::SHIPPED, 'pay_later', 'pending_invoice', false, [
                ['product' => $gloves, 'qty_original' => 10, 'qty_modified' => null, 'unit_price' => 95, 'unit' => 'box'],
            ]);

            $this->seedOrder($clinic->id, $supplier->id, 'ORD-144', OrderStatus::COMPLETED, 'visa', 'paid', false, [
                ['product' => $endoFiles, 'qty_original' => 3, 'qty_modified' => null, 'unit_price' => 320, 'unit' => 'box'],
                ['product' => $putty, 'qty_original' => 2, 'qty_modified' => null, 'unit_price' => 185, 'unit' => 'kit'],
            ]);

            Equipment::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'name' => 'Dental Chair Unit A'],
                ['status' => Equipment::STATUS_OPERATIONAL]
            );
        });
    }

    private function firstOrCreateMaterial(int $supplierId, array $payload): MaterialProduct
    {
        return MaterialProduct::query()->updateOrCreate(
            ['name' => $payload['name'], 'company_id' => $supplierId],
            $payload
        );
    }

    private function seedProcurementOrder(int $clinicId, int $materialId, int $supplierId, string $supplierName, int $qty, float $unitCost, string $status): void
    {
        $order = ProcurementOrder::query()->updateOrCreate(
            ['clinic_id' => $clinicId, 'material_id' => $materialId, 'status' => $status],
            [
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => round($qty * $unitCost, 2),
                'po_number' => 'PO-' . now()->timestamp . '-' . $materialId . '-' . $status,
                'ordered_at' => $status === 'ordered' ? now()->subDay() : null,
                'created_by' => null,
            ]
        );

        $order->update([
            'po_number' => 'PO-' . now()->timestamp . '-' . $order->id,
        ]);
    }

    private function seedOrder(
        int $clinicId,
        int $supplierId,
        string $orderCode,
        string $status,
        string $paymentMethod,
        string $paymentStatus,
        bool $modifiedBySupplier,
        array $items,
        ?string $supplierNote = null
    ): void {
        $order = Order::query()->updateOrCreate(
            ['order_code' => $orderCode],
            [
                'clinic_id' => $clinicId,
                'supplier_company_id' => $supplierId,
                'company_id' => $supplierId,
                'order_date' => now()->subDays(rand(1, 12)),
                'amount_total' => 0,
                'total_amount' => 0,
                'status' => $status,
                'notes' => 'Demo clinic order ' . $orderCode,
                'supplier_note' => $supplierNote,
                'modified_by_supplier' => $modifiedBySupplier,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => 'PAY-' . $orderCode,
            ]
        );

        $total = 0;

        foreach ($items as $itemPayload) {
            $quantity = $itemPayload['qty_original'];
            $lineTotal = round($quantity * $itemPayload['unit_price'], 2);

            OrderItem::query()->updateOrCreate(
                ['order_id' => $order->id, 'product_id' => $itemPayload['product']->id],
                [
                    'item_name' => $itemPayload['product']->name,
                    'unit' => $itemPayload['unit'],
                    'quantity' => $quantity,
                    'qty_original' => $itemPayload['qty_original'],
                    'qty_modified' => $itemPayload['qty_modified'],
                    'unit_price' => $itemPayload['unit_price'],
                    'line_total' => $lineTotal,
                ]
            );

            $total += $lineTotal;
        }

        $order->update([
            'amount_total' => $total,
            'total_amount' => $total,
        ]);
    }
}
