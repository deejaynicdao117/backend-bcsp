<?php

namespace App\Services\Residents;

use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\DisasterAssistanceRequest;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Models\Household;
use App\Models\ResidentProfile;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityService
{
    public function __construct(private readonly ComplaintIntelligenceService $intelligence, private readonly ActivityLogService $activityLog) {}

    public function profile(User $resident): array
    {
        return [
            'user' => $resident->only(['id', 'name', 'email', 'email_verified_at']),
            'profile' => $resident->residentProfile,
            'households' => $resident->household()->with('head:id,name')->get(),
        ];
    }

    public function updateProfile(User $resident, array $data): ResidentProfile
    {
        return DB::transaction(function () use ($resident, $data): ResidentProfile {
            $householdData = array_intersect_key($data, array_flip(['address', 'purok', 'household_code', 'relationship']));
            unset($data['household_code'], $data['relationship']);

            $profile = ResidentProfile::updateOrCreate(['user_id' => $resident->id], $data);

            if (count($householdData) > 0) {
                $household = $resident->household()->first();
                if (! $household) {
                    $household = Household::create([
                        'household_code' => $householdData['household_code'] ?? 'HH-'.Str::upper(Str::random(8)),
                        'head_user_id' => $resident->id,
                        'address' => $householdData['address'] ?? ($profile->address ?? 'Address pending'),
                        'purok' => $householdData['purok'] ?? ($profile->purok ?? 'Unassigned'),
                    ]);
                } else {
                    $household->update(array_intersect_key($householdData, array_flip(['address', 'purok'])));
                }
                $household->members()->syncWithoutDetaching([$resident->id => ['relationship' => $householdData['relationship'] ?? 'head']]);
                $household->update(['member_count' => $household->members()->count()]);
            }

            return $profile->fresh();
        });
    }

    public function complaints(User $resident): array
    {
        return $resident->complaints()->latest()->get()->all();
    }

    public function submitComplaint(User $resident, array $data): Complaint
    {
        $analysis = $this->intelligence->analyze($data['subject'], $data['description']);
        $profile = $resident->residentProfile;
        if (empty($data['location']) && empty($profile?->address)) $analysis['validation_flags'][] = 'location_missing';
        if (! $resident->hasVerifiedEmail()) $analysis['validation_flags'][] = 'email_not_verified';

        return DB::transaction(function () use ($resident, $data, $analysis, $profile): Complaint {
            $complaint = Complaint::create([
                'resident_id' => $resident->id,
                'reference_number' => 'CMP-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $analysis['category'],
                'priority' => $analysis['priority'],
                'location' => $data['location'] ?? $profile?->address,
                'purok' => $data['purok'] ?? $profile?->purok,
                'status' => 'submitted',
                'ai_recommended_category' => $analysis['category'],
                'ai_recommended_priority' => $analysis['priority'],
                'ai_summary' => $analysis['summary'],
                'ai_engine' => 'local_keyword_rules_v1',
                'validation_flags' => $analysis['validation_flags'],
                'possible_duplicate' => $analysis['possible_duplicate'],
            ]);

            $this->activityLog->record($resident, 'complaint.submitted', $complaint, [
                'ai_category' => $analysis['category'],
                'ai_priority' => $analysis['priority'],
                'ai_validation_flags' => $analysis['validation_flags'],
            ]);

            return $complaint;
        });
    }

    public function assistanceRequests(User $resident): array
    {
        return $resident->disasterAssistanceRequests()->with('evacuationCenter')->latest()->get()->all();
    }

    public function submitAssistanceRequest(User $resident, array $data): DisasterAssistanceRequest
    {
        $request = DisasterAssistanceRequest::create([
            ...$data,
            'resident_id' => $resident->id,
            'reference_number' => 'DR-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'status' => 'submitted',
        ]);

        $this->activityLog->record($resident, 'disaster_assistance.submitted', $request);

        return $request;
    }

    public function announcements(): array
    {
        return Announcement::query()
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest('published_at')
            ->get()
            ->all();
    }

    public function emergencyContacts(): array
    {
        return EmergencyContact::where('is_active', true)->orderBy('office')->get()->all();
    }

    public function evacuationCenters(): array
    {
        return EvacuationCenter::whereIn('status', ['open', 'full'])->orderBy('name')->get()->all();
    }
}
