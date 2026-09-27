<?php

namespace App\Services\Staff;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DocumentRequestService
{
    public function index(): Collection
    {
        return DocumentRequest::with(['user', 'documentType'])
            ->latest()
            ->get();
    }

    public function updateStatus(User $staff, DocumentRequest $documentRequest, array $data): DocumentRequest
    {
        $documentRequest->update([
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? $documentRequest->remarks,
            'reviewed_by' => $staff->id,
        ]);

        return $documentRequest->fresh()->load(['user', 'documentType', 'reviewer']);
    }
}
