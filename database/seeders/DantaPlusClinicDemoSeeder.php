<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CaseModel;
use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicInvoice;
use App\Models\ClinicInvoiceItem;
use App\Models\ClinicLabPartnership;
use App\Models\ClinicPayment;
use App\Models\ClinicTreatment;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Equipment;
use App\Models\InsuranceCompany;
use App\Models\InsurancePriceList;
use App\Models\InsurancePriceListItem;
use App\Models\InventoryItem;
use App\Models\LabGalleryImage;
use App\Models\LabService;
use App\Models\MaintenanceCompany;
use App\Models\MaterialCompany;
use App\Models\MaterialProduct;
use App\Models\OwnerMaintenanceRequest;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientRadiology;
use App\Models\PatientTooth;
use App\Models\ProcurementOrder;
use App\Models\User;
use App\Models\Clinic\Message;
use App\Models\Clinic\MessageLog;
use App\Models\Clinic\MessageTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DantaPlusClinicDemoSeeder extends Seeder
{
    private int $targetClinicId = 26;

    private int $targetAdminId = 64;

    private string $targetAdminEmail = 'clinic.admin@denta.com';

    private string $password = 'password123';

    private array $summary = [
        'doctors_used' => [],
        'doctors_created' => [],
        'staff_used' => [],
        'staff_created' => [],
        'patients_used' => [],
        'patients_created' => [],
        'insurance_companies_used' => [],
        'insurance_companies_created' => [],
        'material_products_used' => [],
        'material_products_created' => [],
        'dental_labs_used' => [],
        'dental_labs_created' => [],
        'maintenance_companies_used' => [],
        'maintenance_companies_created' => [],
        'equipment_used' => [],
        'equipment_created' => [],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $clinic = $this->clinic();
            $admin = $this->admin();
            $doctors = $this->doctors($clinic);
            $staff = $this->staff($clinic);
            $insuranceCompanies = $this->insurance($clinic);
            $patients = $this->patients($clinic, $insuranceCompanies);
            $labs = $this->dentalLabs($clinic, $admin);
            $materials = $this->materials();

            $this->patientsClinicalData($clinic, $patients, $doctors, $labs, $staff);
            $this->billing($clinic, $patients, $doctors, $staff);
            $this->inventoryAndProcurement($clinic, $materials, $admin);
            $this->equipment($clinic, $admin);
            $this->messages($clinic, $admin, $patients, $doctors);
            $this->cart($clinic, $admin, $materials);

            $this->printSummary($clinic, $admin, $doctors, $patients, $labs);
        });
    }

    private function clinic(): Clinic
    {
        $clinic = Clinic::query()->find($this->targetClinicId);

        if (! $clinic) {
            throw new RuntimeException("Target clinic_id={$this->targetClinicId} was not found. Seeder stopped without creating a clinic.");
        }

        return $clinic;
    }

    private function admin(): User
    {
        $admin = User::query()
            ->whereKey($this->targetAdminId)
            ->where('clinic_id', $this->targetClinicId)
            ->where('email', $this->targetAdminEmail)
            ->first();

        if (! $admin) {
            throw new RuntimeException("Existing admin {$this->targetAdminEmail} with user_id={$this->targetAdminId} was not found for clinic_id={$this->targetClinicId}.");
        }

        return $admin;
    }

    private function doctors(Clinic $clinic): Collection
    {
        $existing = User::query()
            ->where('clinic_id', $clinic->id)
            ->where('role', 'doctor')
            ->orderBy('id')
            ->get();

        if ($existing->count() >= 3) {
            $this->summary['doctors_used'] = $existing->pluck('id')->all();
            $this->ensureDoctorProfiles($existing);

            return $existing->values();
        }

        $doctors = $existing->values();
        for ($i = $doctors->count() + 1; $doctors->count() < 5; $i++) {
            $doctor = $this->createUser($clinic, 'Dr. Clinic Demo ' . $i, "clinic26.doctor{$i}.demo@dentaplus.local", 'doctor', '010926000' . (10 + $i));
            $this->doctorProfile($doctor, $i);
            $doctors->push($doctor);
            $this->summary['doctors_created'][] = $doctor->id;
        }

        $this->summary['doctors_used'] = $existing->pluck('id')->all();

        return $doctors->values();
    }

    private function staff(Clinic $clinic): Collection
    {
        $existing = User::query()
            ->where('clinic_id', $clinic->id)
            ->whereIn('role', ['nurse', 'accountant', 'receptionist', 'staff'])
            ->orderBy('id')
            ->get();

        if ($existing->count() >= 3) {
            $this->summary['staff_used'] = $existing->pluck('id')->all();

            return $existing->values();
        }

        $staff = $existing->values();
        $rows = [
            ['Clinic Demo Nurse', 'clinic26.nurse.demo@dentaplus.local', 'nurse', '01092600101'],
            ['Clinic Demo Accountant', 'clinic26.accountant.demo@dentaplus.local', 'accountant', '01092600102'],
            ['Clinic Demo Receptionist', 'clinic26.reception.demo@dentaplus.local', 'receptionist', '01092600103'],
        ];

        foreach ($rows as $row) {
            if ($staff->count() >= 3) {
                break;
            }

            $user = $this->createUser($clinic, $row[0], $row[1], $row[2], $row[3]);
            $staff->push($user);
            $this->summary['staff_created'][] = $user->id;
        }

        $this->summary['staff_used'] = $existing->pluck('id')->all();

        return $staff->values();
    }

    private function patients(Clinic $clinic, Collection $insuranceCompanies): Collection
    {
        $existing = Patient::query()
            ->with('user')
            ->where('clinic_id', $clinic->id)
            ->whereHas('user', fn ($query) => $query->where('role', 'patient'))
            ->orderBy('id')
            ->get();

        if ($existing->count() >= 15) {
            $this->summary['patients_used'] = $existing->pluck('id')->all();

            return $existing->values();
        }

        $patients = $existing->values();
        for ($i = $patients->count() + 1; $patients->count() < 15; $i++) {
            $patient = $this->createPatient($clinic, $i, $insuranceCompanies);
            $patients->push($patient);
            $this->summary['patients_created'][] = $patient->id;
        }

        $this->summary['patients_used'] = $existing->pluck('id')->all();

        return $patients->values();
    }

    private function createUser(Clinic $clinic, string $name, string $email, string $role, string $phone): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'clinic_id' => $clinic->id,
                'name' => $name,
                'username' => Str::slug($name) . '-' . $clinic->id,
                'phone' => $phone,
                'password' => bcrypt($this->password),
                'status' => 'Active',
                'is_active' => true,
                'is_verified' => true,
                'role' => $role,
            ]
        );

        if ((int) $user->clinic_id !== $clinic->id) {
            throw new RuntimeException("User {$email} is not linked to clinic_id={$clinic->id}.");
        }

        if (method_exists($user, 'syncRoles')) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    private function ensureDoctorProfiles(Collection $doctors): void
    {
        foreach ($doctors->values() as $index => $doctor) {
            $this->doctorProfile($doctor, $index + 1);
        }
    }

    private function doctorProfile(User $user, int $index): void
    {
        Doctor::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'specialization' => ['Endodontics', 'Orthodontics', 'Prosthodontics', 'Periodontics', 'Pediatric Dentistry'][($index - 1) % 5],
                'license_number' => 'LIC-CLINIC26-' . $user->id,
            ]
        );
    }

    private function insurance(Clinic $clinic): Collection
    {
        $existing = InsuranceCompany::query()
            ->where('clinic_id', $clinic->id)
            ->orderBy('id')
            ->get();

        if ($existing->count() >= 3) {
            $this->summary['insurance_companies_used'] = $existing->pluck('id')->all();

            return $existing->values();
        }

        $companies = $existing->values();
        foreach (['Cigna Demo', 'MetLife Demo', 'Delta Demo'] as $index => $name) {
            if ($companies->count() >= 3) {
                break;
            }

            $priceList = InsurancePriceList::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $name . ' Price List'],
                ['year' => (int) now()->format('Y'), 'is_active' => true, 'notes' => 'Demo price list']
            );

            foreach (['Consultation', 'Cleaning', 'Root Canal'] as $serviceIndex => $serviceName) {
                InsurancePriceListItem::query()->updateOrCreate(
                    ['insurance_price_list_id' => $priceList->id, 'item_code' => 'CL26-INS-' . $index . '-' . $serviceIndex],
                    ['service_name' => $serviceName, 'price' => 250 + ($serviceIndex * 300), 'notes' => 'Demo item']
                );
            }

            $company = InsuranceCompany::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $name],
                [
                    'code' => strtoupper(Str::slug($name, '')) . '26',
                    'coverage' => (70 + ($index * 5)) . '% demo coverage',
                    'payment_terms' => (15 + ($index * 15)) . ' days',
                    'syndicate_price_list_id' => $priceList->id,
                    'is_active' => true,
                ]
            );

            $companies->push($company);
            $this->summary['insurance_companies_created'][] = $company->id;
        }

        $this->summary['insurance_companies_used'] = $existing->pluck('id')->all();

        return $companies->values();
    }

    private function createPatient(Clinic $clinic, int $index, Collection $insuranceCompanies): Patient
    {
        $user = $this->createUser($clinic, 'Clinic Demo Patient ' . $index, "clinic26.patient{$index}.demo@dentaplus.local", 'patient', '0109261' . str_pad((string) $index, 4, '0', STR_PAD_LEFT));
        $company = $insuranceCompanies->isNotEmpty() && $index % 3 !== 0
            ? $insuranceCompanies->values()[$index % $insuranceCompanies->count()]
            : null;

        return Patient::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'clinic_id' => $clinic->id,
                'patient_number' => 'PID-CL26-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'date_of_birth' => now()->subYears(18 + $index)->toDateString(),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'phone' => $user->phone,
                'address' => 'Clinic 26 demo patient address ' . $index,
                'medical_history' => 'No major history',
                'allergies' => $index % 4 === 0 ? 'Penicillin' : null,
                'current_medication' => $index % 5 === 0 ? 'Vitamin D' : null,
                'insurance_provider' => $company?->name,
                'insurance_company_id' => $company?->id,
                'insurance_number' => $company ? 'POL-CL26-' . $index : null,
                'notes' => 'Demo seeded patient for existing clinic 26',
            ]
        )->load('user');
    }

    private function patientsClinicalData(Clinic $clinic, Collection $patients, Collection $doctors, Collection $labs, Collection $staff): void
    {
        foreach ($patients->take(8)->values() as $index => $patient) {
            PatientTooth::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'tooth_number' => (string) (11 + $index)],
                ['status' => $index % 2 === 0 ? 'treated' : 'needs_review', 'notes' => 'Demo tooth note']
            );

            PatientRadiology::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'modality' => 'Panoramic ' . $index],
                ['notes' => 'Demo radiology', 'file_path' => 'demo/radiology/sample-' . $index . '.jpg', 'status' => 'reviewed']
            );

            $note = PatientNote::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'note' => 'Demo note for patient ' . $patient->id],
                ['user_id' => $staff->first()->id]
            );

            $note->attachments()->updateOrCreate(
                ['file_path' => 'demo/patient-notes/note-' . $note->id . '.pdf'],
                ['file_name' => 'note-' . $note->id . '.pdf', 'mime_type' => 'application/pdf', 'size' => 1024]
            );
            $note->mentions()->updateOrCreate(['user_id' => $doctors->first()->id]);

            ClinicAppointment::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'appointment_at' => now()->addDays($index + 1)->setHour(10)],
                [
                    'doctor_user_id' => $doctors[$index % $doctors->count()]->id,
                    'patient_name' => $patient->user?->name,
                    'patient_phone' => $patient->phone,
                    'service_name' => 'Consultation',
                    'duration_minutes' => 30,
                    'status' => ['pending', 'confirmed', 'completed'][$index % 3],
                ]
            );
        }

        foreach ($patients->take(10)->values() as $index => $patient) {
            ClinicTreatment::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'patient_id' => $patient->id, 'title' => 'Treatment ' . $index],
                [
                    'doctor_user_id' => $doctors[$index % $doctors->count()]->id,
                    'description' => 'Demo treatment',
                    'tooth_number' => (string) (21 + $index),
                    'sessions_count' => 1 + ($index % 3),
                    'treatment_date' => now()->subDays($index * 3)->toDateString(),
                    'cost' => 500 + ($index * 100),
                    'status' => ['planned', 'in_progress', 'completed'][$index % 3],
                ]
            );
        }

        foreach ($patients->take(6)->values() as $index => $patient) {
            $lab = $labs[$index % $labs->count()];
            $doctorUser = $doctors[$index % $doctors->count()];
            $doctorProfileId = Doctor::query()->where('user_id', $doctorUser->id)->value('id');

            CaseModel::query()->updateOrCreate(
                ['case_number' => 'CASE-CL26-DEMO-' . $index],
                [
                    'clinic_id' => $clinic->id,
                    'lab_id' => $lab->id,
                    'patient_id' => $patient->id,
                    'dentist_id' => $doctorProfileId,
                    'status' => [CaseModel::STATUS_PENDING, CaseModel::STATUS_ACCEPTED, CaseModel::STATUS_DELIVERED][$index % 3],
                    'priority' => $index % 2 ? CaseModel::PRIORITY_URGENT : CaseModel::PRIORITY_NORMAL,
                    'due_date' => now()->addDays($index - 2)->toDateString(),
                    'case_type' => 'Zircon Crown',
                    'description' => 'Demo lab case',
                    'created_by' => $doctorUser->id,
                    'delivered_at' => $index % 3 === 2 ? now()->subDay() : null,
                ]
            );
        }
    }

    private function billing(Clinic $clinic, Collection $patients, Collection $doctors, Collection $staff): void
    {
        $categories = collect(['Supplies', 'Rent', 'Salaries', 'Maintenance'])->map(fn ($name) => ClinicExpenseCategory::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => $name],
            ['status' => 'active']
        ));

        foreach ($patients->take(12)->values() as $index => $patient) {
            $total = 700 + ($index * 120);
            $paid = match ($index % 4) {
                0 => $total,
                1 => round($total / 2, 2),
                default => 0,
            };
            $issuedAt = now()->subMonths($index % 4)->subDays($index)->toDateString();
            $dueDate = $index % 4 === 3 ? now()->subDays(5)->toDateString() : now()->addDays(20)->toDateString();
            $status = $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : ($index % 4 === 3 ? 'overdue' : 'pending'));

            $invoice = ClinicInvoice::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'invoice_number' => 'INV-CL26-DEMO-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'patient_id' => $patient->id,
                    'doctor_user_id' => $doctors[$index % $doctors->count()]->id,
                    'total' => $total,
                    'paid' => $paid,
                    'remaining' => max($total - $paid, 0),
                    'status' => $status,
                    'payment_method' => 'cash',
                    'issued_at' => $issuedAt,
                    'due_date' => $dueDate,
                    'notes' => 'Demo invoice',
                ]
            );

            ClinicInvoiceItem::query()->updateOrCreate(
                ['clinic_invoice_id' => $invoice->id, 'description' => 'Dental service demo'],
                ['amount' => $total]
            );

            if ($paid > 0) {
                ClinicPayment::query()->updateOrCreate(
                    ['clinic_invoice_id' => $invoice->id, 'amount' => $paid],
                    ['clinic_id' => $clinic->id, 'recorded_by' => $staff->last()->id, 'method' => 'cash', 'paid_at' => $issuedAt, 'notes' => 'Demo payment']
                );
            }
        }

        foreach (range(0, 11) as $index) {
            ClinicExpense::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'title' => 'Demo expense ' . $index],
                [
                    'expense_category_id' => $categories[$index % $categories->count()]->id,
                    'amount' => 200 + ($index * 50),
                    'payment_method' => 'cash',
                    'expense_date' => now()->subMonths($index % 4)->subDays($index)->toDateString(),
                    'assigned_to_user_id' => $staff[$index % $staff->count()]->id,
                    'notes' => 'Demo expense',
                    'attachment_path' => $index % 3 === 0 ? 'demo/expenses/receipt-' . $index . '.pdf' : null,
                ]
            );
        }
    }

    private function materials(): Collection
    {
        $existing = MaterialProduct::query()
            ->with('company')
            ->whereIn('status', ['active', 'Active'])
            ->orderBy('id')
            ->limit(15)
            ->get();

        if ($existing->count() >= 15) {
            $this->summary['material_products_used'] = $existing->pluck('id')->all();

            return $existing->values();
        }

        $companies = $this->materialCompanies();
        $materials = $existing->values();

        for ($i = $materials->count() + 1; $materials->count() < 15; $i++) {
            $company = $companies[$i % $companies->count()];
            $material = MaterialProduct::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'Clinic 26 Demo Material ' . $i],
                [
                    'brand' => 'Brand ' . (($i % 4) + 1),
                    'barcode' => 'CL26-MAT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'description' => 'Demo material product',
                    'category' => ['restorative', 'endodontics', 'prosthodontics'][$i % 3],
                    'price' => 80 + ($i * 15),
                    'stock' => 20 + $i,
                    'status' => 'active',
                    'approval_status' => 'approved',
                ]
            );

            $materials->push($material->load('company'));
            $this->summary['material_products_created'][] = $material->id;
        }

        $this->summary['material_products_used'] = $existing->pluck('id')->all();

        return $materials->values();
    }

    private function materialCompanies(): Collection
    {
        $existing = MaterialCompany::query()->whereIn('status', ['Active', 'active'])->orderBy('id')->limit(3)->get();
        $companies = $existing->values();

        for ($i = $companies->count() + 1; $companies->count() < 3; $i++) {
            $companies->push(MaterialCompany::query()->updateOrCreate(
                ['email' => "clinic26.material{$i}.demo@dentaplus.local"],
                ['name' => 'Clinic 26 Demo Material Company ' . $i, 'commission_percentage' => 5 + $i, 'phone' => '0109262000' . $i, 'status' => 'Active']
            ));
        }

        return $companies->values();
    }

    private function inventoryAndProcurement(Clinic $clinic, Collection $materials, User $admin): void
    {
        $existingInventoryCount = InventoryItem::query()->withoutGlobalScopes()->where('clinic_id', $clinic->id)->count();

        if ($existingInventoryCount < 10) {
            foreach ($materials->take(10)->values() as $index => $material) {
                $quantity = $index === 0 ? 0 : ($index < 4 ? 2 : 20 + $index);
                $minimum = 5;
                InventoryItem::query()->withoutGlobalScopes()->updateOrCreate(
                    ['clinic_id' => $clinic->id, 'product_id' => $material->id],
                    [
                        'company_id' => $material->company_id,
                        'barcode' => $material->barcode,
                        'product_name' => $material->name,
                        'category_name' => $material->category,
                        'description' => $material->description,
                        'quantity' => $quantity,
                        'minimum_stock_level' => $minimum,
                        'reorder_quantity' => 10,
                        'unit' => 'piece',
                        'supplier' => $material->company?->name,
                        'status' => $quantity <= 0 ? 'out_of_stock' : ($quantity <= $minimum ? 'low_stock' : 'in_stock'),
                        'last_updated_at' => now(),
                    ]
                );
            }
        }

        if (ProcurementOrder::query()->where('clinic_id', $clinic->id)->count() >= 4) {
            return;
        }

        foreach (['pending', 'ordered', 'received', 'cancelled'] as $index => $status) {
            $material = $materials[$index];
            ProcurementOrder::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'po_number' => 'PO-CL26-DEMO-' . $status],
                [
                    'material_id' => $material->id,
                    'supplier_id' => $material->company_id,
                    'supplier_name' => $material->company?->name,
                    'qty' => 5 + $index,
                    'unit_cost' => $material->price,
                    'total_cost' => round((5 + $index) * (float) $material->price, 2),
                    'status' => $status,
                    'ordered_at' => in_array($status, ['ordered', 'received'], true) ? now()->subDays(4) : null,
                    'received_at' => $status === 'received' ? now()->subDay() : null,
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    private function equipment(Clinic $clinic, User $admin): void
    {
        $companies = $this->maintenanceCompanies();
        $existingEquipment = Equipment::query()->where('clinic_id', $clinic->id)->orderBy('id')->get();

        if ($existingEquipment->count() >= 8) {
            $this->summary['equipment_used'] = $existingEquipment->pluck('id')->all();
            $equipment = $existingEquipment->values();
        } else {
            $equipment = $existingEquipment->values();
            for ($i = $equipment->count() + 1; $equipment->count() < 8; $i++) {
                $item = Equipment::query()->updateOrCreate(
                    ['clinic_id' => $clinic->id, 'name' => 'Clinic 26 Demo Equipment ' . $i],
                    ['status' => $i % 4 === 0 ? Equipment::STATUS_BROKEN : Equipment::STATUS_OPERATIONAL, 'image_url' => null]
                );
                $equipment->push($item);
                $this->summary['equipment_created'][] = $item->id;
            }
            $this->summary['equipment_used'] = $existingEquipment->pluck('id')->all();
        }

        if (OwnerMaintenanceRequest::query()->where('clinic_id', $clinic->id)->count() >= 4) {
            return;
        }

        foreach ($equipment->take(4)->values() as $index => $item) {
            OwnerMaintenanceRequest::query()->updateOrCreate(
                ['request_code' => 'MR-CL26-DEMO-' . ($index + 1)],
                [
                    'clinic_id' => $clinic->id,
                    'equipment_id' => $item->id,
                    'equipment' => $item->name,
                    'malfunction_type' => 'Demo issue',
                    'issue_description' => 'Demo maintenance issue',
                    'urgency' => ['low', 'medium', 'high', 'critical'][$index],
                    'assigned_company_id' => $companies[$index % $companies->count()]->id,
                    'status' => OwnerMaintenanceRequest::STATUS_PENDING,
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    private function maintenanceCompanies(): Collection
    {
        $existing = MaintenanceCompany::query()->where('status', MaintenanceCompany::STATUS_ACTIVE)->orderBy('id')->limit(3)->get();

        if ($existing->count() >= 3) {
            $this->summary['maintenance_companies_used'] = $existing->pluck('id')->all();

            return $existing->values();
        }

        $companies = $existing->values();
        for ($i = $companies->count() + 1; $companies->count() < 3; $i++) {
            $company = MaintenanceCompany::query()->updateOrCreate(
                ['email' => "clinic26.maintenance{$i}.demo@dentaplus.local"],
                ['name' => 'Clinic 26 Demo Maintenance Co ' . $i, 'contact_person' => 'Support ' . $i, 'phone' => '+20109263000' . $i, 'status' => MaintenanceCompany::STATUS_ACTIVE, 'ai_rating' => 4 + ($i / 10)]
            );
            $companies->push($company);
            $this->summary['maintenance_companies_created'][] = $company->id;
        }

        $this->summary['maintenance_companies_used'] = $existing->pluck('id')->all();

        return $companies->values();
    }

    private function dentalLabs(Clinic $clinic, User $admin): Collection
    {
        $existing = ClinicLabPartnership::query()
            ->with('lab')
            ->where('clinic_id', $clinic->id)
            ->orderBy('id')
            ->get()
            ->pluck('lab')
            ->filter()
            ->values();

        if ($existing->count() >= 3) {
            $this->summary['dental_labs_used'] = $existing->pluck('id')->all();
            $this->ensureLabDetails($existing, $admin);

            return $existing;
        }

        $labs = $existing->values();
        for ($i = $labs->count() + 1; $labs->count() < 3; $i++) {
            $lab = DentalLab::query()->updateOrCreate(
                ['email' => "clinic26.lab{$i}.demo@dentaplus.local"],
                ['name' => 'Clinic 26 Demo Dental Lab ' . $i, 'contact_person' => 'Lab Contact ' . $i, 'phone' => '+20109264000' . $i, 'address' => 'Lab address ' . $i, 'status' => DentalLab::STATUS_ACTIVE, 'is_external' => $i > 1, 'avg_delivery_days' => 3 + $i, 'response_speed' => $i === 1 ? 'Fast' : 'Medium']
            );

            ClinicLabPartnership::query()->updateOrCreate(
                ['clinic_id' => $clinic->id, 'lab_id' => $lab->id],
                ['status' => ClinicLabPartnership::STATUS_ACTIVE, 'partnership_start_date' => now()->subMonths($i)->toDateString(), 'total_cases_sent' => 0, 'invited_by' => $admin->id]
            );

            $labs->push($lab);
            $this->summary['dental_labs_created'][] = $lab->id;
        }

        $this->summary['dental_labs_used'] = $existing->pluck('id')->all();
        $this->ensureLabDetails($labs, $admin);

        return $labs->values();
    }

    private function ensureLabDetails(Collection $labs, User $admin): void
    {
        foreach ($labs->values() as $i => $lab) {
            foreach (['Zircon Crown', 'Emax Veneer'] as $serviceIndex => $service) {
                LabService::query()->updateOrCreate(
                    ['lab_id' => $lab->id, 'service_name' => $service],
                    ['price' => 1000 + ($serviceIndex * 400), 'turnaround_time_days' => 4 + $serviceIndex]
                );
            }

            LabGalleryImage::query()->updateOrCreate(
                ['lab_id' => $lab->id, 'url' => 'demo/labs/clinic26-lab-' . ($i + 1) . '.jpg'],
                ['type' => 'before', 'disk' => 'public', 'sort_order' => 1, 'uploaded_by' => $admin->id, 'created_at' => now()]
            );
        }
    }

    private function messages(Clinic $clinic, User $admin, Collection $patients, Collection $doctors): void
    {
        $template = MessageTemplate::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Demo WhatsApp Reminder'],
            ['created_by' => $admin->id, 'message_type' => 'reminder', 'channel' => 'whatsapp', 'body' => 'Hello [Patient Name], this is your appointment reminder.', 'placeholders' => ['[Patient Name]'], 'is_active' => true]
        );

        $message = Message::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'batch_uuid' => 'demo-batch-' . $clinic->id],
            ['created_by' => $admin->id, 'template_id' => $template->id, 'channel' => 'whatsapp', 'message_type' => 'reminder', 'message' => $template->body, 'sent_at' => now()]
        );

        foreach ($patients->take(5)->values() as $index => $patient) {
            MessageLog::query()->updateOrCreate(
                ['message_id' => $message->id, 'patient_id' => $patient->id],
                [
                    'clinic_id' => $clinic->id,
                    'doctor_user_id' => $doctors[$index % $doctors->count()]->id,
                    'template_id' => $template->id,
                    'sent_by' => $admin->id,
                    'batch_uuid' => $message->batch_uuid,
                    'channel' => 'whatsapp',
                    'message_type' => 'reminder',
                    'status' => 'sent',
                    'message_body' => 'Hello ' . $patient->user?->name . ', this is your appointment reminder.',
                    'phone' => $patient->phone,
                    'sent_at' => now()->subDays($index),
                ]
            );
        }
    }

    private function cart(Clinic $clinic, User $admin, Collection $materials): void
    {
        $cart = Cart::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'user_id' => $admin->id, 'status' => Cart::STATUS_ACTIVE],
            []
        );

        foreach ($materials->take(3)->values() as $index => $material) {
            CartItem::query()->updateOrCreate(
                ['cart_id' => $cart->id, 'material_product_id' => $material->id],
                ['quantity' => 1 + $index, 'unit_price' => $material->price, 'line_total' => round((1 + $index) * (float) $material->price, 2)]
            );
        }
    }

    private function printSummary(Clinic $clinic, User $admin, Collection $doctors, Collection $patients, Collection $labs): void
    {
        $unpaidInvoice = ClinicInvoice::query()
            ->where('clinic_id', $clinic->id)
            ->where('remaining', '>', 0)
            ->orderBy('id')
            ->first();

        $this->command?->info('DantaPlus clinic demo seed completed.');
        $this->command?->line('clinic_id=' . $clinic->id . ' (existing clinic used, not created)');
        $this->command?->line('admin_user_id=' . $admin->id . ' (existing admin used: ' . $admin->email . ')');
        $this->command?->line('doctors_used=[' . implode(',', $this->summary['doctors_used']) . '] + doctors_created=[' . implode(',', $this->summary['doctors_created']) . ']');
        $this->command?->line('staff_used=[' . implode(',', $this->summary['staff_used']) . '] + staff_created=[' . implode(',', $this->summary['staff_created']) . ']');
        $this->command?->line('patients_used=[' . implode(',', $this->summary['patients_used']) . '] + patients_created=[' . implode(',', $this->summary['patients_created']) . ']');
        $this->command?->line('insurance_companies_used=[' . implode(',', $this->summary['insurance_companies_used']) . '] + insurance_companies_created=[' . implode(',', $this->summary['insurance_companies_created']) . ']');
        $this->command?->line('material_products_used=[' . implode(',', $this->summary['material_products_used']) . '] + material_products_created=[' . implode(',', $this->summary['material_products_created']) . ']');
        $this->command?->line('dental_labs_used=[' . implode(',', $this->summary['dental_labs_used']) . '] + dental_labs_created=[' . implode(',', $this->summary['dental_labs_created']) . ']');
        $this->command?->line('maintenance_companies_used=[' . implode(',', $this->summary['maintenance_companies_used']) . '] + maintenance_companies_created=[' . implode(',', $this->summary['maintenance_companies_created']) . ']');
        $this->command?->line('equipment_used=[' . implode(',', $this->summary['equipment_used']) . '] + equipment_created=[' . implode(',', $this->summary['equipment_created']) . ']');
        $this->command?->line('doctor_ids=' . $doctors->pluck('id')->take(5)->implode(','));
        $this->command?->line('patient_ids=' . $patients->pluck('id')->take(5)->implode(','));
        $this->command?->line('invoice_unpaid_id=' . ($unpaidInvoice?->id ?? 'none'));
        $this->command?->line('dental_lab_ids=' . $labs->pluck('id')->implode(','));
    }
}
