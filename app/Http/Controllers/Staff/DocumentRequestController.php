<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\Staff\DocumentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'status' => ['required', 'in:pending,under_review,approved,rejected,ready_for_pickup,released'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
            'ready_for_pickup_at' => ['required_if:status,ready_for_pickup', 'nullable', 'date'],
            'released_at' => ['required_if:status,released', 'nullable', 'date'],
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

    public function downloadDocument(DocumentRequest $documentRequest): BinaryFileResponse
    {
        $path = $this->documentRequestService->generatedDocumentAbsolutePath($documentRequest);

        return response()->download(
            $path,
            $documentRequest->tracking_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
