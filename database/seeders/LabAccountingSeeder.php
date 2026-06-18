<?php

namespace Database\Seeders;

use App\Models\DentalLab;
use App\Models\LabExpenseCategory;
use Illuminate\Database\Seeder;

class LabAccountingSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Materials',
            'Salaries',
            'Utilities',
            'Maintenance',
            'Delivery',
            'Other',
        ];

        DentalLab::query()->select('id')->get()->each(function (DentalLab $lab) use ($categories) {
            foreach ($categories as $category) {
                LabExpenseCategory::query()->updateOrCreate(
                    ['lab_id' => $lab->id, 'name' => $category],
                    ['status' => 'active']
                );
            }
        });
    }
}
