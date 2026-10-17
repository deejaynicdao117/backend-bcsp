<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VerificationCodeMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarangayPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_register_and_login(): void
    {
        $sentCode = null;
        $mailer = $this->mock(VerificationCodeMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (User $user, string $code) use (&$sentCode): bool {
                $sentCode = $code;

                return $user->email === 'maria@example.com'
                    && strlen($code) === 6;
            });

        $response = $this->postJson('/api/auth/register', [
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Luisa',
            'sex' => 'female',
            'email' => ' Maria@Example.com ',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('email', 'maria@example.com')
            ->assertJsonMissingPath('token');

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'role' => 'resident',
            'name' => 'Maria Luisa Santos',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('resident_profiles', [
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Luisa',
            'sex' => 'female',
        ]);

        $this->postJson('/api/auth/register/verify', [
            'email' => 'maria@example.com',
            'code' => $sentCode,
        ])->assertOk()
            ->assertJsonPath('user.role', 'resident')
            ->assertJsonStructure(['token', 'access_token', 'user']);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => ' Maria@EXAMPLE.COM ',
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

    public function test_unverified_resident_cannot_log_in_and_bad_codes_are_rejected(): void
    {
        $sentCode = null;
        $mailer = $this->mock(VerificationCodeMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (User $user, string $code) use (&$sentCode): bool {
                $sentCode = $code;

                return true;
            });

        $this->postJson('/api/auth/register', [
            'last_name' => 'User',
            'first_name' => 'Resident',
            'sex' => 'prefer_not_to_say',
            'email' => 'resident@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertCreated();

        $this->postJson('/api/auth/login', [
            'email' => 'resident@example.com',
            'password' => 'Password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register/verify', [
            'email' => 'resident@example.com',
            'code' => $sentCode === '000000' ? '000001' : '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/auth/register/verify', [
            'email' => 'resident@example.com',
            'code' => $sentCode,
        ])->assertOk();
    }

    public function test_api_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'last_name',
                'first_name',
                'sex',
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
        Storage::fake('local');

        $resident = User::factory()->create([
            'role' => 'resident',
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/document-types', [
            'name' => 'Barangay Clearance',
            'description' => 'For local clearance requests',
        ])->assertStatus(201);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/admin/document-types', [
                'name' => 'Staff-created type',
            ])->assertForbidden();

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
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.generated_document_available', true);

        $generatedDocument = 'document-requests/1/DOC-'.now()->format('Y').'-000001.pdf';
        Storage::disk('local')->assertExists($generatedDocument);

        $this->get('/api/staff/document-requests/1/document')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($resident, 'sanctum')
            ->get('/api/staff/document-requests/1/document')
            ->assertForbidden();
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

    public function test_admin_created_resident_is_verified_and_can_sign_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $created = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'Admin Added Resident',
                'email' => 'admin.added.resident@example.com',
                'password' => 'Password123',
                'role' => 'resident',
            ])->assertCreated()
                ->assertJsonPath('data.role', 'resident');

        $this->assertNotNull(User::findOrFail($created->json('data.id'))->email_verified_at);

        $this->postJson('/api/auth/login', [
            'email' => 'admin.added.resident@example.com',
            'password' => 'Password123',
        ])->assertOk()
            ->assertJsonPath('user.role', 'resident');
    }
}
