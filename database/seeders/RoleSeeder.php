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
            'chat.view',
            'chat.manage',
            'delivery.view',
            'delivery.assign',
            'delivery.update-location',
            'delivery.update-status',
            'support.view',
            'support.create',
            'users.manage',
            'products.manage',
            'orders.manage',
            'billing.manage',
            'reports.view',
            'settings.manage',
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
                'orders.manage',
                'billing.manage',
                'reports.view',
                'settings.manage',
                'users.manage',
            ],
            'sales_rep' => [
                'products.manage',
                'orders.manage',
                'billing.manage',
                'reports.view',
            ],
            'delivery_staff' => [
                'orders.manage',
            ],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            Role::findByName($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
