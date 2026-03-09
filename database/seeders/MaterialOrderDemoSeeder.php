<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\MaterialCompany;
use App\Models\MaterialOrder;
use App\Models\MaterialOrderItem;
use App\Models\MaterialProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $clinics = $this->seedClinics();
            $companies = $this->seedCompaniesAndProducts();
            $this->seedOrders($clinics, $companies);
        });
    }

    private function seedClinics(): Collection
    {
        $clinics = Clinic::query()->take(15)->get();

        if ($clinics->count() >= 5) {
            return $clinics;
        }

        $needed = 12 - $clinics->count();

        for ($i = 1; $i <= $needed; $i++) {
            $number = $clinics->count() + 1;

            $clinics->push(Clinic::create([
                'name' => 'Clinic ' . $number,
                'owner_name' => 'Dr. Owner ' . $number,
                'email' => 'clinic' . $number . '_' . uniqid() . '@example.com',
                'phone' => '010' . str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                'address' => 'Street ' . rand(1, 50) . ', Building ' . rand(1, 20) . ', Cairo',

                // خليك على قيم ثابتة وآمنة
                'subscription_plan' => 'Basic',
                'payment_method' => 'Manual',
                'status' => 'Active',

                'start_date' => Carbon::now()->subMonths(rand(1, 12)),
                'expiry_date' => Carbon::now()->addMonths(rand(1, 12)),
                'max_users' => rand(3, 25),
                'max_branches' => rand(1, 5),
            ]));
        }

        return $clinics;
    }

    private function seedCompaniesAndProducts(): Collection
    {
        $companies = MaterialCompany::query()->with('products')->take(10)->get();

        $companyCategorySets = [
            ['orthodontic'],
            ['prosthodontics'],
            ['endodontics'],
            ['implantology'],
            ['surgical'],
            ['restorative', 'prosthodontics'],
            ['orthodontic', 'surgical'],
            ['implantology', 'restorative'],
        ];

        $productCategories = [
            'Implants',
            'Abutments',
            'Composite',
            'Burs',
            'Cements',
            'Endo Files',
            'Orthodontic Wires',
            'Sutures',
            'Scalers',
            'Bonding Agents',
        ];

        if ($companies->count() < 5) {
            $needed = 8 - $companies->count();

            for ($i = 1; $i <= $needed; $i++) {
                $number = $companies->count() + 1;

                $company = MaterialCompany::create([
                    'name' => 'Material Company ' . $number,
                    'email' => 'material_company_' . $number . '_' . uniqid() . '@example.com',
                    'commission_percentage' => rand(5, 18),
                    'logo_url' => null,
                    'description' => 'Demo supplier company number ' . $number,
                    'phone' => '011' . str_pad((string) rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'website' => 'https://company' . $number . '.example.com',
                    'country' => 'Egypt',
                    'city' => collect(['Cairo', 'Giza', 'Alexandria', 'Mansoura', 'Minya', 'Assiut'])->random(),
                    'address' => 'Industrial Zone ' . rand(1, 10) . ', Building ' . rand(1, 20),
                    'categories' => $companyCategorySets[array_rand($companyCategorySets)],
                    'status' => rand(1, 10) > 2 ? 'Active' : 'Inactive',
                    'is_featured' => (bool) rand(0, 1),

                    // عندك unsignedTinyInteger فخلّيها integer
                    'rating' => rand(3, 5),
                ]);

                $createdProducts = collect();

                for ($p = 1; $p <= 12; $p++) {
                    $createdProducts->push(MaterialProduct::create([
                        'company_id' => $company->id,
                        'image_url' => null,
                        'name' => $company->name . ' Product ' . $p,
                        'brand' => $company->name,
                        'description' => 'Demo material product ' . $p . ' for ' . $company->name,
                        'category' => $productCategories[array_rand($productCategories)],
                        'price' => rand(50, 2500),
                        'stock' => rand(10, 300),
                        'status' => rand(1, 10) > 2 ? 'Active' : 'Inactive',
                    ]));
                }

                $company->setRelation('products', $createdProducts);
                $companies->push($company);
            }
        }

        $companies->each(function ($company) use ($productCategories) {
            $company->loadMissing('products');

            if ($company->products->count() === 0) {
                for ($p = 1; $p <= 10; $p++) {
                    MaterialProduct::create([
                        'company_id' => $company->id,
                        'image_url' => null,
                        'name' => $company->name . ' Product ' . $p,
                        'brand' => $company->name,
                        'description' => 'Auto-created product ' . $p . ' for ' . $company->name,
                        'category' => $productCategories[array_rand($productCategories)],
                        'price' => rand(50, 2500),
                        'stock' => rand(10, 300),
                        'status' => 'Active',
                    ]);
                }

                $company->load('products');
            }
        });

        return MaterialCompany::query()->with('products')->get();
    }

    private function seedOrders(Collection $clinics, Collection $companies): void
    {
        $statuses = MaterialOrder::STATUSES;
        $paymentMethods = ['Cash', 'Card', 'Bank Transfer', 'Instapay', 'Wallet'];
        $paymentStatuses = ['Pending', 'Paid', 'Failed', 'Refunded'];

        for ($i = 1; $i <= 150; $i++) {
            $clinic = $clinics->random();
            $company = $companies->random();

            $products = MaterialProduct::query()
                ->where('company_id', $company->id)
                ->where('status', 'Active')
                ->inRandomOrder()
                ->take(rand(2, 6))
                ->get();

            if ($products->isEmpty()) {
                continue;
            }

            $order = MaterialOrder::create([
                'order_code' => 'MO-' . now()->format('Ymd') . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'clinic_id' => $clinic->id,
                'supplier_company_id' => $company->id,
                'order_date' => Carbon::now()
                    ->subDays(rand(0, 180))
                    ->subHours(rand(0, 23))
                    ->subMinutes(rand(0, 59)),
                'amount_total' => 0,
                'status' => $statuses[array_rand($statuses)],
                'commission_amount' => 0,
                'notes' => 'Demo material order #' . $i,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'payment_reference' => 'PAY-' . strtoupper(substr(md5(uniqid((string) $i, true)), 0, 10)),
            ]);

            $total = 0;

            foreach ($products as $product) {
                $quantity = rand(1, 8);
                $unitPrice = (float) $product->price;
                $lineTotal = round($quantity * $unitPrice, 2);

                MaterialOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $commissionAmount = round($total * (((float) $company->commission_percentage) / 100), 2);

            $order->update([
                'amount_total' => round($total, 2),
                'commission_amount' => $commissionAmount,
            ]);
        }
    }
}
