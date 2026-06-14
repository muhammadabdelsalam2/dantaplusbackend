<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'super-admin',
            'Admin',
            'doctor',
            'patient',
            'lab_admin',
            'lab_receptionist',
            'lab_technician',
            'delivery_representative',
            'material_company_admin',
            'sales_rep',
            'delivery_staff',
            'clinic_admin',
            'nurse',
            'accountant',
            'receptionist',
            'staff',
        ];

        $permissions = [
            'cases.view',
            'cases.create',
            'cases.update',
            'cases.assign-technician',
            'cases.assign-delivery',
            'cases.update-status',
            'inventory.view',
            'inventory.manage',
            'materials.view',
            'chat.view',
            'chat.manage',
            'delivery.view',
            'delivery.assign',
            'delivery.update-location',
            'delivery.update-status',
            'support.view',
            'support.create',
            'support.manage',
            'users.manage',
            'products.manage',
            'orders.view',
            'orders.manage',
            'billing.manage',
            'reports.view',
            'settings.manage',
            'patients.view',
            'patients.create',
            'patients.update',
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'treatments.manage',
            'labs.send',
            'labs.track',
            'communication.send',
            'tasks.view',
            'tasks.manage',
            'dental_labs.view',
            'dental_labs.manage',
            'providers.view',
            'providers.manage',
            'equipment.view',

            'insurance.view',
            'insurance.create',
            'insurance.update',
            'insurance.delete',
            'send_text',
            'send_voice',
            'send_file',
            'access_patient_discussion',
            'delete_message',
            'patient.dashboard.view',
            'patient.profile.view',
            'patient.profile.update',
            'patient.appointments.view',
            'patient.appointments.create',
            'patient.appointments.cancel',
            'patient.invoices.view',
            'patient.payments.view',
            'patient.refunds.create',
            'patient.documents.view',
            'patient.insurance.view',
            'patient.ratings.create',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
        // Console roles Details in table format
        $this->command->info('Assigning permissions to roles...');
        // Show Roles and their permissions in a table format

        $matrix = [
            'lab_admin' => $permissions,
            'lab_receptionist' => [
                'cases.view',
                'cases.create',
                'cases.update',
                'cases.assign-technician',
                'cases.assign-delivery',
                'cases.update-status',
                'inventory.view',
                'inventory.manage',
                'chat.view',
                'chat.manage',
                'delivery.view',
                'delivery.assign',
                'delivery.update-status',
                'support.view',
                'support.create',
            ],
            'lab_technician' => [
                'cases.view',
                'cases.update',
                'cases.update-status',
                'inventory.view',
                'chat.view',
                'support.view',
            ],
            'delivery_representative' => [
                'cases.view',
                'delivery.view',
                'delivery.update-location',
                'delivery.update-status',
                'support.view',
            ],
            'material_company_admin' => [
                'products.manage',
                'inventory.view',
                'inventory.manage',
                'orders.view',
                'orders.manage',
                'billing.manage',
                'reports.view',
                'settings.manage',
                'users.manage',
            ],
            'sales_rep' => [
                'products.manage',
                'orders.view',
                'orders.manage',
                'billing.manage',
                'reports.view',
            ],
            'delivery_staff' => [
                'orders.view',
                'orders.manage',
            ],
            'clinic_admin' => [
                'users.manage',
                'reports.view',
                'settings.manage',
                'materials.view',
                'inventory.view',
                'inventory.manage',
                'orders.view',
                'orders.manage',
                'patients.view',
                'patients.create',
                'patients.update',
                'appointments.view',
                'appointments.create',
                'appointments.update',
                'treatments.manage',
                'billing.manage',
                'labs.send',
                'labs.track',
                'communication.send',
                'tasks.view',
                'tasks.manage',
                'dental_labs.view',
                'dental_labs.manage',
                'providers.view',
                'providers.manage',
                'equipment.view',
                'insurance.view',
                'insurance.create',
                'insurance.update',
                'insurance.delete',
                'support.view',
                'support.manage',
            ],
            'doctor' => [
                'patients.view',
                'appointments.view',
                'appointments.update',
                'treatments.manage',
                'labs.send',
                'labs.track',
                'reports.view',
                'communication.send',
                'tasks.view',
                'tasks.manage',
                'orders.view',
                'dental_labs.view',
                'dental_labs.manage',
                'providers.view',
                'providers.manage',
                'equipment.view',
                'insurance.view',
                'insurance.create',
                'insurance.update',
            ],
            'nurse' => [
                'patients.view',
                'patients.create',
                'patients.update',
                'appointments.view',
                'appointments.create',
                'appointments.update',
                'communication.send',
                'tasks.view',
                'orders.view',
                'dental_labs.view',
                'providers.view',
                'providers.manage',
                'equipment.view',
                'insurance.view',
                'insurance.create',
            ],
            'accountant' => [
                'patients.view',
                'billing.manage',
                'reports.view',
                'tasks.view',
                'orders.view',
                'dental_labs.view',
                'providers.view',
                'equipment.view',
                'insurance.view',
                'insurance.update',
            ],
            'receptionist' => [
                'patients.view',
                'patients.create',
                'appointments.view',
                'appointments.create',
                'appointments.update',
                'communication.send',
                'tasks.view',
                'tasks.manage',
                'orders.view',
                'dental_labs.view',
                'dental_labs.manage',
                'providers.view',
                'providers.manage',
                'equipment.view',
                'insurance.view',
            ],
            'staff' => [
                'patients.view',
                'appointments.view',
                'tasks.view',
                'orders.view',
                'dental_labs.view',
                'providers.view',
                'equipment.view',
                'insurance.view',
            ],
            'patient' => [
                'patient.dashboard.view',
                'patient.profile.view',
                'patient.profile.update',
                'patient.appointments.view',
                'patient.appointments.create',
                'patient.appointments.cancel',
                'patient.invoices.view',
                'patient.payments.view',
                'patient.refunds.create',
                'patient.documents.view',
                'patient.insurance.view',
                'patient.ratings.create',
            ],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            Role::findByName($roleName, 'web')->syncPermissions($rolePermissions);

            $this->command->info('Assigning permissions to roles...');

            $this->command->table(
                ['Role', 'Permissions'],
                collect($roles)->map(function ($role) {
                    return [
                        'Role' => $role,
                        'Permissions' => implode(', ', Role::findByName($role, 'web')->permissions->pluck('name')->toArray()),
                    ];
                })->toArray()
            );
        }
    }
}
