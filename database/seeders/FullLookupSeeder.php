<?php

namespace Database\Seeders;

use App\Enums\LabRole;
use App\Enums\UserStatus;
use App\Models\Clinic;
use App\Models\ClinicLabPartnership;
use App\Models\DentalLab;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FullLookupSeeder extends Seeder
{
    private const LAB_ID = 12;

    private const ADMIN_EMAIL = 'labadmin1@example.com';

    private const TECHNICIANS_COUNT = 5;

    private const DOCTORS_COUNT = 5;

    private const PATIENTS_COUNT = 10;

    private const CLINICS_COUNT = 3;

    private Generator $faker;

    /** @var array<string, array<int, string>> */
    private array $columnsCache = [];

    /** @var array<string, array<int, string>> */
    private array $requiredColumnsCache = [];

    public function run(): void
    {
        $this->faker = FakerFactory::create();

        DB::transaction(function () {
            $this->ensureRoles();

            $lab = $this->ensureLab(self::LAB_ID);
            $admin = $this->ensureLabAdmin($lab->id);
            $clinics = $this->ensureClinics($lab->id, $admin->id);

            $this->ensureTechnicians($lab->id);
            $this->ensureDoctors($clinics);
            $this->ensurePatients($clinics);
        });

        $this->command?->info('FullLookupSeeder completed successfully for lab_id = ' . self::LAB_ID);
    }

    private function ensureRoles(): void
    {
        $requiredRoles = ['lab_admin', 'lab_technician', 'doctor', 'patient'];

        $missingRoles = DB::table('roles')
            ->whereIn('name', $requiredRoles)
            ->pluck('name')
            ->all();

        if (count($missingRoles) !== count($requiredRoles)) {
            $this->call(RoleSeeder::class);
        }
    }

    private function ensureLab(int $labId): DentalLab
    {
        $lab = DentalLab::query()->find($labId);

        if (! $lab) {
            $lab = new DentalLab();
            $lab->forceFill($this->completePayload('dental_labs', [
                'id' => $labId,
                'name' => 'Lookup Lab 12',
                'contact_person' => 'Lookup Admin',
                'address' => '12 Nile Street, Cairo',
                'city' => 'Cairo',
                'phone' => $this->fakePhone(1200),
                'email' => 'lookup-lab-12@example.com',
                'working_hours' => '09:00-18:00',
                'avg_delivery_days' => 3,
                'response_speed' => 'Fast',
                'status' => DentalLab::STATUS_ACTIVE,
                'is_external' => false,
                'date_added' => now()->toDateString(),
                'rating' => 4.8,
                'on_time_percentage' => 96.5,
                'rejection_rate' => 1.2,
            ]));
            $lab->save();

            return $lab->refresh();
        }

        $this->fillMissingAttributes($lab, 'dental_labs', [
            'name' => $lab->name ?: 'Lookup Lab 12',
            'contact_person' => $lab->contact_person ?: 'Lookup Admin',
            'address' => $lab->address ?: '12 Nile Street, Cairo',
            'city' => $lab->city ?: 'Cairo',
            'phone' => $lab->phone ?: $this->fakePhone(1200),
            'email' => $lab->email ?: 'lookup-lab-12@example.com',
            'working_hours' => $lab->working_hours ?: '09:00-18:00',
            'avg_delivery_days' => $lab->avg_delivery_days ?: 3,
            'response_speed' => $lab->response_speed ?: 'Fast',
            'status' => $lab->status ?: DentalLab::STATUS_ACTIVE,
        ]);

        return $lab->refresh();
    }

    private function ensureLabAdmin(int $labId): User
    {
        $defaults = $this->completePayload('users', [
            'name' => 'Lab Admin 12',
            'username' => 'labadmin12',
            'email' => self::ADMIN_EMAIL,
            'phone' => $this->fakePhone(1201),
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_verified' => true,
            'status' => UserStatus::Active->value,
            'role' => LabRole::LabAdmin->value,
            'lab_id' => $labId,
            'email_verified_at' => now(),
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            $defaults
        );

        $this->fillMissingAttributes($admin, 'users', [
            'name' => $defaults['name'] ?? null,
            'username' => $defaults['username'] ?? null,
            'phone' => $admin->phone ?: ($defaults['phone'] ?? null),
            'password' => $admin->password ?: ($defaults['password'] ?? null),
            'is_active' => true,
            'is_verified' => true,
            'status' => UserStatus::Active->value,
            'role' => LabRole::LabAdmin->value,
            'lab_id' => $labId,
            'clinic_id' => null,
        ]);

        if (! $admin->hasRole(LabRole::LabAdmin->value)) {
            $admin->assignRole(LabRole::LabAdmin->value);
        }

        return $admin->refresh();
    }

    /**
     * @return array<int, Clinic>
     */
    private function ensureClinics(int $labId, int $adminId): array
    {
        $clinics = [];

        for ($i = 1; $i <= self::CLINICS_COUNT; $i++) {
            $email = "lookup.clinic{$i}.lab{$labId}@example.com";
            $basePayload = $this->completePayload('clinics', [
                'name' => 'Lookup Clinic ' . $i,
                'owner_name' => $this->faker->name(),
                'email' => $email,
                'phone' => $this->fakePhone(1300 + $i),
                'address' => $this->faker->address(),
                'subdomain' => "lookup-clinic-{$i}-lab{$labId}.dentaplus.test",
                'clinic_type' => Arr::random([
                    'General Dentist',
                    'Orthodontics',
                    'Prosthodontics',
                    'Endodontics',
                    'Pediatric Dentistry',
                ]),
                'is_external' => false,
                'notes' => 'Generated by FullLookupSeeder',
                'added_by' => $adminId,
                'registration_date' => now()->subDays($i * 10)->toDateString(),
                'subscription_plan' => 'Basic',
                'payment_method' => 'Manual',
                'status' => 'Active',
                'start_date' => now()->subMonth(),
                'expiry_date' => now()->addMonths(11),
                'max_users' => 10,
                'max_branches' => 3,
            ]);

            $clinic = Clinic::query()->firstOrCreate(
                ['email' => $email],
                $basePayload
            );

            $this->fillMissingAttributes($clinic, 'clinics', [
                'name' => $clinic->name ?: ($basePayload['name'] ?? null),
                'owner_name' => $clinic->owner_name ?: ($basePayload['owner_name'] ?? null),
                'phone' => $clinic->phone ?: ($basePayload['phone'] ?? null),
                'address' => $clinic->address ?: ($basePayload['address'] ?? null),
                'subdomain' => $clinic->subdomain ?: ($basePayload['subdomain'] ?? null),
                'clinic_type' => $clinic->clinic_type ?: ($basePayload['clinic_type'] ?? null),
                'is_external' => $clinic->is_external ?? false,
                'notes' => $clinic->notes ?: ($basePayload['notes'] ?? null),
                'added_by' => $clinic->added_by ?: $adminId,
                'registration_date' => $clinic->registration_date ?: ($basePayload['registration_date'] ?? null),
                'subscription_plan' => $clinic->subscription_plan ?: 'Basic',
                'payment_method' => $clinic->payment_method ?: 'Manual',
                'status' => $clinic->status ?: 'Active',
                'start_date' => $clinic->start_date ?: now()->subMonth(),
                'expiry_date' => $clinic->expiry_date ?: now()->addMonths(11),
                'max_users' => $clinic->max_users ?: 10,
                'max_branches' => $clinic->max_branches ?: 3,
            ]);

            $partnershipDefaults = $this->completePayload('clinic_lab_partnerships', [
                'clinic_id' => $clinic->id,
                'lab_id' => $labId,
                'status' => ClinicLabPartnership::STATUS_ACTIVE,
                'partnership_start_date' => now()->subMonths(3)->toDateString(),
                'total_cases_sent' => 0,
                'last_case_date' => null,
                'invited_by' => $adminId,
            ]);

            $partnership = ClinicLabPartnership::query()->firstOrCreate(
                ['clinic_id' => $clinic->id, 'lab_id' => $labId],
                $partnershipDefaults
            );

            $this->fillMissingAttributes($partnership, 'clinic_lab_partnerships', [
                'status' => ClinicLabPartnership::STATUS_ACTIVE,
                'partnership_start_date' => $partnership->partnership_start_date ?: now()->subMonths(3)->toDateString(),
                'total_cases_sent' => $partnership->total_cases_sent ?? 0,
                'invited_by' => $partnership->invited_by ?: $adminId,
            ]);

            $clinics[] = $clinic->refresh();
        }

        return $clinics;
    }

    private function ensureTechnicians(int $labId): void
    {
        for ($i = 1; $i <= self::TECHNICIANS_COUNT; $i++) {
            $email = "labtech{$i}.lab{$labId}@example.com";
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                $this->completePayload('users', [
                    'name' => $this->faker->name(),
                    'username' => "labtech{$i}_{$labId}",
                    'email' => $email,
                    'phone' => $this->fakePhone(1400 + $i),
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'is_verified' => true,
                    'status' => UserStatus::Active->value,
                    'role' => LabRole::LabTechnician->value,
                    'lab_id' => $labId,
                    'commission_rates' => ['default' => 0],
                    'email_verified_at' => now(),
                ])
            );

            $this->fillMissingAttributes($user, 'users', [
                'name' => $user->name ?: $this->faker->name(),
                'username' => $user->username ?: "labtech{$i}_{$labId}",
                'phone' => $user->phone ?: $this->fakePhone(1400 + $i),
                'password' => $user->password ?: Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
                'status' => UserStatus::Active->value,
                'role' => LabRole::LabTechnician->value,
                'lab_id' => $labId,
                'clinic_id' => null,
                'commission_rates' => $user->commission_rates ?: ['default' => 0],
            ]);

            if (! $user->hasRole(LabRole::LabTechnician->value)) {
                $user->assignRole(LabRole::LabTechnician->value);
            }
        }
    }

    /**
     * @param  array<int, Clinic>  $clinics
     */
    private function ensureDoctors(array $clinics): void
    {
        for ($i = 1; $i <= self::DOCTORS_COUNT; $i++) {
            $clinic = $clinics[($i - 1) % count($clinics)];
            $email = "lookup.doctor{$i}.clinic{$clinic->id}@example.com";

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                $this->completePayload('users', [
                    'name' => 'Dr. ' . $this->faker->name(),
                    'username' => "lookupdoctor{$i}",
                    'email' => $email,
                    'phone' => $this->fakePhone(1500 + $i),
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'is_verified' => true,
                    'status' => UserStatus::Active->value,
                    'role' => 'doctor',
                    'clinic_id' => $clinic->id,
                    'email_verified_at' => now(),
                ])
            );

            $this->fillMissingAttributes($user, 'users', [
                'name' => $user->name ?: ('Dr. ' . $this->faker->name()),
                'username' => $user->username ?: "lookupdoctor{$i}",
                'phone' => $user->phone ?: $this->fakePhone(1500 + $i),
                'password' => $user->password ?: Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
                'status' => UserStatus::Active->value,
                'role' => 'doctor',
                'clinic_id' => $clinic->id,
                'lab_id' => null,
            ]);

            if (! $user->hasRole('doctor')) {
                $user->assignRole('doctor');
            }

            $doctor = Doctor::query()->firstOrCreate(
                ['user_id' => $user->id],
                $this->completePayload('doctors', [
                    'user_id' => $user->id,
                    'specialization' => Arr::random([
                        'General Dentistry',
                        'Orthodontics',
                        'Prosthodontics',
                        'Periodontics',
                        'Endodontics',
                    ]),
                    'license_number' => sprintf('LAB12-DOC-%04d', $i),
                ])
            );

            $this->fillMissingAttributes($doctor, 'doctors', [
                'specialization' => $doctor->specialization ?: 'General Dentistry',
                'license_number' => $doctor->license_number ?: sprintf('LAB12-DOC-%04d', $i),
            ]);
        }
    }

    /**
     * @param  array<int, Clinic>  $clinics
     */
    private function ensurePatients(array $clinics): void
    {
        for ($i = 1; $i <= self::PATIENTS_COUNT; $i++) {
            $clinic = $clinics[($i - 1) % count($clinics)];
            $email = "lookup.patient{$i}.clinic{$clinic->id}@example.com";

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                $this->completePayload('users', [
                    'name' => $this->faker->name(),
                    'username' => "lookuppatient{$i}",
                    'email' => $email,
                    'phone' => $this->fakePhone(1600 + $i),
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                    'is_verified' => true,
                    'status' => UserStatus::Active->value,
                    'role' => 'patient',
                    'clinic_id' => $clinic->id,
                    'email_verified_at' => now(),
                ])
            );

            $this->fillMissingAttributes($user, 'users', [
                'name' => $user->name ?: $this->faker->name(),
                'username' => $user->username ?: "lookuppatient{$i}",
                'phone' => $user->phone ?: $this->fakePhone(1600 + $i),
                'password' => $user->password ?: Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
                'status' => UserStatus::Active->value,
                'role' => 'patient',
                'clinic_id' => $clinic->id,
                'lab_id' => null,
            ]);

            if (! $user->hasRole('patient')) {
                $user->assignRole('patient');
            }

            $patient = Patient::query()->firstOrCreate(
                ['user_id' => $user->id],
                $this->completePayload('patients', [
                    'user_id' => $user->id,
                    'date_of_birth' => $this->faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
                    'gender' => Arr::random(['male', 'female']),
                    'phone' => $user->phone,
                ])
            );

            $this->fillMissingAttributes($patient, 'patients', [
                'date_of_birth' => $patient->date_of_birth ?: $this->faker->date('Y-m-d', '-18 years'),
                'gender' => $patient->gender ?: Arr::random(['male', 'female']),
                'phone' => $patient->phone ?: $user->phone,
            ]);
        }
    }

    private function fillMissingAttributes(Model $model, string $table, array $attributes): void
    {
        $payload = $this->filterColumns($table, $attributes);
        $changes = [];

        foreach ($payload as $column => $value) {
            $current = $model->getAttribute($column);

            if ($this->shouldFillValue($current, $value)) {
                $changes[$column] = $value;
            }
        }

        if ($changes !== []) {
            if ($this->hasColumn($table, 'updated_at')) {
                $changes['updated_at'] = now();
            }

            $model->forceFill($changes)->save();
        }
    }

    private function shouldFillValue(mixed $current, mixed $newValue): bool
    {
        if ($newValue === null) {
            return false;
        }

        if ($current === null) {
            return true;
        }

        if (is_string($current) && trim($current) === '') {
            return true;
        }

        if (is_array($current) && $current === []) {
            return true;
        }

        return false;
    }

    private function completePayload(string $table, array $payload): array
    {
        $payload = $this->filterColumns($table, $payload);

        foreach ($this->requiredColumns($table) as $column) {
            if (! array_key_exists($column, $payload)) {
                $payload[$column] = $this->fallbackValueForRequiredColumn($table, $column);
            }
        }

        return $this->withTimestamps($table, $payload);
    }

    private function fallbackValueForRequiredColumn(string $table, string $column): mixed
    {
        return match ($table . '.' . $column) {
            'dental_labs.name' => 'Generated Lab',
            'clinics.name' => 'Generated Clinic',
            'clinics.owner_name' => 'Generated Owner',
            'clinics.phone' => $this->fakePhone(2001),
            'clinics.address' => 'Generated Address',
            'clinics.subscription_plan' => 'Basic',
            'clinics.payment_method' => 'Manual',
            'clinics.status' => 'Active',
            'clinics.max_users' => 10,
            'clinics.max_branches' => 3,
            'users.name' => 'Generated User',
            'users.email' => 'generated-' . Str::lower(Str::random(8)) . '@example.com',
            'users.password' => Hash::make('password123'),
            'doctors.specialization' => 'General Dentistry',
            'doctors.license_number' => 'AUTO-' . Str::upper(Str::random(10)),
            default => null,
        };
    }

    private function withTimestamps(string $table, array $payload): array
    {
        if ($this->hasColumn($table, 'created_at') && ! array_key_exists('created_at', $payload)) {
            $payload['created_at'] = now();
        }

        if ($this->hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function requiredColumns(string $table): array
    {
        if (isset($this->requiredColumnsCache[$table])) {
            return $this->requiredColumnsCache[$table];
        }

        $driver = DB::getDriverName();

        $required = match ($driver) {
            'sqlite' => collect(DB::select("PRAGMA table_info('{$table}')"))
                ->filter(fn ($column) => (int) ($column->notnull ?? 0) === 1
                    && (int) ($column->pk ?? 0) === 0
                    && $column->dflt_value === null)
                ->pluck('name')
                ->values()
                ->all(),
            'mysql' => collect(DB::select(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND IS_NULLABLE = ?
                   AND COLUMN_DEFAULT IS NULL
                   AND EXTRA NOT LIKE ?',
                [$table, 'NO', '%auto_increment%']
            ))->pluck('COLUMN_NAME')->values()->all(),
            default => [],
        };

        return $this->requiredColumnsCache[$table] = array_values(array_filter(
            $required,
            fn (string $column) => ! in_array($column, ['id', 'remember_token'], true)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        if (! isset($this->columnsCache[$table])) {
            $this->columnsCache[$table] = Schema::getColumnListing($table);
        }

        return $this->columnsCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    private function filterColumns(string $table, array $payload): array
    {
        return array_filter(
            $payload,
            fn (string $column) => $this->hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function fakePhone(int $suffix): string
    {
        return '2010000' . str_pad((string) $suffix, 4, '0', STR_PAD_LEFT);
    }
}
