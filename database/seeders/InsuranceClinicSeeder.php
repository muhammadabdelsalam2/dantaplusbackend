<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\InsuranceCompany;
use App\Models\InsurancePriceList;
use App\Models\InsurancePriceListItem;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InsuranceClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->orderBy('id')->first();

        if (! $clinic) {
            $this->command?->warn('InsuranceClinicSeeder skipped: no clinic found.');

            return;
        }

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->keyBy('slug');

        $definitions = [
            [
                'company' => [
                    'name' => 'Cigna Dental',
                    'code' => 'CIGNA',
                    'coverage' => '80% preventive, 60% restorative',
                    'payment_terms' => '30 days',
                ],
                'price_list' => [
                    'name' => 'Cigna Syndicate 2024',
                    'year' => 2024,
                    'notes' => 'Cigna syndicate prices for clinic billing reference.',
                ],
                'items' => [
                    ['service_slug' => 'initial-consultation', 'service_name' => 'Initial Consultation', 'item_code' => 'CIG-CONS', 'price' => 220],
                    ['service_slug' => 'dental-cleaning', 'service_name' => 'Dental Cleaning', 'item_code' => 'CIG-CLN', 'price' => 260],
                    ['service_slug' => 'composite-filling', 'service_name' => 'Composite Filling', 'item_code' => 'CIG-FILL', 'price' => 390],
                ],
            ],
            [
                'company' => [
                    'name' => 'MetLife Dental',
                    'code' => 'METLIFE',
                    'coverage' => '75% preventive, 50% restorative',
                    'payment_terms' => '45 days',
                ],
                'price_list' => [
                    'name' => 'MetLife Syndicate 2024',
                    'year' => 2024,
                    'notes' => 'MetLife reference price list.',
                ],
                'items' => [
                    ['service_slug' => 'follow-up-visit', 'service_name' => 'Follow-up Visit', 'item_code' => 'MET-FUP', 'price' => 130],
                    ['service_slug' => 'root-canal-treatment', 'service_name' => 'Root Canal Treatment', 'item_code' => 'MET-RCT', 'price' => 1500],
                    ['service_slug' => 'simple-extraction', 'service_name' => 'Simple Extraction', 'item_code' => 'MET-EXT', 'price' => 420],
                ],
            ],
            [
                'company' => [
                    'name' => 'Delta Dental',
                    'code' => 'DELTA',
                    'coverage' => '90% preventive, 70% major services',
                    'payment_terms' => '15 days',
                ],
                'price_list' => [
                    'name' => 'Delta Syndicate 2024',
                    'year' => 2024,
                    'notes' => 'Delta Dental yearly syndicate list.',
                ],
                'items' => [
                    ['service_slug' => 'fluoride-application', 'service_name' => 'Fluoride Application', 'item_code' => 'DEL-FLU', 'price' => 150],
                    ['service_slug' => 'dental-crown', 'service_name' => 'Dental Crown', 'item_code' => 'DEL-CRN', 'price' => 2200],
                    ['service_slug' => 'teeth-whitening', 'service_name' => 'Teeth Whitening', 'item_code' => 'DEL-WHT', 'price' => 1750],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $priceList = InsurancePriceList::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $definition['price_list']['name'],
                    'year' => $definition['price_list']['year'],
                ],
                [
                    'notes' => $definition['price_list']['notes'],
                    'is_active' => true,
                ]
            );

            foreach ($definition['items'] as $item) {
                $service = $services->get($item['service_slug']);

                InsurancePriceListItem::query()->updateOrCreate(
                    [
                        'insurance_price_list_id' => $priceList->id,
                        'item_code' => $item['item_code'],
                    ],
                    [
                        'service_id' => $service?->id,
                        'service_name' => $service?->name ?? $item['service_name'],
                        'price' => $item['price'],
                    ]
                );
            }

            InsuranceCompany::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $definition['company']['name'],
                ],
                [
                    'code' => $definition['company']['code'],
                    'coverage' => $definition['company']['coverage'],
                    'payment_terms' => $definition['company']['payment_terms'],
                    'syndicate_price_list_id' => $priceList->id,
                    'is_active' => true,
                ]
            );
        }

        $patient = Patient::query()
            ->where('clinic_id', $clinic->id)
            ->orderBy('id')
            ->first();

        if (! $patient) {
            $patientUser = User::query()->firstOrCreate(
                ['email' => 'insurance.patient.' . $clinic->id . '@dentaplus.local'],
                [
                    'clinic_id' => $clinic->id,
                    'name' => 'Insurance Demo Patient',
                    'username' => 'insdemo' . $clinic->id,
                    'phone' => '0100000' . str_pad((string) $clinic->id, 4, '0', STR_PAD_LEFT),
                    'password' => bcrypt(Str::random(12)),
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                    'role' => 'patient',
                ]
            );

            if (method_exists($patientUser, 'syncRoles')) {
                $patientUser->syncRoles(['patient']);
            }

            $patient = Patient::query()->create([
                'user_id' => $patientUser->id,
                'clinic_id' => $clinic->id,
                'patient_number' => 'PID-INS-' . str_pad((string) $clinic->id, 4, '0', STR_PAD_LEFT),
                'phone' => $patientUser->phone,
                'insurance_provider' => 'Cigna Dental',
                'insurance_number' => 'INS-' . str_pad((string) $clinic->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        $companies = InsuranceCompany::query()
            ->where('clinic_id', $clinic->id)
            ->orderBy('id')
            ->take(2)
            ->get();

        foreach ($companies as $index => $company) {
            $grossAmount = 1000 + ($index * 250);
            $coveragePercentage = $index === 0 ? 80 : 70;
            $insuranceShare = round(($grossAmount * $coveragePercentage) / 100, 2);
            $approvedAmount = $index === 0 ? $insuranceShare : round($insuranceShare * 0.85, 2);
            $status = $index === 0
                ? InsuranceClaim::STATUS_SUBMITTED
                : InsuranceClaim::STATUS_PARTIALLY_APPROVED;

            InsuranceClaim::query()->updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'claim_number' => 'CLM-SEED-' . $clinic->id . '-' . ($index + 1),
                ],
                [
                    'insurance_company_id' => $company->id,
                    'patient_id' => $patient->id,
                    'title' => $index === 0 ? 'Root Canal Claim' : 'Crown Claim',
                    'description' => 'Seeded insurance claim for API testing.',
                    'service_date' => now()->subDays(7 + $index)->toDateString(),
                    'coverage_percentage' => $coveragePercentage,
                    'gross_amount' => $grossAmount,
                    'patient_share_amount' => round($grossAmount - $insuranceShare, 2),
                    'insurance_share_amount' => $insuranceShare,
                    'approved_amount' => $approvedAmount,
                    'paid_amount' => 0,
                    'status' => $status,
                    'notes' => 'Auto-seeded claim example.',
                    'status_notes' => $index === 0 ? 'Ready for payer submission.' : 'Partially approved by insurer.',
                    'submitted_at' => now()->subDays(3),
                    'reviewed_at' => $status === InsuranceClaim::STATUS_PARTIALLY_APPROVED ? now()->subDay() : null,
                ]
            );
        }
    }
}
