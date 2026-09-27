<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
