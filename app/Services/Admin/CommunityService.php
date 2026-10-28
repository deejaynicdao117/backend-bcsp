<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\DisasterAssistanceRequest;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\PortalNotificationService;

class CommunityService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly PortalNotificationService $notifications,
    ) {}

    public function households(): array
    {
        return Household::with([
            'head:id,name,email',
            'members:id,name,email',
            'verifier:id,name',
        ])
            ->latest()
            ->get()
            ->all();
    }

    public function announcements(): array
    {
        return Announcement::with('author:id,name')->latest()->get()->all();
    }

    public function saveAnnouncement(User $admin, array $data, ?Announcement $announcement = null): Announcement
    {
        $wasPublished = $announcement?->status === 'published';
        $data['author_id'] = $admin->id;
        if (
            ($data['status'] ?? $announcement?->status) === 'published'
            && ! ($announcement?->published_at)
        ) {
            $data['published_at'] = now();
        }
        if ($announcement) {
            $announcement->update($data);
        } else {
            $announcement = Announcement::create($data);
        }
        $this->activityLog->record($admin, 'announcement.saved', $announcement, ['status' => $announcement->status]);

        if (
            ! $wasPublished
            && $announcement->status === 'published'
        ) {
            $this->notifications->sendToRoles(
                ['resident', 'staff'],
                'announcement.published',
                'New barangay announcement',
                $announcement->title.': '.$announcement->body,
                ['announcement_id' => $announcement->id],
                $admin->barangay_id,
            );
        }

        return $announcement->fresh(['author:id,name']);
    }

    public function deleteAnnouncement(User $admin, Announcement $announcement): void
    {
        $announcement->delete();
        $this->activityLog->record($admin, 'announcement.deleted', $announcement);
    }

    public function emergencyContacts(): array
    {
        return EmergencyContact::latest()->get()->all();
    }

    public function saveEmergencyContact(User $admin, array $data, ?EmergencyContact $contact = null): EmergencyContact
    {
        if ($contact) {
            $contact->update($data);
        } else {
            $contact = EmergencyContact::create($data);
        }
        $this->activityLog->record($admin, 'emergency_contact.saved', $contact);

        return $contact;
    }

    public function deleteEmergencyContact(User $admin, EmergencyContact $contact): void
    {
        $contact->delete();
        $this->activityLog->record($admin, 'emergency_contact.deleted', $contact);
    }

    public function evacuationCenters(): array
    {
        return EvacuationCenter::latest()
            ->get()
            ->all();
    }

    public function saveEvacuationCenter(User $admin, array $data, ?EvacuationCenter $center = null): EvacuationCenter
    {
        if ($center) {
            $center->update($data);
        } else {
            $center = EvacuationCenter::create($data);
        }
        $this->activityLog->record($admin, 'evacuation_center.saved', $center);

        return $center;
    }

    public function deleteEvacuationCenter(User $admin, EvacuationCenter $center): void
    {
        $center->delete();
        $this->activityLog->record($admin, 'evacuation_center.deleted', $center);
    }

    public function activityLogs(): array
    {
        return ActivityLog::with('actor:id,name,email')->latest()->limit(200)->get()->all();
    }

    public function complaints(): array
    {
        return Complaint::with(['resident:id,name,email', 'assignee:id,name', 'reviewer:id,name'])->latest()->get()->all();
    }

    public function assistanceRequests(): array
    {
        return DisasterAssistanceRequest::with([
            'resident:id,name,email',
            'assignee:id,name',
            'evacuationCenter:id,name',
        ])
            ->latest()
            ->get()
            ->all();
    }

    public function analytics(): array
    {
        $responded = Complaint::whereNotNull('first_response_at')
            ->get(['created_at', 'first_response_at']);
        $resolved = Complaint::whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        return [
            'complaints_by_category' => Complaint::query()
                ->selectRaw(
                    'COALESCE(final_category, ai_recommended_category, category) as category, COUNT(*) as total',
                )
                ->groupByRaw('COALESCE(final_category, ai_recommended_category, category)')
                ->orderByDesc('total')
                ->get()
                ->all(),
            'complaints_by_purok' => Complaint::query()
                ->selectRaw("COALESCE(purok, 'Unspecified') as location, COUNT(*) as total")
                ->groupByRaw("COALESCE(purok, 'Unspecified')")
                ->orderByDesc('total')
                ->get()
                ->all(),
            'complaints_by_date' => Complaint::query()
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get()
                ->all(),
            'average_response_minutes' => $responded->isEmpty()
                ? 0
                : (int) round($responded->avg(
                    fn (Complaint $complaint): int => $complaint->created_at
                        ->diffInMinutes($complaint->first_response_at),
                )),
            'average_resolution_hours' => $resolved->isEmpty()
                ? 0
                : (int) round($resolved->avg(
                    fn (Complaint $complaint): int => $complaint->created_at
                        ->diffInHours($complaint->resolved_at),
                )),
            'complaint_total' => Complaint::count(),
            'assistance_total' => DisasterAssistanceRequest::count(),
        ];
    }
}
