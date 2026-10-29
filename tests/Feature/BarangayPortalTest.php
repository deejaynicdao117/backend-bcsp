<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\VerificationCodeMailer;
use App\Support\CurrentBarangay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarangayPortalTest extends TestCase
{
    use RefreshDatabase;

    private function registrationBarangay(): Barangay
    {
        return Barangay::create([
            'psgc_code' => 'TEST-BULACAN-001',
            'name' => 'Test Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
            'is_active' => true,
            'onboarding_status' => 'approved',
        ]);
    }

    public function test_resident_can_register_and_login(): void
    {
        $barangay = $this->registrationBarangay();
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
            'barangay_id' => $barangay->id,
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
            'barangay_id' => $barangay->id,
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
        $barangay = $this->registrationBarangay();
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
            'barangay_id' => $barangay->id,
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

    public function test_verification_accepts_email_and_code_with_spacing_from_paste(): void
    {
        $barangay = $this->registrationBarangay();
        $sentCode = null;
        $mailer = $this->mock(VerificationCodeMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (User $user, string $code) use (&$sentCode): bool {
                $sentCode = $code;

                return true;
            });

        $this->postJson('/api/auth/register', [
            'last_name' => 'Doe',
            'first_name' => 'Jane',
            'sex' => 'female',
            'barangay_id' => $barangay->id,
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertCreated();

        $pastedCode = substr($sentCode, 0, 3) . ' ' . substr($sentCode, 3, 2) . ' ' . substr($sentCode, 5);

        $this->postJson('/api/auth/register/verify', [
            'email' => "  JANE@EXAMPLE.COM  ",
            'code' => $pastedCode,
        ])->assertOk()
            ->assertJsonPath('user.email', 'jane@example.com');
    }

    public function test_api_validation_errors_are_json_without_accept_header(): void
    {
        $this->post('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'last_name',
                'first_name',
                'sex',
                'barangay_id',
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

        $documentTypeResponse = $this->postJson('/api/admin/document-types', [
            'name' => 'Barangay Clearance',
            'description' => 'For local clearance requests',
            'body_template' => 'First draft for [resident_name].',
        ])->assertStatus(201)
            ->assertJsonPath('body_template', 'First draft for [resident_name].');
        $documentTypeId = $documentTypeResponse->json('id');

        $this->putJson('/api/admin/document-types/'.$documentTypeId, [
            'name' => 'Barangay Clearance',
            'description' => 'For local clearance requests',
            'body_template' => "CERTIFICATION\nThis certifies [resident_name] of [barangay_name]. Purpose: [purpose].",
        ])->assertOk()
            ->assertJsonPath('data.body_template', "CERTIFICATION\nThis certifies [resident_name] of [barangay_name]. Purpose: [purpose].");

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/admin/document-types', [
                'name' => 'Staff-created type',
            ])->assertForbidden();

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
        $trackingNumber = $requestResponse->json('data.tracking_number');

        $this->actingAs($staff, 'sanctum');

        $this->putJson('/api/staff/document-requests/1', [
            'status' => 'rejected',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');

        $this->putJson('/api/staff/document-requests/1', [
            'status' => 'rejected',
            'rejection_reason' => 'Please provide a valid proof of residence.',
        ])->assertOk()
            ->assertJsonPath('data.rejection_reason', 'Please provide a valid proof of residence.');

        $processingResponse = $this->putJson('/api/staff/document-requests/1', [
            'status' => 'approved',
            'remarks' => 'Verified by staff',
        ]);

        $processingResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.generated_document_available', true);

        $generatedDocument = 'document-requests/1/'.$trackingNumber.'.pdf';
        Storage::disk('local')->assertExists($generatedDocument);
        $this->assertDatabaseHas('document_types', [
            'id' => $documentTypeId,
            'body_template' => "CERTIFICATION\nThis certifies [resident_name] of [barangay_name]. Purpose: [purpose].",
        ]);

        $this->get('/api/staff/document-requests/1/document')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->putJson('/api/staff/document-requests/1', [
            'status' => 'ready_for_pickup',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('ready_for_pickup_at');

        $this->putJson('/api/staff/document-requests/1', [
            'status' => 'ready_for_pickup',
            'ready_for_pickup_at' => '2026-10-28',
        ])->assertOk()
            ->assertJsonPath('data.ready_for_pickup_at', '2026-10-28');

        $this->putJson('/api/staff/document-requests/1', [
            'status' => 'released',
            'released_at' => '2026-10-29',
        ])->assertOk()
            ->assertJsonPath('data.released_at', '2026-10-29');

        $this->actingAs($resident, 'sanctum')
            ->get('/api/resident/document-requests/1/preview')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="'.$trackingNumber.'.pdf"');

        $otherResident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($otherResident, 'sanctum')
            ->get('/api/resident/document-requests/1/preview')
            ->assertNotFound();

        $this->actingAs($resident, 'sanctum')
            ->get('/api/staff/document-requests/1/document')
            ->assertForbidden();
    }

    public function test_authenticated_barangay_users_cannot_view_or_update_other_barangays_data(): void
    {
        $firstBarangay = Barangay::create([
            'psgc_code' => 'TEST-001',
            'name' => 'First Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
        $secondBarangay = Barangay::create([
            'psgc_code' => 'TEST-002',
            'name' => 'Second Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
        $firstAdmin = User::factory()->create(['role' => 'admin', 'barangay_id' => $firstBarangay->id]);
        $secondType = DocumentType::create(['name' => 'Private second type', 'barangay_id' => $secondBarangay->id]);

        $this->actingAs($firstAdmin, 'sanctum')
            ->getJson('/api/document-types')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Private second type']);

        $this->putJson('/api/admin/document-types/'.$secondType->id, [
            'name' => 'Hijacked document type',
        ])->assertNotFound();
    }

    public function test_tracking_numbers_and_request_alerts_are_isolated_between_barangays(): void
    {
        $firstBarangay = Barangay::create([
            'psgc_code' => 'TRACK-001',
            'name' => 'Tracking First',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
        $secondBarangay = Barangay::create([
            'psgc_code' => 'TRACK-002',
            'name' => 'Tracking Second',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
        $firstResident = User::factory()->create(['role' => 'resident', 'barangay_id' => $firstBarangay->id]);
        $secondResident = User::factory()->create(['role' => 'resident', 'barangay_id' => $secondBarangay->id]);
        $firstStaff = User::factory()->create(['role' => 'staff', 'barangay_id' => $firstBarangay->id]);
        $secondStaff = User::factory()->create(['role' => 'staff', 'barangay_id' => $secondBarangay->id]);
        $this->assertSame($firstBarangay->id, $firstResident->barangay_id);
        $this->assertSame($secondBarangay->id, $secondResident->barangay_id);
        $this->assertSame($firstBarangay->id, $firstStaff->barangay_id);
        $this->assertSame($secondBarangay->id, $secondStaff->barangay_id);
        $tenant = app(CurrentBarangay::class);
        $tenant->set($firstBarangay->id);
        $firstType = DocumentType::create(['name' => 'Clearance A']);
        $tenant->set($secondBarangay->id);
        $secondType = DocumentType::create(['name' => 'Clearance B']);
        $tenant->set(null);

        $firstResponse = $this->actingAs($firstResident, 'sanctum')
            ->postJson('/api/resident/document-requests', [
                'document_type_id' => $firstType->id,
                'purpose' => 'Employment',
            ])->assertCreated();

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $firstStaff->id,
            'type' => 'document_request.created',
        ]);
        $this->assertDatabaseMissing('portal_notifications', [
            'user_id' => $secondStaff->id,
            'type' => 'document_request.created',
        ]);

        $secondResponse = $this->actingAs($secondResident, 'sanctum')
            ->postJson('/api/resident/document-requests', [
                'document_type_id' => $secondType->id,
                'purpose' => 'School',
            ])->assertCreated();

        $firstTrackingNumber = $firstResponse->json('data.tracking_number');
        $secondTrackingNumber = $secondResponse->json('data.tracking_number');
        $this->assertNotSame($firstTrackingNumber, $secondTrackingNumber);
        $this->assertStringContainsString('TRACK001', $firstTrackingNumber);
        $this->assertStringContainsString('TRACK002', $secondTrackingNumber);

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $firstStaff->id,
            'type' => 'document_request.created',
        ]);
        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $secondStaff->id,
            'type' => 'document_request.created',
        ]);
    }

    public function test_platform_admin_can_create_a_new_barangay_workspace(): void
    {
        $platformAdmin = User::factory()->create([
            'role' => 'platform_admin',
            'barangay_id' => Barangay::where('psgc_code', 'DEFAULT')->value('id'),
        ]);

        $this->actingAs($platformAdmin, 'sanctum')
            ->postJson('/api/platform/barangays', [
                'psgc_code' => 'TEST-NEW-001',
                'name' => 'New Test Barangay',
                'municipality' => 'Test Municipality',
                'province' => 'Bulacan',
                'contact_email' => 'newtest@example.com',
                'contact_phone' => '09171234567',
                'office_address' => 'Main Plaza, Test Municipality',
                'primary_color' => '#1d4ed8',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Test Barangay')
            ->assertJsonPath('data.onboarding_status', 'approved')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('barangays', [
            'psgc_code' => 'TEST-NEW-001',
            'name' => 'New Test Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
    }

    public function test_platform_admin_can_list_bulacan_barangay_workspaces(): void
    {
        $barangay = Barangay::create([
            'psgc_code' => 'TEST-003',
            'name' => 'Platform Test Barangay',
            'municipality' => 'Test Municipality',
            'province' => 'Bulacan',
        ]);
        $platformAdmin = User::factory()->create([
            'role' => 'platform_admin',
            'barangay_id' => Barangay::where('psgc_code', 'DEFAULT')->value('id'),
        ]);

        $this->actingAs($platformAdmin, 'sanctum')
            ->getJson('/api/platform/barangays')
            ->assertOk()
            ->assertJsonFragment(['psgc_code' => $barangay->psgc_code]);

        $this->getJson('/api/locations/bulacan/barangays')
            ->assertOk()
            ->assertJsonMissing(['psgc_code' => $barangay->psgc_code]);

        $this->putJson('/api/platform/barangays/'.$barangay->id.'/onboarding-review', [
            'onboarding_status' => 'approved',
        ])->assertOk()
            ->assertJsonPath('data.onboarding_status', 'approved')
            ->assertJsonPath('data.onboarding_reviewed_by', $platformAdmin->id);

        $this->getJson('/api/locations/bulacan/barangays')
            ->assertOk()
            ->assertJsonFragment(['psgc_code' => $barangay->psgc_code]);

        $this->postJson('/api/platform/barangays/'.$barangay->id.'/users', [
            'name' => 'Local Barangay Admin',
            'email' => 'local-admin@example.com',
            'password' => 'Temporary123',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('data.barangay_id', $barangay->id)
            ->assertJsonPath('data.role', 'admin');

        $this->putJson('/api/platform/barangays/'.$barangay->id.'/onboarding-review', [
            'onboarding_status' => 'declined',
        ])->assertOk();
        $this->getJson('/api/locations/bulacan/barangays')
            ->assertOk()
            ->assertJsonMissing(['psgc_code' => $barangay->psgc_code]);

        $legacyWorkspace = Barangay::where('psgc_code', 'DEFAULT')->firstOrFail();
        $this->postJson('/api/auth/register', [
            'last_name' => 'Legacy',
            'first_name' => 'User',
            'sex' => 'prefer_not_to_say',
            'barangay_id' => $legacyWorkspace->id,
            'email' => 'legacy-register@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('barangay_id');
    }

    public function test_admin_cannot_create_duplicate_document_types(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/document-types', [
            'name' => 'Barangay Clearance',
            'description' => 'Primary local document',
        ])->assertCreated();

        $this->postJson('/api/admin/document-types', [
            'name' => ' barangay   clearance ',
            'description' => 'Duplicate version',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_admin_can_update_and_delete_document_types(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/document-types', [
            'name' => 'Barangay Clearance',
            'description' => 'Initial description',
        ])->assertCreated();

        $this->putJson('/api/admin/document-types/1', [
            'name' => 'Barangay Clearance - Updated',
            'description' => 'Updated description',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Barangay Clearance - Updated')
            ->assertJsonPath('data.description', 'Updated description');

        $this->deleteJson('/api/admin/document-types/1')
            ->assertOk()
            ->assertJsonPath('message', 'Document type deleted.');
    }

    public function test_admin_can_login_and_fetch_users(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $verifiedStaff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);
        $verifiedResident = User::factory()->create([
            'role' => 'resident',
            'email_verified_at' => now(),
        ]);
        $verifiedResident->residentProfile()->create(['verification_status' => 'verified']);
        $unverifiedResident = User::factory()->create([
            'role' => 'resident',
            'email_verified_at' => now(),
        ]);
        $unverifiedResident->residentProfile()->create(['verification_status' => 'unverified']);

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
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['data' => [[ 'email' => $unverifiedResident->email ]]])
            ->assertJsonFragment(['email' => $verifiedResident->email])
            ->assertJsonFragment(['email' => $verifiedStaff->email]);
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
