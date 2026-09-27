<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Services\Residents\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->community->profile($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'sex' => ['sometimes', 'nullable', 'string', 'max:30'],
            'civil_status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purok' => ['sometimes', 'nullable', 'string', 'max:100'],
            'household_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'relationship' => ['sometimes', 'string', 'max:60'],
        ]);

        return response()->json(['message' => 'Resident profile saved.', 'data' => $this->community->updateProfile($request->user(), $data)]);
    }

    public function complaints(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->community->complaints($request->user())]);
    }

    public function submitComplaint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'message' => 'Complaint submitted for staff review.',
            'data' => $this->community->submitComplaint($request->user(), $data),
        ], 201);
    }

    public function assistanceRequests(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->community->assistanceRequests($request->user())]);
    }

    public function submitAssistanceRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'incident_type' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'location' => ['required', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'in:low,medium,high,critical'],
        ]);

        return response()->json([
            'message' => 'Emergency assistance request sent.',
            'data' => $this->community->submitAssistanceRequest($request->user(), $data),
        ], 201);
    }

    public function announcements(): JsonResponse
    {
        return response()->json(['data' => $this->community->announcements()]);
    }

    public function emergencyContacts(): JsonResponse
    {
        return response()->json(['data' => $this->community->emergencyContacts()]);
    }

    public function evacuationCenters(): JsonResponse
    {
        return response()->json(['data' => $this->community->evacuationCenters()]);
    }
}
