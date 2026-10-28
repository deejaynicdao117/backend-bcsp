<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Admin\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->community->announcements()]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->save($request, $announcement);
    }

    private function save(Request $request, ?Announcement $announcement = null): JsonResponse
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

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $this->community->deleteAnnouncement($request->user(), $announcement);

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
