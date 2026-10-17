<?php

namespace App\Services\Residents;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Collection;

class DocumentRequestService
{
    public function __construct(private readonly PortalNotificationService $notifications) {}

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

        $request = DocumentRequest::create([
            'user_id' => $resident->id,
            'document_type_id' => $documentType->id,
            'tracking_number' => 'DOC-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT),
            'purpose' => $data['purpose'],
            'status' => 'pending',
        ])->load('documentType');

        $this->notifications->sendToRoles(
            ['staff'],
            'document_request.created',
            'New document request',
            "{$resident->name} submitted {$documentType->name} ({$request->tracking_number}).",
            ['tracking_number' => $request->tracking_number, 'request_id' => $request->id],
        );

        return $request;
    }
}
