<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\PortalNotification;
use App\Models\User;
use App\Services\PortalNotificationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CommunityPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_can_save_profile_and_submit_complaint_and_emergency_request(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($resident, 'sanctum');

        $this->putJson('/api/resident/profile', [
            'phone' => '09170000000',
            'address' => '12 Community Road',
            'purok' => 'Purok 2',
            'relationship' => 'head',
        ])->assertOk();

        $this->assertDatabaseHas('households', ['head_user_id' => $resident->id, 'purok' => 'Purok 2']);

        $complaint = $this->postJson('/api/resident/complaints', [
            'subject' => 'Smoke and fire near the market',
            'description' => 'There is smoke and fire coming from a stall beside the public market. Please send emergency responders immediately.',
        ])->assertCreated()
            ->assertJsonPath('data.ai_recommended_category', 'fire_emergency')
            ->assertJsonPath('data.ai_recommended_priority', 'high')
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson('/api/resident/disaster-assistance', [
            'incident_type' => 'Flood',
            'description' => 'Flood water is entering our home and we need evacuation assistance.',
            'location' => '12 Community Road',
            'purok' => 'Purok 2',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $this->getJson('/api/resident/complaints')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/resident/disaster-assistance')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/resident/profile')->assertOk()->assertJsonPath('data.profile.purok', 'Purok 2');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->json('data.id'),
            'ai_recommended_category' => 'fire_emergency',
            'ai_recommended_priority' => 'high',
        ]);
    }

    public function test_resident_profile_name_updates_the_name_shown_in_staff_verification(): void
    {
        $resident = User::factory()->create([
            'name' => 'Old Account Name',
            'role' => 'resident',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($resident, 'sanctum')
            ->putJson('/api/resident/profile', [
                'first_name' => 'Alex',
                'middle_name' => 'M.',
                'last_name' => 'Rivera',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $resident->id,
            'name' => 'Alex M. Rivera',
        ]);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/residents')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Alex M. Rivera');
    }

    public function test_staff_verification_complaint_review_and_admin_community_management(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $resident->residentProfile()->create(['purok' => 'Purok 1', 'address' => '1 Test Road']);
        $household = Household::create([
            'household_code' => 'HH-TEST-01',
            'head_user_id' => $resident->id,
            'address' => '1 Test Road',
            'purok' => 'Purok 1',
        ]);
        $complaintResponse = $this->actingAs($resident, 'sanctum')->postJson('/api/resident/complaints', [
            'subject' => 'Garbage has not been collected',
            'description' => 'Garbage has not been collected on our street for several days and there is a bad smell.',
            'purok' => 'Purok 1',
        ])->assertCreated();
        $complaintId = $complaintResponse->json('data.id');

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/residents')->assertOk();
        $this->putJson("/api/staff/residents/{$resident->id}/verification", ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.verification_status', 'verified');
        $this->getJson('/api/staff/households')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/staff/households/{$household->id}/verification", ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.verification_status', 'verified');
        $this->putJson("/api/staff/complaints/{$complaintId}", [
            'status' => 'resolved',
            'final_category' => 'sanitation',
            'final_priority' => 'medium',
            'staff_remarks' => 'Sanitation team completed cleanup.',
        ])->assertOk()
            ->assertJsonPath('data.ai_recommended_category', 'sanitation')
            ->assertJsonPath('data.final_category', 'sanitation')
            ->assertJsonPath('data.status', 'resolved');
        $this->getJson('/api/staff/analytics/community')->assertOk()
            ->assertJsonStructure(['data' => ['complaints_by_category', 'complaints_by_purok', 'average_response_minutes']]);

        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/admin/announcements', [
            'title' => 'Storm advisory',
            'body' => 'Monitor official updates and prepare emergency supplies.',
            'type' => 'advisory',
            'severity' => 'warning',
            'status' => 'published',
        ])->assertCreated()->assertJsonPath('data.status', 'published');
        $this->postJson('/api/admin/emergency-contacts', [
            'name' => 'Rescue Line',
            'phone' => '911',
            'availability' => '24/7',
        ])->assertCreated();
        $this->postJson('/api/admin/evacuation-centers', [
            'name' => 'School Gym',
            'address' => 'Purok 1',
            'capacity' => 100,
            'status' => 'open',
        ])->assertCreated();
        $this->putJson('/api/admin/settings', ['key' => 'portal.banner', 'value' => 'Ready', 'group' => 'portal'])->assertOk();
        $this->getJson('/api/admin/activity-logs')->assertOk();

        $this->actingAs($resident, 'sanctum');
        $this->getJson('/api/community/announcements')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/community/emergency-contacts')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/community/evacuation-centers')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_staff_resident_list_excludes_profiles_belonging_to_non_residents(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $staff = User::factory()->create(['role' => 'staff']);
        $resident->residentProfile()->create();
        $staff->residentProfile()->create();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/staff/residents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $resident->id);
    }

    public function test_staff_can_manage_announcements_and_publish_notifies_residents_by_email_and_in_portal(): void
    {
        $resident = User::factory()->create(['role' => 'resident']);
        $staff = User::factory()->create(['role' => 'staff']);
        $adminStaff = User::factory()->create(['role' => 'staff']);

        $this->mock(PortalNotificationMailer::class)
            ->shouldReceive('send')
            ->times(3)
            ->withArgs(fn (User $recipient, string $subject): bool => in_array($recipient->id, [$resident->id, $staff->id, $adminStaff->id], true)
                && $subject === 'New barangay announcement');

        $this->actingAs($staff, 'sanctum');

        $this->getJson('/api/staff/announcements')->assertOk()->assertJsonCount(0, 'data');

        $announcement = $this->postJson('/api/staff/announcements', [
            'title' => 'Community clean-up',
            'body' => 'Join us for the community clean-up this Saturday.',
            'status' => 'draft',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $announcementId = $announcement->json('data.id');

        $this->putJson("/api/staff/announcements/{$announcementId}", [
            'title' => 'Community clean-up this Saturday',
            'body' => 'Meet at the barangay hall at 8 AM this Saturday.',
            'status' => 'published',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $resident->id,
            'type' => 'announcement.published',
        ]);

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $resident->id,
            'title' => 'New barangay announcement',
            'message' => 'Community clean-up this Saturday: Meet at the barangay hall at 8 AM this Saturday.',
        ]);

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $adminStaff->id,
            'title' => 'New barangay announcement',
            'message' => 'Community clean-up this Saturday: Meet at the barangay hall at 8 AM this Saturday.',
        ]);

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $staff->id,
            'title' => 'New barangay announcement',
            'message' => 'Community clean-up this Saturday: Meet at the barangay hall at 8 AM this Saturday.',
        ]);

        $this->getJson('/api/staff/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Community clean-up this Saturday');

        $this->deleteJson("/api/staff/announcements/{$announcementId}")
            ->assertOk()
            ->assertJsonPath('message', 'Announcement deleted.');
    }

    public function test_admin_and_staff_can_access_the_announcement_maker_but_residents_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $resident = User::factory()->create(['role' => 'resident']);

        $this->actingAs($admin)->get('/announcements')->assertOk()->assertSee('Create announcement');
        $this->actingAs($staff)->get('/announcements')->assertOk()->assertSee('Create announcement');
        $this->actingAs($resident)->get('/announcements')->assertForbidden();
    }

    public function test_signed_email_verification_link_verifies_resident(): void
    {
        $resident = User::factory()->unverified()->create(['role' => 'resident']);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $resident->id,
            'hash' => sha1($resident->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk()->assertJsonPath('user.id', $resident->id);
        $this->assertNotNull($resident->fresh()->email_verified_at);
    }

    public function test_complaint_uses_configured_ai_provider_and_retains_local_validation_flags(): void
    {
        config([
            'services.complaint_ai.enabled' => true,
            'services.complaint_ai.api_key' => 'test-key',
            'services.complaint_ai.base_url' => 'https://ai.example.test/v1',
            'services.complaint_ai.model' => 'test-model',
        ]);
        Http::fake([
            'ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'category' => 'sanitation',
                        'priority' => 'medium',
                        'summary' => 'Residents report missed garbage collection.',
                    ])],
                ]],
            ]),
        ]);

        $resident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($resident, 'sanctum')
            ->postJson('/api/resident/complaints', [
                'subject' => 'Garbage pickup missed',
                'description' => 'The garbage truck missed our street this week; trash is accumulating near the homes.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.ai_recommended_category', 'sanitation')
            ->assertJsonPath('data.ai_recommended_priority', 'medium')
            ->assertJsonPath('data.ai_summary', 'Residents report missed garbage collection.')
            ->assertJsonPath('data.ai_engine', 'openai_compatible:test-model');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://ai.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test-model');
    }

    public function test_complaint_ai_provider_failure_falls_back_to_local_rules(): void
    {
        config([
            'services.complaint_ai.enabled' => true,
            'services.complaint_ai.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);

        $resident = User::factory()->create(['role' => 'resident']);
        $this->actingAs($resident, 'sanctum')
            ->postJson('/api/resident/complaints', [
                'subject' => 'Smoke near the market',
                'description' => 'There is smoke near the market and residents are concerned about a possible fire.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.ai_recommended_category', 'fire_emergency')
            ->assertJsonPath('data.ai_engine', 'local_keyword_rules_v1');
    }
}
