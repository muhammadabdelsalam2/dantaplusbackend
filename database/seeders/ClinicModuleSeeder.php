<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\ClinicExpense;
use App\Models\ClinicExpenseCategory;
use App\Models\ClinicTask;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClinicModuleSeeder extends Seeder
{
    private const CLINIC_ID = 26;

    public function run(): void
    {
        //  Use existing clinic only (no creation)
        $clinic = Clinic::findOrFail(self::CLINIC_ID);

        // Roles
        foreach (['doctor', 'patient', 'clinic_admin', 'receptionist', 'staff'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // Clinic Admin
        $clinicAdmin = User::updateOrCreate(
            ['email' => 'admin.clinic26@dentaplus.local'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Clinic Admin 26',
                'username' => 'clinicadmin26',
                'phone' => '01026000001',
                'password' => Hash::make('Password@123'),
                'role' => 'clinic_admin',
                'status' => 'Active',
                'is_active' => true,
                'is_verified' => true,
            ]
        );
        $clinicAdmin->syncRoles(['clinic_admin']);

        // Staff
        $staffUser = User::updateOrCreate(
            ['email' => 'staff.clinic26@dentaplus.local'],
            [
                'clinic_id' => $clinic->id,
                'name' => 'Mona Staff',
                'username' => 'monastaff26',
                'phone' => '01026000002',
                'password' => Hash::make('Password@123'),
                'role' => 'staff',
                'status' => 'Active',
                'is_active' => true,
                'is_verified' => true,
            ]
        );
        $staffUser->syncRoles(['staff']);

        // Doctors
        $doctorUsers = collect([
            [
                'name' => 'Dr. Emily Adams',
                'email' => 'doctor.emily26@dentaplus.local',
                'username' => 'emilyadams26',
                'phone' => '01026000003',
                'specialization' => 'Orthodontics',
                'license_number' => 'DOC-2601',
            ],
            [
                'name' => 'Dr. John Carter',
                'email' => 'doctor.john26@dentaplus.local',
                'username' => 'johncarter26',
                'phone' => '01026000004',
                'specialization' => 'Endodontics',
                'license_number' => 'DOC-2602',
            ],
        ])->map(function ($doctorData) use ($clinic) {

            $user = User::updateOrCreate(
                ['email' => $doctorData['email']],
                [
                    'clinic_id' => $clinic->id,
                    'name' => $doctorData['name'],
                    'username' => $doctorData['username'],
                    'phone' => $doctorData['phone'],
                    'password' => Hash::make('Password@123'),
                    'role' => 'doctor',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );

            $user->syncRoles(['doctor']);

            $doctor = Doctor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $doctorData['specialization'],
                    'license_number' => $doctorData['license_number'],
                ]
            );

            return compact('user', 'doctor');
        });

        // Patients
        $patients = collect([
            [
                'name' => 'Emma Brown',
                'email' => 'emma.brown26@dentaplus.local',
                'username' => 'emmabrown26',
                'phone' => '01026000005',
                'gender' => 'female',
                'patient_number' => 'PID-2600001',
            ],
            [
                'name' => 'Liam Smith',
                'email' => 'liam.smith26@dentaplus.local',
                'username' => 'liamsmith26',
                'phone' => '01026000006',
                'gender' => 'male',
                'patient_number' => 'PID-2600002',
            ],
        ])->map(function ($patientData) use ($clinic) {

            $user = User::updateOrCreate(
                ['email' => $patientData['email']],
                [
                    'clinic_id' => $clinic->id,
                    'name' => $patientData['name'],
                    'username' => $patientData['username'],
                    'phone' => $patientData['phone'],
                    'password' => Hash::make('Password@123'),
                    'role' => 'patient',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_verified' => true,
                ]
            );

            $user->syncRoles(['patient']);

            return Patient::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'clinic_id' => $clinic->id,
                    'patient_number' => $patientData['patient_number'],
                    'phone' => $patientData['phone'],
                    'gender' => $patientData['gender'],
                    'address' => 'Cairo, Egypt',
                ]
            );
        });

        // Expense Categories
        $salaryCategory = ClinicExpenseCategory::updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Doctor Salaries'],
            ['status' => 'active']
        );

        $rentCategory = ClinicExpenseCategory::updateOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Rent & Utilities'],
            ['status' => 'active']
        );

        // Tasks
        ClinicTask::updateOrCreate(
            ['clinic_id' => $clinic->id, 'title' => 'Review lab results for Emma Brown'],
            [
                'description' => 'Check incoming lab report and update patient discussion.',
                'assign_to_doctor_id' => $doctorUsers[0]['doctor']->id,
                'priority' => 'low',
                'status' => 'done',
                'due_date' => now()->toDateString(),
                'created_by' => $clinicAdmin->id,
            ]
        );

        ClinicTask::updateOrCreate(
            ['clinic_id' => $clinic->id, 'title' => 'Follow up with patient Liam Smith'],
            [
                'description' => 'Confirm next appointment and treatment notes.',
                'assign_to_user_id' => $staffUser->id,
                'priority' => 'high',
                'status' => 'todo',
                'due_date' => now()->addDay()->toDateString(),
                'created_by' => $clinicAdmin->id,
            ]
        );

        // Expenses
        ClinicExpense::updateOrCreate(
            ['clinic_id' => $clinic->id, 'title' => 'April Doctor Salary'],
            [
                'expense_category_id' => $salaryCategory->id,
                'amount' => 3500,
                'payment_method' => 'bank_transfer',
                'expense_date' => now()->toDateString(),
                'assigned_to_user_id' => $doctorUsers[0]['user']->id,
                'notes' => 'Salary payout for clinic dentist.',
            ]
        );

        ClinicExpense::updateOrCreate(
            ['clinic_id' => $clinic->id, 'title' => 'Electricity Bill'],
            [
                'expense_category_id' => $rentCategory->id,
                'amount' => 450,
                'payment_method' => 'cash',
                'expense_date' => now()->subDays(2)->toDateString(),
                'assigned_to_user_id' => $staffUser->id,
                'notes' => 'Monthly utility bill.',
            ]
        );
    }
}
