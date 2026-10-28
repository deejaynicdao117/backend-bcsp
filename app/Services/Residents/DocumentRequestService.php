<?php

namespace App\Services\Residents;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Support\CurrentBarangay;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Collection;

class DocumentRequestService
{
    public function __construct(
        private readonly PortalNotificationService $notifications,
        private readonly CurrentBarangay $currentBarangay,
    ) {}

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
        $barangayCode = $resident->barangay?->psgc_code ?? (string) $this->currentBarangay->id();
        $trackingNumber = sprintf(
            'DOC-%s-%s-%s',
            now()->format('Y'),
            preg_replace('/[^A-Za-z0-9]/', '', $barangayCode),
            (string) \Illuminate\Support\Str::ulid(),
        );

        $request = DocumentRequest::create([
            'user_id' => $resident->id,
            'document_type_id' => $documentType->id,
            'tracking_number' => $trackingNumber,
            'purpose' => $data['purpose'],
            'status' => 'pending',
        ])->load('documentType');

        $this->notifications->sendToRoles(
            ['staff'],
            'document_request.created',
            'New document request',
            "{$resident->name} submitted {$documentType->name} ({$request->tracking_number}).",
            ['tracking_number' => $request->tracking_number, 'request_id' => $request->id],
            $resident->barangay_id,
        );

        return $request;
    }
}
