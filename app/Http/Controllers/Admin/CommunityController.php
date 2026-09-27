<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Services\Admin\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function households(): JsonResponse
    {
        return response()->json(['data' => $this->community->households()]);
    }

    public function announcements(): JsonResponse
    {
        return response()->json(['data' => $this->community->announcements()]);
    }

    public function saveAnnouncement(Request $request, ?Announcement $announcement = null): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:30000'],
            'type' => ['sometimes', Rule::in(['announcement', 'advisory', 'emergency', 'evacuation_protocol'])],
            'severity' => ['sometimes', Rule::in(['info', 'warning', 'urgent', 'critical'])],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return response()->json([
            'message' => 'Announcement saved.',
            'data' => $this->community->saveAnnouncement($request->user(), $data, $announcement),
        ], $announcement ? 200 : 201);
    }

    public function deleteAnnouncement(Request $request, Announcement $announcement): JsonResponse
    {
        $this->community->deleteAnnouncement($request->user(), $announcement);
        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function emergencyContacts(): JsonResponse
    {
        return response()->json(['data' => $this->community->emergencyContacts()]);
    }

    public function saveEmergencyContact(Request $request, ?EmergencyContact $contact = null): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'availability' => ['sometimes', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Emergency contact saved.',
            'data' => $this->community->saveEmergencyContact($request->user(), $data, $contact),
        ], $contact ? 200 : 201);
    }

    public function deleteEmergencyContact(Request $request, EmergencyContact $contact): JsonResponse
    {
        $this->community->deleteEmergencyContact($request->user(), $contact);
        return response()->json(['message' => 'Emergency contact deleted.']);
    }

    public function evacuationCenters(): JsonResponse
    {
        return response()->json(['data' => $this->community->evacuationCenters()]);
    }

    public function saveEvacuationCenter(Request $request, ?EvacuationCenter $center = null): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'current_occupancy' => ['sometimes', 'integer', 'min:0'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['open', 'full', 'closed'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'message' => 'Evacuation center saved.',
            'data' => $this->community->saveEvacuationCenter($request->user(), $data, $center),
        ], $center ? 200 : 201);
    }

    public function deleteEvacuationCenter(Request $request, EvacuationCenter $center): JsonResponse
    {
        $this->community->deleteEvacuationCenter($request->user(), $center);
        return response()->json(['message' => 'Evacuation center deleted.']);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => $this->community->settings()]);
    }

    public function saveSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'value' => ['present'],
            'group' => ['sometimes', 'string', 'max:80'],
        ]);

        return response()->json([
            'message' => 'System setting saved.',
            'data' => $this->community->saveSetting($request->user(), $data['key'], $data['value'], $data['group'] ?? 'general'),
        ]);
    }

    public function activityLogs(): JsonResponse
    {
        return response()->json(['data' => $this->community->activityLogs()]);
    }

    public function complaints(): JsonResponse
    {
        return response()->json(['data' => $this->community->complaints()]);
    }

    public function assistanceRequests(): JsonResponse
    {
        return response()->json(['data' => $this->community->assistanceRequests()]);
    }

    public function analytics(): JsonResponse
    {
        return response()->json(['data' => $this->community->analytics()]);
    }
}
