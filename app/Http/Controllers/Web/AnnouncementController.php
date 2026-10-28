<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Admin\CommunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function index(): View
    {
        return view('announcements.index', [
            'announcements' => $this->community->announcements(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->community->saveAnnouncement($request->user(), $this->validatedData($request));

        return redirect()->route('announcements.index')->with('status', 'Announcement saved.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->community->saveAnnouncement($request->user(), $this->validatedData($request), $announcement);

        return redirect()->route('announcements.index')->with('status', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->community->deleteAnnouncement($request->user(), $announcement);

        return redirect()->route('announcements.index')->with('status', 'Announcement deleted.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:30000'],
            'type' => ['required', Rule::in(['announcement', 'advisory', 'emergency', 'evacuation_protocol'])],
            'severity' => ['required', Rule::in(['info', 'warning', 'urgent', 'critical'])],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
