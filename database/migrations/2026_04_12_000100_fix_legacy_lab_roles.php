<?php

use App\Enums\LabRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $roleNames = [
            LabRole::LabAdmin->value,
            LabRole::LabReceptionist->value,
            LabRole::LabTechnician->value,
            LabRole::DeliveryRepresentative->value,
        ];

        foreach ($roleNames as $roleName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        $legacyLabRoleId = DB::table('roles')
            ->where('name', 'lab')
            ->where('guard_name', 'web')
            ->value('id');

        $labAdminRoleId = DB::table('roles')
            ->where('name', LabRole::LabAdmin->value)
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('users')
            ->where('role', 'lab')
            ->update(['role' => LabRole::LabAdmin->value]);

        if ($legacyLabRoleId && $labAdminRoleId) {
            $legacyAssignments = DB::table('model_has_roles')
                ->where('role_id', $legacyLabRoleId)
                ->get();

            foreach ($legacyAssignments as $assignment) {
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => $labAdminRoleId,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }
        }

        $labScopedUsers = DB::table('users')
            ->whereIn('role', $roleNames)
            ->whereNull('lab_id')
            ->get(['id', 'name', 'email', 'phone', 'created_at']);

        foreach ($labScopedUsers as $user) {
            $labId = DB::table('dental_labs')->insertGetId([
                'name' => $user->name ?: 'Lab '.$user->id,
                'contact_person' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => 'Active',
                'avg_delivery_days' => 0,
                'date_added' => $now->toDateString(),
                'created_at' => $user->created_at ?? $now,
                'updated_at' => $now,
            ]);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['lab_id' => $labId]);
        }

        if ($legacyLabRoleId) {
            DB::table('model_has_roles')
                ->where('role_id', $legacyLabRoleId)
                ->delete();

            DB::table('roles')
                ->where('id', $legacyLabRoleId)
                ->delete();
        }
    }

    public function down(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'lab', 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
};
