<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformController extends Controller
{
    private const SETTINGS_DEFAULTS = [
        'portal_name' => 'Barangay Citizen Services Portal',
        'support_email' => 'support@barangayportal.gov',
        'primary_color' => '#2563eb',
        'welcome_message' => 'Your local services, organized and accessible to every resident.',
        'allow_new_registrations' => true,
        'emergency_notices' => true,
        'public_announcements' => false,
        'email_verification' => true,
    ];

    public function dashboard(): JsonResponse
    {
        $province = 'Bulacan';
        $monthStart = now()->startOfMonth();
        $barangays = DB::table('barangays')
            ->where('province', $province)
            ->where('psgc_code', '!=', 'DEFAULT')
            ->selectRaw('COUNT(*) AS total_barangays')
            ->selectRaw("SUM(CASE WHEN is_active = 1 AND onboarding_status = 'approved' THEN 1 ELSE 0 END) AS active_barangays")
            ->selectRaw("SUM(CASE WHEN onboarding_status = 'pending' THEN 1 ELSE 0 END) AS pending_onboarding")
            ->first();
        $users = DB::table('users')
            ->join('barangays', 'barangays.id', '=', 'users.barangay_id')
            ->where('barangays.province', $province)
            ->selectRaw('COUNT(*) AS total_users')
            ->selectRaw("SUM(CASE WHEN users.role = 'resident' THEN 1 ELSE 0 END) AS resident_count")
            ->first();
        $requests = DB::table('document_requests')
            ->join('barangays', 'barangays.id', '=', 'document_requests.barangay_id')
            ->where('barangays.province', $province)
            ->selectRaw('COUNT(*) AS total_requests')
            ->selectRaw("SUM(CASE WHEN document_requests.status IN ('pending', 'under_review') THEN 1 ELSE 0 END) AS pending_requests")
            ->selectRaw('SUM(CASE WHEN document_requests.created_at >= ? THEN 1 ELSE 0 END) AS requests_this_month', [$monthStart])
            ->first();

        return response()->json(['data' => [
            'total_barangays' => (int) ($barangays->total_barangays ?? 0),
            'active_barangays' => (int) ($barangays->active_barangays ?? 0),
            'pending_onboarding' => (int) ($barangays->pending_onboarding ?? 0),
            'total_users' => (int) ($users->total_users ?? 0),
            'resident_count' => (int) ($users->resident_count ?? 0),
            'total_requests' => (int) ($requests->total_requests ?? 0),
            'pending_requests' => (int) ($requests->pending_requests ?? 0),
            'requests_this_month' => (int) ($requests->requests_this_month ?? 0),
        ]]);
    }

    public function reports(): JsonResponse
    {
        $province = 'Bulacan';
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => $monthStart->copy()->subMonths($monthsAgo));
        $barangays = DB::table('barangays')
            ->where('province', $province)
            ->where('psgc_code', '!=', 'DEFAULT')
            ->selectRaw('COUNT(*) AS total_barangays')
            ->selectRaw("SUM(CASE WHEN is_active = 1 AND onboarding_status = 'approved' THEN 1 ELSE 0 END) AS active_barangays")
            ->selectRaw("SUM(CASE WHEN onboarding_status = 'pending' THEN 1 ELSE 0 END) AS pending_onboarding")
            ->first();
        $users = DB::table('users')
            ->join('barangays', 'barangays.id', '=', 'users.barangay_id')
            ->where('barangays.province', $province)
            ->where('users.role', 'resident')
            ->selectRaw('COUNT(*) AS resident_registrations')
            ->selectRaw('SUM(CASE WHEN users.created_at >= ? THEN 1 ELSE 0 END) AS resident_registrations_this_month', [$monthStart])
            ->first();
        $monthlyExpressions = [];
        $monthlyBindings = [];
        foreach ($months as $index => $month) {
            $monthlyExpressions[] = "SUM(CASE WHEN document_requests.created_at >= ? AND document_requests.created_at < ? THEN 1 ELSE 0 END) AS month_{$index}";
            $monthlyBindings[] = $month;
            $monthlyBindings[] = $month->copy()->addMonth();
        }
        $requestStatuses = DB::table('document_requests')
            ->join('barangays', 'barangays.id', '=', 'document_requests.barangay_id')
            ->where('barangays.province', $province)
            ->selectRaw('document_requests.status, COUNT(*) AS total, '.implode(', ', $monthlyExpressions), $monthlyBindings)
            ->groupBy('document_requests.status')
            ->get();
        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'ready_for_pickup', 'released'];
        $statuses = collect($statuses)
            ->merge($requestStatuses->pluck('status'))
            ->unique()
            ->values();
        $statusCounts = $requestStatuses->keyBy('status');
        $totalRequests = (int) $requestStatuses->sum(fn (object $row): int => (int) $row->total);
        $closedRequests = (int) ($statusCounts->get('released')->total ?? 0) + (int) ($statusCounts->get('rejected')->total ?? 0);
        $monthlyRequests = $months->values()->map(fn ($month, int $index): array => [
            'month' => $month->format('M'),
            'total' => (int) $requestStatuses->sum(fn (object $row): int => (int) $row->{'month_'.$index}),
        ])->all();

        return response()->json(['data' => [
            'total_barangays' => (int) ($barangays->total_barangays ?? 0),
            'active_barangays' => (int) ($barangays->active_barangays ?? 0),
            'pending_onboarding' => (int) ($barangays->pending_onboarding ?? 0),
            'resident_registrations' => (int) ($users->resident_registrations ?? 0),
            'resident_registrations_this_month' => (int) ($users->resident_registrations_this_month ?? 0),
            'total_requests' => $totalRequests,
            'closed_request_percent' => $totalRequests > 0 ? (int) round(($closedRequests / $totalRequests) * 100) : 0,
            'requests_by_status' => $statuses
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'total' => (int) ($statusCounts->get($status)->total ?? 0),
                ])->all(),
            'monthly_requests' => $monthlyRequests,
        ]]);
    }

    public function settings(): JsonResponse
    {
        $values = $this->storedSettings();

        return response()->json(['data' => $values]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'portal_name' => ['required', 'string', 'max:120'],
            'support_email' => ['required', 'email', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'welcome_message' => ['required', 'string', 'max:1000'],
            'allow_new_registrations' => ['required', 'boolean'],
            'emergency_notices' => ['required', 'boolean'],
            'public_announcements' => ['required', 'boolean'],
            'email_verification' => ['required', 'boolean'],
        ]);

        $barangayId = $this->defaultWorkspaceId();
        $timestamp = now();

        DB::transaction(function () use ($validated, $barangayId, $request, $timestamp): void {
            foreach ($validated as $key => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['barangay_id' => $barangayId, 'key' => $key],
                    [
                        'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                        'group' => 'platform',
                        'updated_by' => $request->user()->id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            }
        });

        return response()->json([
            'message' => 'Platform settings saved successfully.',
            'data' => $this->storedSettings(),
        ]);
    }

    private function storedSettings(): array
    {
        $barangayId = $this->defaultWorkspaceId();
        $stored = SystemSetting::withoutGlobalScope('barangay')
            ->where('barangay_id', $barangayId)
            ->whereIn('key', array_keys(self::SETTINGS_DEFAULTS))
            ->pluck('value', 'key');

        $settings = self::SETTINGS_DEFAULTS;
        foreach ($settings as $key => $default) {
            if (! $stored->has($key)) {
                continue;
            }

            $settings[$key] = is_bool($default)
                ? filter_var($stored[$key], FILTER_VALIDATE_BOOLEAN)
                : (string) $stored[$key];
        }

        return $settings;
    }

    private function defaultWorkspaceId(): int
    {
        $id = Barangay::query()->where('psgc_code', 'DEFAULT')->value('id');
        abort_unless($id, 500, 'The platform settings workspace is not configured.');

        return (int) $id;
    }
}
