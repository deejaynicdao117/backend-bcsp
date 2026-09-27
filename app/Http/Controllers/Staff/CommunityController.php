<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\DisasterAssistanceRequest;
use App\Models\Household;
use App\Models\User;
use App\Services\Staff\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function households(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->community->households($request->query('search'))]);
    }

    public function verifyHousehold(Request $request, Household $household): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'pending'])]]);

        return response()->json([
            'message' => 'Household verification updated.',
            'data' => $this->community->verifyHousehold($household, $request->user(), $data['status']),
        ]);
    }

    public function residents(): JsonResponse
    {
        return response()->json(['data' => $this->community->residentProfiles()]);
    }

    public function verifyResident(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role === 'resident', 404);
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'unverified'])]]);

        return response()->json([
            'message' => 'Resident verification updated.',
            'data' => $this->community->verifyResident($user, $request->user(), $data['status']),
        ]);
    }

    public function complaints(): JsonResponse
    {
        return response()->json(['data' => $this->community->complaints()]);
    }

    public function updateComplaint(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'under_review', 'in_progress', 'resolved', 'closed', 'rejected'])],
            'final_category' => ['sometimes', 'required', 'string', 'max:100'],
            'final_priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'staff_remarks' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        return response()->json([
            'message' => 'Complaint reviewed. AI recommendations and final staff decisions are retained separately.',
            'data' => $this->community->reviewComplaint($complaint, $request->user(), $data),
        ]);
    }

    public function assistanceRequests(): JsonResponse
    {
        return response()->json(['data' => $this->community->assistanceRequests()]);
    }

    public function updateAssistance(Request $request, DisasterAssistanceRequest $assistanceRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'acknowledged', 'dispatched', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'staff_remarks' => ['nullable', 'string', 'max:5000'],
            'evacuation_center_id' => ['nullable', 'exists:evacuation_centers,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        return response()->json([
            'message' => 'Disaster assistance request updated.',
            'data' => $this->community->updateAssistance($assistanceRequest, $request->user(), $data),
        ]);
    }

    public function analytics(): JsonResponse
    {
        return response()->json(['data' => $this->community->analytics()]);
    }
}
