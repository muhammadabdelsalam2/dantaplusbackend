<?php

namespace Database\Seeders;

use App\Models\DentalLab;
use App\Models\LabMaterial;
use Illuminate\Database\Seeder;

class LabMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $lab = DentalLab::query()->first();

        if (! $lab) {
            return;
        }

        $materials = [
            [
                'name' => 'Zirconia Puck (A2)',
                'supplier' => 'Dental Supply Co.',
                'stock' => 15,
                'low_stock_threshold' => 5,
                'cost' => 80,
                'purchase_date' => now()->subDays(20)->toDateString(),
                'expiration_date' => now()->addMonths(10)->toDateString(),
            ],
            [
                'name' => 'PMMA Disc (Clear)',
                'supplier' => 'Modern Dental',
                'stock' => 4,
                'low_stock_threshold' => 10,
                'cost' => 35,
                'purchase_date' => now()->subDays(5)->toDateString(),
                'expiration_date' => null,
            ],
            [
                'name' => 'Wax Disc (Blue)',
                'supplier' => 'Dental Supply Co.',
                'stock' => 25,
                'low_stock_threshold' => 8,
                'cost' => 12,
                'purchase_date' => now()->subDays(40)->toDateString(),
                'expiration_date' => now()->addMonths(2)->toDateString(),
            ],
        ];

        foreach ($materials as $material) {
            LabMaterial::query()->create(array_merge($material, [
                'lab_id' => $lab->id,
            ]));
        }
    }
}
