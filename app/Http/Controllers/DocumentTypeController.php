<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Services\DocumentTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentTypeController extends Controller
{
    public function __construct(private readonly DocumentTypeService $documentTypeService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->documentTypeService->all());
    }

    public function store(Request $request): JsonResponse
    {
        $normalizedName = preg_replace('/\s+/', ' ', trim((string) $request->input('name')));
        $request->merge(['name' => $normalizedName]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalizedValue = preg_replace('/\s+/', ' ', trim((string) $value));

                    if ($normalizedValue === '') {
                        return;
                    }

                    $exists = DocumentType::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($normalizedValue)])
                        ->exists();

                    if ($exists) {
                        $fail('The document type already exists.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'body_template' => ['nullable', 'string', 'max:20000'],
        ]);

        $documentType = $this->documentTypeService->create($validated);

        return response()->json($documentType, 201);
    }

    public function update(Request $request, DocumentType $documentType): JsonResponse
    {
        $normalizedName = preg_replace('/\s+/', ' ', trim((string) $request->input('name')));
        $request->merge(['name' => $normalizedName]);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($documentType): void {
                    $normalizedValue = preg_replace('/\s+/', ' ', trim((string) $value));

                    if ($normalizedValue === '') {
                        return;
                    }

                    $exists = DocumentType::query()
                        ->where('id', '!=', $documentType->id)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($normalizedValue)])
                        ->exists();

                    if ($exists) {
                        $fail('The document type already exists.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'body_template' => ['nullable', 'string', 'max:20000'],
        ]);

        $updated = $this->documentTypeService->update($documentType, $validated);

        return response()->json([
            'message' => 'Document type updated.',
            'data' => $updated,
        ]);
    }

    public function destroy(DocumentType $documentType): JsonResponse
    {
        $this->documentTypeService->delete($documentType);

        return response()->json([
            'message' => 'Document type deleted.',
        ]);
    }
}
