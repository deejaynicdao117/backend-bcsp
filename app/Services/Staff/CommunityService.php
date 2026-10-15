<?php

namespace App\Services\Staff;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\DisasterAssistanceRequest;
use App\Models\Household;
use App\Models\ResidentProfile;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;

class CommunityService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function households(?string $search = null): array
    {
        return Household::with(['head:id,name,email', 'members:id,name,email', 'verifier:id,name'])
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('household_code', 'like', "%{$search}%")
                ->orWhere('purok', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")))
            ->latest()->get()->all();
    }

    public function verifyHousehold(Household $household, User $staff, string $status): Household
    {
        $household->update([
            'verification_status' => $status,
            'verified_by' => $staff->id,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
        $this->activityLog->record($staff, 'household.verification_updated', $household, ['status' => $status]);

        return $household->fresh(['head:id,name,email', 'members:id,name,email', 'verifier:id,name']);
    }

    public function residentProfiles(): array
    {
        return ResidentProfile::with(['user:id,name,email', 'verifier:id,name'])
            ->latest()->get()->all();
    }

    public function verifyResident(User $resident, User $staff, string $status): ResidentProfile
    {
        $profile = ResidentProfile::firstOrCreate(['user_id' => $resident->id]);
        $profile->update([
            'verification_status' => $status,
            'verified_by' => $staff->id,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
        $this->activityLog->record($staff, 'resident.verification_updated', $profile, ['status' => $status]);

        return $profile->fresh(['user:id,name,email', 'verifier:id,name']);
    }

    public function complaints(): array
    {
        return Complaint::with(['resident:id,name,email', 'assignee:id,name', 'reviewer:id,name'])->latest()->get()->all();
    }

    public function reviewComplaint(Complaint $complaint, User $staff, array $data): Complaint
    {
        return DB::transaction(function () use ($complaint, $staff, $data): Complaint {
            $status = $data['status'];
            $complaint->update([
                'status' => $status,
                'final_category' => $data['final_category'] ?? $complaint->final_category ?? $complaint->ai_recommended_category,
                'final_priority' => $data['final_priority'] ?? $complaint->final_priority ?? $complaint->ai_recommended_priority,
                'staff_remarks' => $data['staff_remarks'] ?? $complaint->staff_remarks,
                'assigned_to' => $data['assigned_to'] ?? $complaint->assigned_to ?? $staff->id,
                'reviewed_by' => $staff->id,
                'first_response_at' => $complaint->first_response_at ?? now(),
                'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null,
            ]);

            $this->activityLog->record($staff, 'complaint.reviewed', $complaint, [
                'ai_category' => $complaint->ai_recommended_category,
                'ai_priority' => $complaint->ai_recommended_priority,
                'final_category' => $complaint->final_category,
                'final_priority' => $complaint->final_priority,
                'status' => $status,
            ]);

            return $complaint->fresh(['resident:id,name,email', 'assignee:id,name', 'reviewer:id,name']);
        });
    }

    public function assistanceRequests(): array
    {
        return DisasterAssistanceRequest::with(['resident:id,name,email', 'assignee:id,name', 'evacuationCenter:id,name'])
            ->latest()->get()->all();
    }

    public function updateAssistance(DisasterAssistanceRequest $assistanceRequest, User $staff, array $data): DisasterAssistanceRequest
    {
        $assistanceRequest->update([
            ...$data,
            'assigned_to' => $data['assigned_to'] ?? $staff->id,
            'responded_at' => $assistanceRequest->responded_at ?? now(),
            'resolved_at' => in_array($data['status'] ?? $assistanceRequest->status, ['resolved', 'closed'], true) ? now() : null,
        ]);
        $this->activityLog->record($staff, 'disaster_assistance.updated', $assistanceRequest, $data);

        return $assistanceRequest->fresh(['resident:id,name,email', 'assignee:id,name', 'evacuationCenter:id,name']);
    }

    public function analytics(): array
    {
        $responded = Complaint::whereNotNull('first_response_at')->get(['created_at', 'first_response_at']);
        $resolved = Complaint::whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);

        return [
            'complaints_by_category' => Complaint::query()->selectRaw('COALESCE(final_category, ai_recommended_category, category) as category, COUNT(*) as total')
                ->groupByRaw('COALESCE(final_category, ai_recommended_category, category)')->orderByDesc('total')->get()->all(),
            'complaints_by_purok' => Complaint::query()->selectRaw("COALESCE(purok, 'Unspecified') as location, COUNT(*) as total")
                ->groupByRaw("COALESCE(purok, 'Unspecified')")->orderByDesc('total')->get()->all(),
            'complaints_by_date' => Complaint::query()->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(30))->groupByRaw('DATE(created_at)')->orderBy('date')->get()->all(),
            'average_response_minutes' => $responded->isEmpty() ? 0 : (int) round($responded->avg(fn (Complaint $complaint): int => $complaint->created_at->diffInMinutes($complaint->first_response_at))),
            'average_resolution_hours' => $resolved->isEmpty() ? 0 : (int) round($resolved->avg(fn (Complaint $complaint): int => $complaint->created_at->diffInHours($complaint->resolved_at))),
            'complaint_total' => Complaint::count(),
            'assistance_total' => DisasterAssistanceRequest::count(),
            'activity_count' => ActivityLog::count(),
        ];
    }
}
