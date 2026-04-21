<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicAppointment;
use App\Models\ClinicInvoice;
use App\Models\ClinicPayment;
use App\Models\ClinicTreatment;
use App\Models\Patient;
use App\Models\PatientNote;
use App\Models\PatientRadiology;
use App\Models\PatientTooth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TestclinicSeeder extends Seeder
{
    // ─── الـ credentials الموجودة من الـ API ────────────────────────
    private const SUPER_ADMIN_EMAIL = 'admin@system.com';
    private const SUPER_ADMIN_TOKEN = '76|1O7aLgBv3mGu7wtlvSjtRi4Ni4cCyNKKhh3moTDQ158f5604';

    // ─── الـ clinic اللي هنربط بيها الـ clinic_admin ───────────────
    // باستخدام أول عيادة active موجودة في الـ DB (ID: 26 من الـ response)
    private const TARGET_CLINIC_EMAIL = 'emily.adams@clinic.com'; // Bright Smiles Dental
    private const TARGET_CLINIC_ID    = 26; // fallback لو مش موجود

    public function run(): void
    {
        // ─── 1. نجيب الـ Super Admin الموجود (مش بنعمله جديد) ──────
        $superAdmin = User::query()->where('email', self::SUPER_ADMIN_EMAIL)->first();

        if (! $superAdmin) {
            $this->command->warn('Super Admin not found: ' . self::SUPER_ADMIN_EMAIL);
            $this->command->warn('Make sure to run the main seeder first.');
            return;
        }

        $this->command->info('Super Admin found: ' . $superAdmin->name . ' (ID: ' . $superAdmin->id . ')');

        // ─── 2. نجيب الـ clinic المستهدفة من الـ DB ─────────────────
        $clinic = Clinic::query()
            ->where('email', self::TARGET_CLINIC_EMAIL)
            ->orWhere('id', self::TARGET_CLINIC_ID)
            ->first();

        // لو مش موجودة خد أول clinic active
        if (! $clinic) {
            $clinic = Clinic::query()
                ->where('status', 'Active')
                ->whereNotNull('id')
                ->orderBy('id')
                ->first();
        }

        if (! $clinic) {
            $this->command->error('No clinic found in DB. Run FullLookupSeeder first.');
            return;
        }

        $this->command->info('Target clinic: ' . $clinic->name . ' (ID: ' . $clinic->id . ')');

        // ─── 3. Sync الـ modules للـ clinic ──────────────────────────
        $this->syncClinicModules($clinic);

        // ─── 4. ننشئ Clinic Admin ويتربط بالـ clinic دي ───────────
        $clinicAdmin = $this->upsertUser(
            email: 'clinic.admin@denta.com',
            attributes: [
                'name'        => 'Clinic Admin',
                'username'    => 'clinicadmin',
                'phone'       => '01000000002',
                'password'    => Hash::make('password123'),
                'is_active'   => true,
                'is_verified' => true,
                'status'      => 'Active',
                'role'        => 'clinic_admin',
                'clinic_id'   => $clinic->id,
            ],
            roles: ['clinic_admin']
        );

        $this->command->info('Clinic Admin created/updated: ' . $clinicAdmin->email);

        // ─── 5. Staff — واحد من كل role clinic موجودة ───────────────
        $allRoles      = Role::query()->pluck('name')->all();
        $skipRoles     = ['super-admin', 'Admin', 'clinic_admin', 'patient'];
        $clinicRoles   = array_filter($allRoles, fn($r) => ! in_array($r, $skipRoles, true));

        $roleUsers = ['clinic_admin' => $clinicAdmin];

        foreach ($clinicRoles as $roleName) {
            $email = Str::lower(str_replace([' ', '-'], '_', $roleName)) . '@denta.com';
            $name  = 'Test ' . Str::headline(str_replace(['_', '-'], ' ', $roleName));

            $roleUsers[$roleName] = $this->upsertUser(
                email: $email,
                attributes: [
                    'name'        => $name,
                    'username'    => Str::slug($name, ''),
                    'phone'       => $this->phoneForRole($roleName),
                    'password'    => Hash::make('password123'),
                    'is_active'   => true,
                    'is_verified' => true,
                    'status'      => 'Active',
                    'role'        => $roleName,
                    'clinic_id'   => $clinic->id,
                ],
                roles: [$roleName]
            );

            $this->command->line('  Staff created: ' . $email . ' [' . $roleName . ']');
        }

        // ─── 6. Patients (3 مرضى) ────────────────────────────────────
        $p1User = $this->upsertUser(
            email: 'patient1@denta.com',
            attributes: [
                'name' => 'Liam Smith', 'username' => 'liamsmith', 'phone' => '01000000011',
                'password' => Hash::make('password123'), 'is_active' => true,
                'is_verified' => true, 'status' => 'Active', 'role' => 'patient',
                'clinic_id' => $clinic->id,
            ],
            roles: ['patient']
        );

        $p2User = $this->upsertUser(
            email: 'patient2@denta.com',
            attributes: [
                'name' => 'Olivia Johnson', 'username' => 'oliviajohnson', 'phone' => '01000000012',
                'password' => Hash::make('password123'), 'is_active' => true,
                'is_verified' => true, 'status' => 'Active', 'role' => 'patient',
                'clinic_id' => $clinic->id,
            ],
            roles: ['patient']
        );

        $p3User = $this->upsertUser(
            email: 'patient3@denta.com',
            attributes: [
                'name' => 'Noah Williams', 'username' => 'noahwilliams', 'phone' => '01000000013',
                'password' => Hash::make('password123'), 'is_active' => true,
                'is_verified' => true, 'status' => 'Active', 'role' => 'patient',
                'clinic_id' => $clinic->id,
            ],
            roles: ['patient']
        );

        $patient1 = $this->upsertPatient($p1User, $clinic->id, 'PID-0000001', [
            'date_of_birth' => '1992-03-15', 'gender' => 'male',
            'phone' => '01000000011', 'address' => 'Nasr City, Cairo',
            'medical_history' => 'No chronic illness.', 'allergies' => 'Penicillin',
            'current_medication' => 'None', 'insurance_provider' => 'MedNet',
            'insurance_number' => 'INS-1001', 'notes' => 'Postman test patient.',
        ]);

        $patient2 = $this->upsertPatient($p2User, $clinic->id, 'PID-0000002', [
            'date_of_birth' => '1987-07-20', 'gender' => 'female',
            'phone' => '01000000012', 'address' => 'Heliopolis, Cairo',
            'medical_history' => 'Mild hypertension.', 'allergies' => 'None',
            'current_medication' => 'Amlodipine', 'insurance_provider' => 'AXA',
            'insurance_number' => 'INS-1002', 'notes' => 'Follow-up patient.',
        ]);

        $patient3 = $this->upsertPatient($p3User, $clinic->id, 'PID-0000003', [
            'date_of_birth' => '1995-11-08', 'gender' => 'male',
            'phone' => '01000000013', 'address' => 'Maadi, Cairo',
            'medical_history' => 'Diabetes type 2.', 'allergies' => 'Latex',
            'current_medication' => 'Metformin', 'insurance_provider' => 'Bupa',
            'insurance_number' => 'INS-1003', 'notes' => 'Requires periodic review.',
        ]);

        $this->command->info('3 patients created/updated.');

        // ─── 7. Dental Chart (لـ patient1) ───────────────────────────
        $this->upsertTooth($patient1, $clinic->id, '11', 'caries',   'Occlusal caries noted.');
        $this->upsertTooth($patient1, $clinic->id, '26', 'filled',   'Composite filling completed.');
        $this->upsertTooth($patient1, $clinic->id, '36', 'planned',  'Crown planned.');

        // ─── 8. Appointments ──────────────────────────────────────────
        $doctor = $roleUsers['doctor'] ?? $clinicAdmin;

        $app1 = ClinicAppointment::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
             'service_name' => 'Initial Consultation',
             'appointment_at' => Carbon::today()->setTime(10, 0, 0)],
            [
                'doctor_user_id' => $doctor->id, 'patient_name' => $p1User->name,
                'patient_phone' => $patient1->phone ?? '01000000011',
                'duration_minutes' => 30, 'duration' => 30,
                'branch' => 'Main Branch', 'room' => 'Room 1',
                'payment_type' => 'cash', 'status' => 'scheduled',
                'notes' => 'First visit — checkup.',
            ]
        );

        $app2 = ClinicAppointment::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
             'service_name' => 'Follow Up',
             'appointment_at' => Carbon::tomorrow()->setTime(12, 0, 0)],
            [
                'doctor_user_id' => $doctor->id, 'patient_name' => $p1User->name,
                'patient_phone' => $patient1->phone ?? '01000000011',
                'duration_minutes' => 45, 'duration' => 45,
                'branch' => 'Main Branch', 'room' => 'Room 2',
                'payment_type' => 'card', 'status' => 'confirmed',
                'notes' => 'Treatment follow-up.',
            ]
        );

        // ─── 9. Treatments ────────────────────────────────────────────
        $tr1 = ClinicTreatment::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
             'title' => 'Dental Filling', 'status' => 'completed'],
            [
                'doctor_user_id' => $doctor->id,
                'description'    => 'Composite filling for tooth 26.',
                'tooth_number'   => '26', 'sessions_count' => 1,
                'treatment_date' => Carbon::today()->toDateString(),
                'cost'           => 150.00,
            ]
        );

        $tr2 = ClinicTreatment::query()->updateOrCreate(
            ['clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
             'title' => 'Root Canal Treatment', 'status' => 'planned'],
            [
                'doctor_user_id' => $doctor->id,
                'description'    => 'Planned RCT for tooth 11.',
                'tooth_number'   => '11', 'sessions_count' => 3,
                'treatment_date' => Carbon::tomorrow()->toDateString(),
                'cost'           => 500.00,
            ]
        );

        // ─── 10. Invoices + Payment ───────────────────────────────────
        $inv1 = ClinicInvoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-0001'],
            [
                'clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
                'appointment_id' => $app1->id,
                'total' => 150.00, 'paid' => 150.00, 'remaining' => 0.00,
                'status' => 'paid', 'payment_method' => 'cash',
                'issued_at' => Carbon::today()->toDateString(),
                'notes' => 'Invoice for completed filling.',
            ]
        );

        $inv2 = ClinicInvoice::query()->updateOrCreate(
            ['invoice_number' => 'INV-0002'],
            [
                'clinic_id' => $clinic->id, 'patient_id' => $patient1->id,
                'appointment_id' => $app2->id,
                'total' => 1200.00, 'paid' => 0.00, 'remaining' => 1200.00,
                'status' => 'pending', 'payment_method' => 'cash',
                'issued_at' => Carbon::tomorrow()->toDateString(),
                'notes' => 'Planned treatment estimate.',
            ]
        );

        $accountant = $roleUsers['accountant'] ?? $clinicAdmin;

        ClinicPayment::query()->updateOrCreate(
            ['clinic_invoice_id' => $inv1->id, 'amount' => 150.00],
            [
                'clinic_id'   => $clinic->id,
                'recorded_by' => $accountant->id,
                'method'      => 'cash',
                'paid_at'     => Carbon::today()->setTime(10, 30, 0),
                'notes'       => 'Full payment collected.',
            ]
        );

        // ─── 11. Radiology + Discussion ──────────────────────────────
        PatientRadiology::query()->updateOrCreate(
            ['patient_id' => $patient1->id, 'clinic_id' => $clinic->id,
             'modality' => 'Panoramic X-Ray'],
            [
                'notes'     => 'Baseline panoramic image.',
                'file_path' => 'clinic/radiology/mock-panorama.jpg',
                'status'    => 'available',
            ]
        );

        PatientNote::query()->updateOrCreate(
            ['patient_id' => $patient1->id, 'user_id' => $doctor->id,
             'clinic_id' => $clinic->id],
            ['note' => 'Patient discussed treatment plan and agreed to proceed with RCT.']
        );

        // ─── Summary ──────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('  CLINIC POSTMAN TEST — CREDENTIALS   ');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('Super Admin (existing):');
        $this->command->info('  Email : ' . self::SUPER_ADMIN_EMAIL);
        $this->command->info('  Token : ' . self::SUPER_ADMIN_TOKEN);
        $this->command->info('  Role  : super-admin');
        $this->command->info('───────────────────────────────────────');
        $this->command->info('Clinic Admin (new):');
        $this->command->info('  Email    : clinic.admin@denta.com');
        $this->command->info('  Password : password123');
        $this->command->info('  Clinic   : ' . $clinic->name . ' (ID: ' . $clinic->id . ')');
        $this->command->info('───────────────────────────────────────');
        $this->command->info('Staff Accounts (password: password123):');

        foreach ($roleUsers as $role => $u) {
            if ($role === 'clinic_admin') {
                continue;
            }
            $label = str_pad(Str::headline(str_replace(['_', '-'], ' ', $role)) . ':', 20);
            $this->command->info('  ' . $label . ' ' . $u->email);
        }

        $this->command->info('───────────────────────────────────────');
        $this->command->info('Test Data IDs:');
        $this->command->info('  clinic_id      : ' . $clinic->id);
        $this->command->info('  patient_id (1) : ' . $patient1->id);
        $this->command->info('  patient_id (2) : ' . $patient2->id);
        $this->command->info('  patient_id (3) : ' . $patient3->id);
        $this->command->info('  appointment (1): ' . $app1->id);
        $this->command->info('  appointment (2): ' . $app2->id);
        $this->command->info('  treatment (1)  : ' . $tr1->id);
        $this->command->info('  treatment (2)  : ' . $tr2->id);
        $this->command->info('  invoice (1)    : ' . $inv1->id . '  [paid]');
        $this->command->info('  invoice (2)    : ' . $inv2->id . '  [pending]');
        $this->command->info('═══════════════════════════════════════');
        $this->command->info('Run: php artisan db:seed --class=ClinicPostmanSeeder');
        $this->command->info('');
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function upsertUser(string $email, array $attributes, array $roles): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $attributes['name'], 'password' => $attributes['password']]
        );

        $user->forceFill($attributes)->save();

        $existingRoles = Role::query()
            ->whereIn('name', $roles)
            ->pluck('name')
            ->all();

        if ($existingRoles !== []) {
            $user->syncRoles($existingRoles);
        }

        return $user->fresh();
    }

    private function upsertPatient(User $user, int $clinicId, string $patientNumber, array $attributes): Patient
    {
        return Patient::query()->updateOrCreate(
            ['user_id' => $user->id],
            array_merge($attributes, [
                'clinic_id'      => $clinicId,
                'patient_number' => $patientNumber,
            ])
        );
    }

    private function upsertTooth(Patient $patient, int $clinicId, string $tooth, string $status, string $notes): void
    {
        PatientTooth::query()->updateOrCreate(
            ['patient_id' => $patient->id, 'clinic_id' => $clinicId, 'tooth_number' => $tooth],
            ['status' => $status, 'notes' => $notes]
        );
    }

    private function syncClinicModules(Clinic $clinic): void
    {
        if (! Schema::hasTable('clinic_modules')) {
            return;
        }

        $modules = config('clinic_modules.keys', [
            'patients', 'appointments', 'treatments',
            'billing', 'radiology', 'dental_chart',
        ]);

        foreach ($modules as $module) {
            DB::table('clinic_modules')->updateOrInsert(
                ['clinic_id' => $clinic->id, 'module' => $module],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function phoneForRole(string $roleName): string
    {
        return match ($roleName) {
            'doctor'                  => '01000000003',
            'nurse'                   => '01000000004',
            'accountant'              => '01000000005',
            'receptionist'            => '01000000006',
            'staff'                   => '01000000007',
            'lab_admin'               => '01000000021',
            'lab_receptionist'        => '01000000022',
            'lab_technician'          => '01000000023',
            'delivery_representative' => '01000000024',
            'material_company_admin'  => '01000000025',
            'sales_rep'               => '01000000026',
            'delivery_staff'          => '01000000027',
            default                   => '01000000099',
        };
    }
}
