<?php

namespace App\Services\Admin;

use App\Models\DocumentRequest;
use App\Models\User;

class AdminService
{
    public function users(): array
    {
        return User::query()
            ->select('id', 'name', 'email', 'role')
            ->get()
            ->all();
    }

    public function dashboard(): array
    {
        return [
            'total_users' => User::count(),
            'admin_count' => User::where('role', 'admin')->count(),
            'staff_count' => User::where('role', 'staff')->count(),
            'resident_count' => User::where('role', 'resident')->count(),
            'total_requests' => DocumentRequest::count(),
            'pending_requests' => DocumentRequest::where('status', 'pending')->count(),
            'requests_this_month' => DocumentRequest::where('created_at', '>=', now()->startOfMonth())->count(),
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
        $statusCounts = DocumentRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'ready_for_release', 'completed'];
        $requestsByStatus = collect($statuses)
            ->map(fn (string $status): array => [
                'status' => $status,
                'total' => (int) ($statusCounts[$status] ?? 0),
            ])->all();

        $monthlyRequests = collect(range(5, 0))
            ->map(function (int $monthsAgo): array {
                $month = now()->startOfMonth()->subMonths($monthsAgo);

                return [
                    'month' => $month->format('M'),
                    'total' => DocumentRequest::whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count(),
                ];
            })->all();

        return [
            'requests_by_status' => $requestsByStatus,
            'monthly_requests' => $monthlyRequests,
            'total_requests' => DocumentRequest::count(),
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
