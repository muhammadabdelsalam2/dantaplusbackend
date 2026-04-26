<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServicePricingSeeder extends Seeder
{
    public function run(): void
    {
        // Assumption: frontend base-services source is not present in this repo,
        // so this safe default catalog is seeded idempotently until the frontend list is confirmed.
        $catalog = [
            'Diagnostics' => [
                ['name' => 'Initial Consultation', 'slug' => 'initial-consultation', 'base_price' => 250],
                ['name' => 'Follow-up Visit', 'slug' => 'follow-up-visit', 'base_price' => 150],
            ],
            'Preventive' => [
                ['name' => 'Dental Cleaning', 'slug' => 'dental-cleaning', 'base_price' => 300],
                ['name' => 'Fluoride Application', 'slug' => 'fluoride-application', 'base_price' => 180],
            ],
            'Restorative' => [
                ['name' => 'Composite Filling', 'slug' => 'composite-filling', 'base_price' => 450],
                ['name' => 'Dental Crown', 'slug' => 'dental-crown', 'base_price' => 2500],
            ],
            'Endodontics' => [
                ['name' => 'Root Canal Treatment', 'slug' => 'root-canal-treatment', 'base_price' => 1800],
            ],
            'Oral Surgery' => [
                ['name' => 'Simple Extraction', 'slug' => 'simple-extraction', 'base_price' => 500],
                ['name' => 'Surgical Extraction', 'slug' => 'surgical-extraction', 'base_price' => 1200],
            ],
            'Orthodontics' => [
                ['name' => 'Orthodontic Consultation', 'slug' => 'orthodontic-consultation', 'base_price' => 350],
                ['name' => 'Fixed Braces', 'slug' => 'fixed-braces', 'base_price' => 12000],
            ],
            'Cosmetic' => [
                ['name' => 'Teeth Whitening', 'slug' => 'teeth-whitening', 'base_price' => 2000],
                ['name' => 'Dental Veneers', 'slug' => 'dental-veneers', 'base_price' => 3500],
            ],
        ];

        foreach ($catalog as $categoryName => $services) {
            $category = Category::query()->firstOrCreate(
                ['slug' => 'clinic-service-' . Str::slug($categoryName)],
                [
                    'name' => $categoryName,
                    'status' => 'active',
                ]
            );

            foreach ($services as $serviceData) {
                Service::query()->firstOrCreate(
                    ['slug' => $serviceData['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $serviceData['name'],
                        'description' => null,
                        'base_price' => $serviceData['base_price'],
                        'is_base' => true,
                        'created_by_clinic_id' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
