<?php

namespace App\Services\Residents;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DocumentRequestService
{
    public function indexFor(User $resident): Collection
    {
        return $resident->documentRequests()
            ->with('documentType')
            ->latest()
            ->get();
    }

    public function create(User $resident, array $data): DocumentRequest
    {
        $documentType = DocumentType::findOrFail($data['document_type_id']);
        $nextNumber = DocumentRequest::count() + 1;

        return DocumentRequest::create([
            'user_id' => $resident->id,
            'document_type_id' => $documentType->id,
            'tracking_number' => 'DOC-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT),
            'purpose' => $data['purpose'],
            'status' => 'pending',
        ])->load('documentType');
    }
}
