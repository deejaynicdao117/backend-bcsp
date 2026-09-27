<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_register_and_login(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'resident');

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'role' => 'resident',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'maria@example.com',
            'password' => 'Password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'access_token',
                'user' => ['id', 'name', 'role'],
            ])
            ->assertJsonPath('access_token', $loginResponse->json('token'));
    }

    public function test_api_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->post('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);

        $resident = User::factory()->create([
            'role' => 'resident',
        ]);

        $this->actingAs($resident, 'sanctum')
            ->post('/api/resident/document-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'document_type_id',
                'purpose',
            ]);
    }

    public function test_logout_without_a_bearer_token_returns_json_unauthorized(): void
    {
        $this->post('/api/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_resident_can_submit_document_request_and_staff_can_process_it(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
        ]);

        $this->actingAs($staff, 'sanctum');

        $this->postJson('/api/document-types', [
            'name' => 'Barangay Clearance',
            'description' => 'For local clearance requests',
        ])->assertStatus(201);

        $this->actingAs($resident, 'sanctum');

        $requestResponse = $this->postJson('/api/resident/document-requests', [
            'document_type_id' => 1,
            'purpose' => 'Employment requirement',
        ]);

        $requestResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($staff, 'sanctum');

        $processingResponse = $this->putJson('/api/staff/document-requests/1', [
            'status' => 'approved',
            'remarks' => 'Verified by staff',
        ]);

        $processingResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_admin_can_login_and_fetch_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('user.role', 'admin');

        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'email',
                    'role',
                ]],
            ]);
    }

    public function test_admin_can_manage_users_and_view_dashboard_modules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $created = $this->postJson('/api/admin/users', [
            'name' => 'New Staff Member',
            'email' => 'new.staff@example.com',
            'password' => 'Password123',
            'role' => 'staff',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'staff');

        $userId = $created->json('data.id');

        $this->putJson("/api/admin/users/{$userId}", [
            'name' => 'Updated Staff Member',
            'role' => 'resident',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Staff Member')
            ->assertJsonPath('data.role', 'resident');

        $this->getJson('/api/admin/document-requests')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['requests_by_status', 'monthly_requests', 'total_requests'],
            ]);

        $this->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->deleteJson("/api/admin/users/{$userId}")
            ->assertOk();

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable();
    }
}
