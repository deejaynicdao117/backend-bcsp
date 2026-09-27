<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Services\Residents\DocumentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
