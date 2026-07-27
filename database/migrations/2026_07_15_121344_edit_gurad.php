<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $clinicAdminRole = Role::firstOrCreate(['name' => 'clinic_admin', 'guard_name' => 'web']);

        User::role('Admin')->get()->each(function (User $user) use ($clinicAdminRole) {
            $user->removeRole('Admin');
            $user->assignRole($clinicAdminRole);
        });
    }

    public function down(): void {}
};
