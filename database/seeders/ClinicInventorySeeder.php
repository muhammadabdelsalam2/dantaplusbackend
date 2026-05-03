<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Models\MaterialProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClinicInventorySeeder extends Seeder
{
    private const CLINIC_ID = 26;

    public function run(): void
    {
        // Step 1: Fix any products with null barcode
        MaterialProduct::whereNull('barcode')
            ->each(function (MaterialProduct $product) {
                $product->update([
                    'barcode' => $this->generateBarcode($product->id),
                ]);
            });

        // Step 2: Seed inventory for clinic 26 using first 10 products
        $products = MaterialProduct::with('company')->take(10)->get();

        foreach ($products as $product) {
            $quantity = rand(10, 100);
            $minimumStock = rand(5, 20);

            $item = InventoryItem::withoutGlobalScopes()->updateOrCreate(
                [
                    'clinic_id'  => self::CLINIC_ID,
                    'product_id' => $product->id,
                ],
                [
                    'company_id'          => $product->company_id,
                    'barcode'             => $product->barcode,
                    'product_name'        => $product->name,
                    'category_name'       => $product->category,
                    'description'         => $product->description,
                    'quantity'            => $quantity,
                    'minimum_stock_level' => $minimumStock,
                    'reorder_quantity'    => (int) ($minimumStock * 1.5),
                    'unit'                => 'piece',
                    'supplier'            => $product->company?->name,
                    'status'              => $this->resolveStatus($quantity, $minimumStock),
                    'last_updated_at'     => now(),
                ]
            );

            // Log only if just created (wasRecentlyCreated)
            if ($item->wasRecentlyCreated) {
                InventoryLog::withoutGlobalScopes()->create([
                    'inventory_item_id' => $item->id,
                    'company_id'        => $item->company_id,
                    'clinic_id'         => $item->clinic_id,
                    'user_id'           => null,
                    'action'            => 'seed_stock',
                    'amount'            => $quantity,
                    'reason'            => 'Initial clinic inventory seeded',
                    'created_at'        => now(),
                ]);
            }
        }

        $this->command->info(' Clinic 26 inventory seeded for ' . $products->count() . ' products.');
    }

    private function resolveStatus(int $quantity, int $minimumStock): string
    {
        if ($quantity <= 0) return 'out_of_stock';
        if ($quantity <= $minimumStock) return 'low_stock';
        return 'in_stock';
    }

    private function generateBarcode(int $productId): string
    {
        // EAN-13 style: prefix 200 + padded product id + random suffix + timestamp hash
        return '200' . str_pad($productId, 4, '0', STR_PAD_LEFT) . strtoupper(Str::random(6));
    }
}
