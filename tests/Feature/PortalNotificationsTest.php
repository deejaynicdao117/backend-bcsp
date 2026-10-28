<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_document_request_notifies_staff_but_not_admin_or_resident(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'staff']);
        /** @var User $resident */
        $resident = User::factory()->create(['role' => 'resident']);
        $documentType = DocumentType::create(['name' => 'Barangay Clearance']);

        $this->actingAs($resident, 'sanctum')
            ->postJson('/api/resident/document-requests', [
                'document_type_id' => $documentType->id,
                'purpose' => 'Employment requirement',
            ])->assertCreated();

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $staff->id,
            'type' => 'document_request.created',
        ]);
        $this->assertDatabaseMissing('portal_notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('portal_notifications', ['user_id' => $resident->id]);
    }

    public function test_status_change_notifies_only_the_requesting_resident(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['role' => 'staff']);
        /** @var User $resident */
        $resident = User::factory()->create(['role' => 'resident']);
        $documentType = DocumentType::create(['name' => 'Barangay Clearance']);
        $request = DocumentRequest::create([
            'user_id' => $resident->id,
            'document_type_id' => $documentType->id,
            'tracking_number' => 'DOC-2026-000001',
            'purpose' => 'Employment requirement',
            'status' => 'pending',
        ]);

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/staff/document-requests/{$request->id}", [
                'status' => 'under_review',
                'remarks' => 'Review started',
            ])->assertOk();

        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $resident->id,
            'type' => 'document_request.status',
        ]);
        $this->assertDatabaseMissing('portal_notifications', ['user_id' => $staff->id]);
    }

    public function test_published_announcement_notifies_residents_and_alerts_are_private(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        /** @var User $resident */
        $resident = User::factory()->create(['role' => 'resident']);
        /** @var User $otherResident */
        $otherResident = User::factory()->create(['role' => 'resident']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/announcements', [
                'title' => 'Community meeting',
                'body' => 'The barangay meeting will be held this Friday.',
                'status' => 'published',
            ])->assertCreated();

        $this->assertSame(2, PortalNotification::where('type', 'announcement.published')->count());

        $this->actingAs($resident, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'New barangay announcement');

        $this->actingAs($otherResident, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}