<?php

namespace Tests\Feature\Owner;

use App\Models\AiAlert;
use App\Models\Clinic;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\DentalLab;
use App\Models\MaintenanceCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('doctor', 'web');
    }

    public function test_non_superadmin_cannot_access_owner_maintenance_routes(): void
    {
        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole('doctor');
        Sanctum::actingAs($user);

        $this->getJson('/api/owner/maintenance/requests')->assertStatus(403);
    }

    public function test_superadmin_can_create_and_list_maintenance_requests(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $clinic = Clinic::query()->create([
            'name' => 'Clinic A',
            'owner_name' => 'Owner A',
            'email' => 'clinica@example.com',
            'phone' => '0100000000',
            'address' => 'Addr',
            'subscription_plan' => 'Basic',
            'payment_method' => 'Manual',
            'status' => 'Active',
            'start_date' => now()->subDays(10),
            'expiry_date' => now()->addDays(20),
            'max_users' => 3,
            'max_branches' => 1,
        ]);

        $company = MaintenanceCompany::query()->create([
            'name' => 'Maint Co',
            'status' => 'Active',
        ]);

        $payload = [
            'clinic_id' => $clinic->id,
            'equipment' => 'Dental Chair',
            'issue_description' => 'Motor issue',
            'assigned_company_id' => $company->id,
        ];

        $this->postJson('/api/owner/maintenance/requests', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.clinicId', $clinic->id);

        $this->getJson('/api/owner/maintenance/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination',
                ],
            ]);
    }

    public function test_maintenance_request_update_returns_404_when_missing(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/owner/maintenance/requests/99999', ['status' => 'Resolved'])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_review_alert_endpoint_marks_alert_as_reviewed(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $company = MaintenanceCompany::query()->create(['name' => 'Alert Co']);
        $alert = AiAlert::query()->create([
            'type' => 'Performance',
            'title' => 'Late response',
            'message' => 'Too many delayed tickets',
            'severity' => 'High',
            'company_id' => $company->id,
            'is_reviewed' => false,
        ]);

        $this->patchJson("/api/owner/maintenance/alerts/{$alert->id}/review")
            ->assertOk()
            ->assertJsonPath('data.isReviewed', true);
    }

    public function test_superadmin_can_fetch_and_reply_to_conversation(): void
    {
        $admin = User::factory()->create(['is_active' => 1, 'name' => 'Super Admin']);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $clinic = Clinic::query()->create([
            'name' => 'Clinic B',
            'owner_name' => 'Owner B',
            'email' => 'clinicb@example.com',
            'phone' => '0100000001',
            'address' => 'Addr',
            'subscription_plan' => 'Standard',
            'payment_method' => 'Manual',
            'status' => 'Active',
            'start_date' => now()->subDays(5),
            'expiry_date' => now()->addDays(40),
            'max_users' => 5,
            'max_branches' => 2,
        ]);

        $lab = DentalLab::query()->create([
            'name' => 'Lab X',
            'avg_delivery_days' => 2,
            'status' => 'Active',
        ]);

        $conversation = CommunicationConversation::query()->create([
            'clinic_id' => $clinic->id,
            'lab_id' => $lab->id,
            'status' => 'Open',
        ]);

        CommunicationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'clinic',
            'sender_name' => 'Clinic Admin',
            'text' => 'Need an update',
            'type' => 'text',
            'is_read' => false,
        ]);

        $this->getJson('/api/owner/communication/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/owner/communication/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/owner/communication/conversations/{$conversation->id}/messages", [
            'text' => 'We are on it',
        ])->assertStatus(201)
            ->assertJsonPath('data.senderType', 'super-admin');
    }

    public function test_conversation_status_validation_works(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $conversation = CommunicationConversation::query()->create([
            'status' => 'Open',
        ]);

        $this->patchJson("/api/owner/communication/conversations/{$conversation->id}", [
            'status' => 'invalid',
        ])->assertStatus(422);
    }

    public function test_superadmin_can_fetch_renewal_alerts_and_send_reminders(): void
    {
        $admin = User::factory()->create(['is_active' => 1]);
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $clinic = Clinic::query()->create([
            'name' => 'Clinic C',
            'owner_name' => 'Owner C',
            'email' => 'clinicc@example.com',
            'phone' => '0100000002',
            'address' => 'Addr',
            'subscription_plan' => 'Premium',
            'payment_method' => 'Manual',
            'status' => 'Active',
            'start_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(7),
            'max_users' => 10,
            'max_branches' => 2,
        ]);

        $this->getJson('/api/owner/alerts/renewal?tab=expiring_soon')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tab',
                    'items',
                    'pagination',
                    'summary',
                ],
            ]);

        $this->postJson('/api/owner/alerts/renewal/reminders', [
            'clinic_ids' => [$clinic->id],
            'channel' => 'email',
            'message' => 'Subscription renewal reminder',
        ])->assertStatus(201)
            ->assertJsonPath('data.sent', 1);
    }
}
