<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Services\Residents\DocumentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentRequestController extends Controller
{
    public function __construct(private readonly DocumentRequestService $documentRequestService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->documentRequestService->indexFor($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id' => ['required', 'exists:document_types,id'],
            'purpose' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json([
            'message' => 'Document request submitted successfully.',
            'data' => $this->documentRequestService->create($request->user(), $validated),
        ], 201);
    }

    public function previewDocument(Request $request, DocumentRequest $documentRequest): BinaryFileResponse
    {
        abort_unless($documentRequest->user_id === $request->user()->id, 404);
        abort_unless(in_array($documentRequest->status, ['approved', 'ready_for_pickup', 'released'], true), 404);

        $path = $documentRequest->generated_document_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404, 'The document preview is not available.');

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$documentRequest->tracking_number.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
