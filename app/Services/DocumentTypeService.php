<?php

namespace App\Services;

use App\Models\DocumentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocumentTypeService
{
    public function all(): Collection
    {
        return DocumentType::all();
    }

    public function create(array $data): DocumentType
    {
        $name = preg_replace('/\s+/', ' ', trim((string) ($data['name'] ?? '')));

        if ($name === '') {
            throw new \InvalidArgumentException('Document type name is required.');
        }

        $normalized = DocumentType::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)])
            ->first();

        if ($normalized) {
            throw new \InvalidArgumentException('The document type already exists.');
        }

        return DocumentType::create([
            ...$data,
            'name' => $name,
        ]);
    }

    public function update(DocumentType $documentType, array $data): DocumentType
    {
        $name = preg_replace('/\s+/', ' ', trim((string) ($data['name'] ?? '')));

        if ($name === '') {
            throw new \InvalidArgumentException('Document type name is required.');
        }

        $existing = DocumentType::query()
            ->where('id', '!=', $documentType->id)
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('The document type already exists.');
        }

        $documentType->update([
            ...$data,
            'name' => $name,
        ]);

        return $documentType->fresh();
    }

    public function delete(DocumentType $documentType): void
    {
        $documentType->delete();
    }
}
