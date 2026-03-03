<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist in test DB
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('doctor', 'web');
        Role::findOrCreate('patient', 'web');
    }

    public function test_guest_cannot_access_superadmin_users(): void
    {
        $res = $this->getJson('/api/superadmin/users');
        $res->assertStatus(401);
    }

    public function test_non_superadmin_cannot_access_superadmin_users(): void
    {
        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole('doctor');

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/superadmin/users');
        $res->assertStatus(403);
    }

    public function test_superadmin_can_list_users_with_pagination(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        User::factory()->count(3)->create(['is_active' => 1]);

        $res = $this->getJson('/api/superadmin/users?per_page=2');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'pagination' => ['current_page','per_page','total','last_page'],
                ],
            ]);
    }

    public function test_superadmin_can_create_user_and_assign_role(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'is_active' => true,
            'role' => 'doctor',
        ];

        $res = $this->postJson('/api/superadmin/users', $payload);
        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'is_active' => 1,
        ]);

        $created = User::where('email', 'new@example.com')->first();
        $this->assertTrue($created->hasRole('doctor'));
    }

    public function test_superadmin_can_update_user(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $user = User::factory()->create([
            'name' => 'Old',
            'email' => 'old@example.com',
            'is_active' => 1
        ]);
        $user->assignRole('patient');

        $payload = [
            'name' => 'New Name',
            'role' => 'doctor',
            'is_active' => false,
        ];

        $res = $this->patchJson("/api/superadmin/users/{$user->id}", $payload);
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', false);

        $user->refresh();
        $this->assertTrue($user->hasRole('doctor'));
        $this->assertEquals(0, (int) $user->is_active);
    }

    public function test_superadmin_can_toggle_status(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole('patient');

        $res = $this->patchJson("/api/superadmin/users/{$user->id}/status");
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => 0,
        ]);
    }

    public function test_superadmin_can_delete_user(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole('patient');

        $res = $this->deleteJson("/api/superadmin/users/{$user->id}");
        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_superadmin_can_list_roles(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $res = $this->getJson('/api/superadmin/roles');
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    ['id','name','guard_name'],
                ]
            ]);
    }
}
