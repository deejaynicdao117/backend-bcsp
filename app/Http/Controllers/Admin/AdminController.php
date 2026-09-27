<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminService;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function __construct(private readonly AdminService $adminService) {}

    public function users(): JsonResponse
    {
        return response()->json(['data' => $this->adminService->users()]);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->adminService->dashboard()]);
    }

    public function documentRequests(): JsonResponse
    {
        return response()->json(['data' => $this->adminService->documentRequests()]);
    }

    public function reports(): JsonResponse
    {
        return response()->json(['data' => $this->adminService->reports()]);
    }

    public function notifications(): JsonResponse
    {
        return response()->json(['data' => $this->adminService->notifications()]);
    }
}
