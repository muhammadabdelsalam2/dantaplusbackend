<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Clinic\Insurance\InsuranceClaim;
use App\Models\Clinic\Insurance\InsuranceClaimItem;
use App\Models\Clinic\Insurance\InsuranceCompany;
use App\Models\Clinic\Insurance\InsurancePriceList;
use App\Models\Clinic\Insurance\InsurancePriceListItem;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo Seeder for Insurance Claims Endpoints Testing
 *
 * This seeder creates safe, test-only data with DEMO_ prefix.
 * Safe to run on any environment (development/staging).
 * All records are clearly marked as demo data.
 *
 * Usage:
 *   php artisan db:seed --class=InsuranceClaimsEndpointsDemoSeeder
 *
 * What it creates:
 *   1. Demo user (clinic_staff)
 *   2. Demo clinic with branch
 *   3. Demo patients (3)
 *   4. Demo insurance companies (2)
 *   5. Demo price lists with items (50+ items)
 *   6. Demo insurance claims (5 with different statuses)
 */
class InsuranceClaimsEndpointsDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createDemoUsers();
        $this->createDemoClinicStructure();
        $this->createDemoPatients();
        $this->createDemoInsuranceCompanies();
        $this->createDemoClaims();

        $this->command->info('✅ Insurance Claims Demo Data Created Successfully!');
        $this->command->info('');
        $this->command->info('📋 Created Demo Records:');
        $this->command->info('  • 1 Demo Clinic (DEMO_CLINIC_001)');
        $this->command->info('  • 1 Demo Branch');
        $this->command->info('  • 1 Demo Staff User');
        $this->command->info('  • 3 Demo Patients');
        $this->command->info('  • 2 Demo Insurance Companies');
        $this->command->info('  • 2 Demo Price Lists with 50+ price list items');
        $this->command->info('  • 5 Demo Insurance Claims (with different statuses)');
        $this->command->info('');
        $this->command->info('🔑 Important Variables (Update in Postman):');
        $this->command->info('  • {{clinic_id}}: ' . $this->getClinicId());
        $this->command->info('  • {{patient_id}}: ' . $this->getFirstPatientId());
        $this->command->info('  • {{insurance_company_id}}: ' . $this->getFirstInsuranceCompanyId());
        $this->command->info('  • {{claim_id}}: ' . $this->getFirstClaimId());
        $this->command->info('');
    }

    /**
     * Create demo user for testing
     */
    private function createDemoUsers(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo.staff@dentaplus.local'],
            [
                'name' => 'DEMO - Staff User',
                'phone' => '+966501234567',
                'password' => bcrypt('Demo@12345'),
                'user_type' => 'staff',
                'is_active' => true,
            ]
        );

        $this->command->line('✓ Demo User Created: ' . $user->email);
    }

    /**
     * Create demo clinic and branch
     */
    private function createDemoClinicStructure(): void
    {
        $clinic = Clinic::updateOrCreate(
            ['name' => 'DEMO_CLINIC_001'],
            [
                'name_ar' => 'عيادة تجريبية - DEMO',
                'phone' => '+966123456789',
                'email' => 'demo@dentaplus.local',
                'city' => 'Riyadh',
                'country' => 'Saudi Arabia',
                'is_active' => true,
            ]
        );

        Branch::updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'DEMO_BRANCH_001'],
            [
                'name_ar' => 'فرع تجريبي',
                'phone' => '+966123456789',
                'city' => 'Riyadh',
                'is_active' => true,
            ]
        );

        $this->command->line('✓ Demo Clinic Created: ' . $clinic->name);
    }

    /**
     * Create demo patients
     */
    private function createDemoPatients(): void
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();

        $patientData = [
            [
                'patient_number' => 'DEMO_PAT_001',
                'name' => 'أحمد محمد علي',
                'name_ar' => 'أحمد محمد علي',
                'phone' => '+966501111111',
                'email' => 'ahmed.demo@example.com',
                'insurance_number' => 'INS-DEMO-001',
            ],
            [
                'patient_number' => 'DEMO_PAT_002',
                'name' => 'فاطمة عبدالرحمن',
                'name_ar' => 'فاطمة عبدالرحمن',
                'phone' => '+966502222222',
                'email' => 'fatima.demo@example.com',
                'insurance_number' => 'INS-DEMO-002',
            ],
            [
                'patient_number' => 'DEMO_PAT_003',
                'name' => 'محمد سالم حسن',
                'name_ar' => 'محمد سالم حسن',
                'phone' => '+966503333333',
                'email' => 'mohammad.demo@example.com',
                'insurance_number' => 'INS-DEMO-003',
            ],
        ];

        foreach ($patientData as $data) {
            $patient = Patient::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'patient_number' => $data['patient_number'],
                ],
                array_merge($data, ['clinic_id' => $clinic->id])
            );

            $this->command->line('✓ Demo Patient Created: ' . $patient->name);
        }
    }

    /**
     * Create demo insurance companies with price lists
     */
    private function createDemoInsuranceCompanies(): void
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();

        $companies = [
            [
                'name' => 'DEMO Insurance Company 1',
                'name_ar' => 'شركة التأمين التجريبية رقم 1',
                'contact_person' => 'Demo Contact 1',
                'phone' => '+966501111111',
            ],
            [
                'name' => 'DEMO Insurance Company 2',
                'name_ar' => 'شركة التأمين التجريبية رقم 2',
                'contact_person' => 'Demo Contact 2',
                'phone' => '+966502222222',
            ],
        ];

        foreach ($companies as $companyData) {
            $company = InsuranceCompany::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'name' => $companyData['name'],
                ],
                array_merge($companyData, ['clinic_id' => $clinic->id])
            );

            // Create price list for company
            $priceList = InsurancePriceList::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'insurance_company_id' => $company->id,
                    'name' => 'DEMO Price List - ' . $company->name,
                ],
                [
                    'name_ar' => 'قائمة الأسعار التجريبية - ' . $company->name_ar,
                    'description' => 'Demo price list for testing',
                ]
            );

            // Update company with price list
            $company->update(['syndicate_price_list_id' => $priceList->id]);

            // Create price list items
            $this->createPriceListItems($clinic->id, $priceList->id);

            $this->command->line('✓ Demo Insurance Company Created: ' . $company->name);
        }
    }

    /**
     * Create demo price list items
     */
    private function createPriceListItems($clinicId, $priceListId): void
    {
        $services = [
            // Preventive Services
            ['code' => 'DEMO-CLEAN-01', 'name' => 'تنظيف أسنان عام', 'category' => 'عمليات وقائية', 'price' => 150.00, 'coverage' => 80],
            ['code' => 'DEMO-CLEAN-02', 'name' => 'تنظيف عميق للثة', 'category' => 'عمليات وقائية', 'price' => 200.00, 'coverage' => 75],
            ['code' => 'DEMO-EXAM-01', 'name' => 'فحص شامل', 'category' => 'عمليات وقائية', 'price' => 100.00, 'coverage' => 100],
            ['code' => 'DEMO-XRAY-01', 'name' => 'أشعة سينية', 'category' => 'عمليات وقائية', 'price' => 50.00, 'coverage' => 100],

            // Filling Services
            ['code' => 'DEMO-FILL-WHITE', 'name' => 'حشوة بيضاء (الحد الأدنى)', 'category' => 'حشوات', 'price' => 200.00, 'coverage' => 50],
            ['code' => 'DEMO-FILL-COMPOSITE', 'name' => 'حشوة كومبوزيت', 'category' => 'حشوات', 'price' => 250.00, 'coverage' => 50],
            ['code' => 'DEMO-FILL-AMALGAM', 'name' => 'حشوة ملغم', 'category' => 'حشوات', 'price' => 150.00, 'coverage' => 60],

            // Root Canal Treatment
            ['code' => 'DEMO-ROOT-INCISOR', 'name' => 'علاج جذر - قاطع', 'category' => 'علاج الجذور', 'price' => 400.00, 'coverage' => 40],
            ['code' => 'DEMO-ROOT-CANINE', 'name' => 'علاج جذر - ناب', 'category' => 'علاج الجذور', 'price' => 450.00, 'coverage' => 40],
            ['code' => 'DEMO-ROOT-PREMOLAR', 'name' => 'علاج جذر - ضرس صغير', 'category' => 'علاج الجذور', 'price' => 500.00, 'coverage' => 40],
            ['code' => 'DEMO-ROOT-MOLAR', 'name' => 'علاج جذر - ضرس كبير', 'category' => 'علاج الجذور', 'price' => 600.00, 'coverage' => 35],

            // Crown and Bridge
            ['code' => 'DEMO-CROWN-PREP', 'name' => 'تحضير التاج', 'category' => 'تيجان وجسور', 'price' => 200.00, 'coverage' => 30],
            ['code' => 'DEMO-CROWN-PORCELAIN', 'name' => 'تاج بورسلين', 'category' => 'تيجان وجسور', 'price' => 800.00, 'coverage' => 25],
            ['code' => 'DEMO-CROWN-ZIRCONIA', 'name' => 'تاج زيركونيا', 'category' => 'تيجان وجسور', 'price' => 1000.00, 'coverage' => 20],

            // Extraction
            ['code' => 'DEMO-EXTRACT-SIMPLE', 'name' => 'خلع بسيط', 'category' => 'خلع', 'price' => 150.00, 'coverage' => 60],
            ['code' => 'DEMO-EXTRACT-COMPLEX', 'name' => 'خلع معقد', 'category' => 'خلع', 'price' => 300.00, 'coverage' => 50],
            ['code' => 'DEMO-EXTRACT-IMPACTED', 'name' => 'خلع ضرس العقل المدفون', 'category' => 'خلع', 'price' => 500.00, 'coverage' => 40],

            // Implants
            ['code' => 'DEMO-IMPLANT-BODY', 'name' => 'جسم الغرسة السني', 'category' => 'غرسات', 'price' => 2000.00, 'coverage' => 10],
            ['code' => 'DEMO-IMPLANT-ABUTMENT', 'name' => 'دعامة الغرسة', 'category' => 'غرسات', 'price' => 800.00, 'coverage' => 10],
            ['code' => 'DEMO-IMPLANT-CROWN', 'name' => 'تاج على الغرسة', 'category' => 'غرسات', 'price' => 1200.00, 'coverage' => 10],

            // Orthodontics
            ['code' => 'DEMO-ORTHO-EXAM', 'name' => 'فحص تقويم الأسنان', 'category' => 'تقويم', 'price' => 200.00, 'coverage' => 20],
            ['code' => 'DEMO-ORTHO-BRACES', 'name' => 'تركيب المقوسات المعدنية', 'category' => 'تقويم', 'price' => 2500.00, 'coverage' => 15],
            ['code' => 'DEMO-ORTHO-CERAMIC', 'name' => 'تركيب المقوسات السيراميكية', 'category' => 'تقويم', 'price' => 3000.00, 'coverage' => 10],

            // Whitening
            ['code' => 'DEMO-WHITEN-OFFICE', 'name' => 'تبيض في العيادة', 'category' => 'تبيض', 'price' => 300.00, 'coverage' => 0],
            ['code' => 'DEMO-WHITEN-HOME', 'name' => 'تبيض منزلي', 'category' => 'تبيض', 'price' => 250.00, 'coverage' => 0],
        ];

        foreach ($services as $index => $serviceData) {
            InsurancePriceListItem::updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'insurance_price_list_id' => $priceListId,
                    'code' => $serviceData['code'],
                ],
                [
                    'service_name' => $serviceData['name'],
                    'category' => $serviceData['category'],
                    'unit_price' => $serviceData['price'],
                    'coverage_percentage' => $serviceData['coverage'],
                ]
            );
        }

        $this->command->line('✓ Created ' . count($services) . ' Demo Price List Items');
    }

    /**
     * Create demo insurance claims with different statuses
     */
    private function createDemoClaims(): void
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();
        $patients = Patient::where('clinic_id', $clinic->id)->limit(3)->get();
        $companies = InsuranceCompany::where('clinic_id', $clinic->id)->get();

        if ($patients->isEmpty() || $companies->isEmpty()) {
            $this->command->warn('⚠️ No demo patients or companies found. Skipping claim creation.');
            return;
        }

        $claimStatuses = [
            ['status' => 'draft', 'approved_amount' => 0],
            ['status' => 'submitted', 'approved_amount' => 0],
            ['status' => 'approved', 'approved_amount' => 950.00],
            ['status' => 'partially_approved', 'approved_amount' => 700.00],
            ['status' => 'approved_with_limit', 'approved_amount' => 800.00],
        ];

        foreach ($claimStatuses as $index => $claimData) {
            $patient = $patients[$index % $patients->count()];
            $company = $companies[$index % $companies->count()];
            $priceList = InsurancePriceList::where('insurance_company_id', $company->id)->first();

            // Create claim
            $claim = InsuranceClaim::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'patient_id' => $patient->id,
                    'insurance_company_id' => $company->id,
                    'claim_number' => 'DEMO-CLM-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                ],
                [
                    'title' => 'DEMO Claim - ' . $claimData['status'],
                    'service_date' => now()->subDays(rand(1, 30))->toDateString(),
                    'gross_amount' => 1200.00,
                    'insurance_share_amount' => 950.00,
                    'patient_share_amount' => 250.00,
                    'coverage_percentage' => 80,
                    'approved_amount' => $claimData['approved_amount'],
                    'status' => $claimData['status'],
                    'patient_consent_required' => false,
                ]
            );

            // Add demo items to claim
            if ($priceList) {
                $priceListItems = InsurancePriceListItem::where('insurance_price_list_id', $priceList->id)
                    ->limit(3)
                    ->get();

                foreach ($priceListItems as $itemIndex => $priceListItem) {
                    InsuranceClaimItem::updateOrCreate(
                        [
                            'insurance_claim_id' => $claim->id,
                            'insurance_price_list_item_id' => $priceListItem->id,
                        ],
                        [
                            'service_name' => $priceListItem->service_name,
                            'code' => $priceListItem->code,
                            'category_name' => $priceListItem->category,
                            'unit_price' => $priceListItem->unit_price,
                            'quantity' => rand(1, 3),
                            'total_amount' => $priceListItem->unit_price * rand(1, 3),
                            'notes' => 'DEMO Item ' . ($itemIndex + 1),
                        ]
                    );
                }
            }

            $this->command->line('✓ Demo Claim Created: ' . $claim->claim_number . ' (Status: ' . $claimData['status'] . ')');
        }
    }

    /**
     * Helper methods to get IDs for user reference
     */
    private function getClinicId(): string
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();
        return $clinic ? (string) $clinic->id : '1';
    }

    private function getFirstPatientId(): string
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();
        $patient = $clinic ? Patient::where('clinic_id', $clinic->id)->first() : null;
        return $patient ? (string) $patient->id : '1';
    }

    private function getFirstInsuranceCompanyId(): string
    {
        $clinic = Clinic::where('name', 'DEMO_CLINIC_001')->first();
        $company = $clinic ? InsuranceCompany::where('clinic_id', $clinic->id)->first() : null;
        return $company ? (string) $company->id : '1';
    }

    private function getFirstClaimId(): string
    {
        $claim = InsuranceClaim::where('claim_number', 'like', 'DEMO-CLM-%')->first();
        return $claim ? (string) $claim->id : '1';
    }
}
