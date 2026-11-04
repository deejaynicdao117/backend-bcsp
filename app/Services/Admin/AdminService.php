<?php

namespace App\Services\Admin;

use App\Models\DocumentRequest;
use App\Models\User;

class AdminService
{
    public function users(): array
    {
        return User::query()
            ->leftJoin('resident_profiles', 'resident_profiles.user_id', '=', 'users.id')
            ->where(function ($query): void {
                $query->where('users.role', '!=', 'resident')
                    ->orWhere('resident_profiles.verification_status', 'verified')
                    ->orWhere(function ($residentQuery): void {
                        $residentQuery->where('users.role', 'resident')
                            ->whereNull('resident_profiles.id')
                            ->whereNotNull('users.email_verified_at');
                    });
            })
            ->select('users.id', 'users.name', 'users.email', 'users.role')
            ->distinct()
            ->get()
            ->all();
    }

    public function dashboard(): array
    {
        $monthStart = now()->startOfMonth();
        $userStats = User::query()
            ->selectRaw('COUNT(*) AS total_users')
            ->selectRaw("SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS admin_count")
            ->selectRaw("SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) AS staff_count")
            ->selectRaw("SUM(CASE WHEN role = 'resident' THEN 1 ELSE 0 END) AS resident_count")
            ->toBase()
            ->first();
        $requestStats = DocumentRequest::query()
            ->selectRaw('COUNT(*) AS total_requests')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_requests")
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS requests_this_month', [$monthStart])
            ->toBase()
            ->first();

        return [
            'total_users' => (int) ($userStats->total_users ?? 0),
            'admin_count' => (int) ($userStats->admin_count ?? 0),
            'staff_count' => (int) ($userStats->staff_count ?? 0),
            'resident_count' => (int) ($userStats->resident_count ?? 0),
            'total_requests' => (int) ($requestStats->total_requests ?? 0),
            'pending_requests' => (int) ($requestStats->pending_requests ?? 0),
            'requests_this_month' => (int) ($requestStats->requests_this_month ?? 0),
            'recent_requests' => DocumentRequest::with([
                'user:id,name',
                'documentType:id,name',
            ])->latest()->limit(6)->get(),
        ];
    }

    public function documentRequests(): array
    {
        return DocumentRequest::with([
            'user:id,name,email',
            'documentType:id,name',
            'reviewer:id,name',
        ])->latest()->get()->all();
    }

    public function reports(): array
    {
        $monthStart = now()->startOfMonth();
        $months = collect(range(5, 0))->map(fn (int $monthsAgo) => $monthStart->copy()->subMonths($monthsAgo));
        $monthlyExpressions = [];
        $monthlyBindings = [];
        foreach ($months as $index => $month) {
            $monthlyExpressions[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS month_{$index}";
            $monthlyBindings[] = $month;
            $monthlyBindings[] = $month->copy()->addMonth();
        }
        $requestRows = DocumentRequest::query()
            ->selectRaw('status, COUNT(*) AS total, '.implode(', ', $monthlyExpressions), $monthlyBindings)
            ->groupBy('status')
            ->toBase()
            ->get();
        $statusCounts = $requestRows->keyBy('status');

        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'ready_for_pickup', 'released'];
        $statuses = collect($statuses)
            ->merge($requestRows->pluck('status'))
            ->unique()
            ->values();

        $requestsByStatus = $statuses
            ->map(fn (string $status): array => [
                'status' => $status,
                'total' => (int) ($statusCounts->get($status)->total ?? 0),
            ])->all();

        $monthlyRequests = $months->values()->map(fn ($month, int $index): array => [
            'month' => $month->format('M'),
            'total' => (int) $requestRows->sum(fn (object $row): int => (int) $row->{'month_'.$index}),
        ])->all();

        return [
            'requests_by_status' => $requestsByStatus,
            'monthly_requests' => $monthlyRequests,
            'total_requests' => (int) $requestRows->sum(fn (object $row): int => (int) $row->total),
        ];
    }

    public function notifications(): array
    {
        return DocumentRequest::with(['user:id,name', 'documentType:id,name'])
            ->whereIn('status', ['pending', 'under_review'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'title' => $request->status === 'pending' ? 'New document request' : 'Request under review',
                'message' => ($request->user?->name ?? 'A resident')
                    .' requested '.($request->documentType?->name ?? 'a document').'.',
                'status' => $request->status,
                'created_at' => $request->created_at?->toIso8601String(),
            ])->all();
    }
}
