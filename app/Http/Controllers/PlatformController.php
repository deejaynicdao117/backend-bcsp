<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\DocumentRequest;
use App\Models\SystemSetting;
use App\Models\User;
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
        $barangays = Barangay::query()->where('psgc_code', '!=', 'DEFAULT');
        $users = User::withoutGlobalScope('barangay');
        $requests = DocumentRequest::withoutGlobalScope('barangay');

        return response()->json(['data' => [
            'total_barangays' => (clone $barangays)->count(),
            'active_barangays' => (clone $barangays)
                ->where('is_active', true)
                ->where('onboarding_status', 'approved')
                ->count(),
            'pending_onboarding' => (clone $barangays)->where('onboarding_status', 'pending')->count(),
            'total_users' => (clone $users)->count(),
            'resident_count' => (clone $users)->where('role', 'resident')->count(),
            'total_requests' => (clone $requests)->count(),
            'pending_requests' => (clone $requests)->whereIn('status', ['pending', 'under_review'])->count(),
            'requests_this_month' => (clone $requests)->where('created_at', '>=', now()->startOfMonth())->count(),
        ]]);
    }

    public function reports(): JsonResponse
    {
        $requests = DocumentRequest::withoutGlobalScope('barangay');
        $users = User::withoutGlobalScope('barangay');
        $barangays = Barangay::query()->where('psgc_code', '!=', 'DEFAULT');
        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'ready_for_pickup', 'released'];
        $statusCounts = (clone $requests)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthlyRequests = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($requests): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);

                return [
                    'month' => $month->format('M'),
                    'total' => (clone $requests)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count(),
                ];
            })->all();

        $totalRequests = (clone $requests)->count();
        $closedRequests = (int) ($statusCounts['released'] ?? 0) + (int) ($statusCounts['rejected'] ?? 0);

        return response()->json(['data' => [
            'total_barangays' => (clone $barangays)->count(),
            'active_barangays' => (clone $barangays)
                ->where('is_active', true)
                ->where('onboarding_status', 'approved')
                ->count(),
            'pending_onboarding' => (clone $barangays)->where('onboarding_status', 'pending')->count(),
            'resident_registrations' => (clone $users)->where('role', 'resident')->count(),
            'resident_registrations_this_month' => (clone $users)
                ->where('role', 'resident')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            'total_requests' => $totalRequests,
            'closed_request_percent' => $totalRequests > 0 ? (int) round(($closedRequests / $totalRequests) * 100) : 0,
            'requests_by_status' => collect($statuses)
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'total' => (int) ($statusCounts[$status] ?? 0),
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
