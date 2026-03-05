<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\MaterialProduct;
use Illuminate\Support\Str;

class MaterialOrderSeeder extends Seeder
{
    public function run(): void
    {
        // استخدم المنتج الموجود عندك (id=2) أو أول منتج متاح
        $product = MaterialProduct::query()->find(2) ?? MaterialProduct::query()->first();

        if (!$product) {
            // ما فيش منتجات -> ما نقدرش نعمل items
            $this->command?->warn('No material_products found. Seed products first.');
            return;
        }

        $unitPrice = (float) $product->price;

        for ($i = 1; $i <= 5; $i++) {

            $quantity = rand(1, 3);
            $lineTotal = $quantity * $unitPrice;

            // ✅ لازم amount_total يتبعت هنا
            $order = MaterialOrder::create([
                'order_code' => 'ORD-' . strtoupper(Str::random(6)),
                'clinic_id' => 1,
                'supplier_company_id' => $product->company_id ?? 2,
                'status' => 'pending',
                'amount_total' => $lineTotal,
            ]);

            MaterialOrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);
        }
    }
}
