<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaterialProduct;
use Illuminate\Support\Facades\DB;

class FixProductCategorySeeder extends Seeder
{
    // product category => categories table name
    private array $manualMap = [
        'composites'        => 'Composite Materials',
        'impression_materials' => 'Impression Materials',
        'composite'         => 'Composite Materials',
        'scalers'           => 'Surgical Consumables',
        'cements'           => 'Dental Cements',
        'burs'              => 'Surgical Consumables',
        'orthodontic wires' => 'Composite Materials', // مفيش مطابق واضح — عدّل لو عندك
        'abutments'         => 'Surgical Consumables',
        'sutures'           => 'Surgical Consumables',
        'endo files'        => 'Endodontic Files',
        'bonding agents'    => 'Composite Materials',
        'implants'          => 'Surgical Consumables',
        'prosthodontics'    => 'Dental Cements',
    ];

    public function run(): void
    {
        $categories = DB::table('categories')->get()
            ->mapWithKeys(fn($c) => [strtolower(trim($c->name)) => $c->id]);

        $products = MaterialProduct::whereNull('category_id')->get();
        $updated = 0;
        $skipped = [];

        foreach ($products as $product) {
            $raw = strtolower(trim($product->category ?? ''));
            if (!$raw) continue;

            $mappedName = strtolower($this->manualMap[$raw] ?? '');
            $categoryId = $categories[$mappedName] ?? null;

            if ($categoryId) {
                $product->updateQuietly(['category_id' => $categoryId]);
                $updated++;
            } else {
                $skipped[] = "Product #{$product->id} — '{$product->category}'";
            }
        }

        $this->command->info("✅ Updated: {$updated} products");
        if (!empty($skipped)) {
            $this->command->warn('⚠️ Skipped: ' . count($skipped));
            foreach ($skipped as $line) $this->command->line("   {$line}");
        }
    }
}
