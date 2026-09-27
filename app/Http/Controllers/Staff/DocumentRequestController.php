<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\Staff\DocumentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function __construct(private readonly DocumentRequestService $documentRequestService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->documentRequestService->index(),
        ]);
    }

    public function updateStatus(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,under_review,approved,rejected,ready_for_release,completed'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'message' => 'Document request updated.',
            'data' => $this->documentRequestService->updateStatus(
                $request->user(),
                $documentRequest,
                $validated,
            ),
        ]);
    }
}
