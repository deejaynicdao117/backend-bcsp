<?php

namespace App\Http\Controllers;

use App\Services\DocumentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly DocumentTypeService $documentTypeService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->documentTypeService->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $documentType = $this->documentTypeService->create($validated);

        return response()->json($documentType, 201);
    }
}
