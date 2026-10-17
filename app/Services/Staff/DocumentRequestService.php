<?php

namespace App\Services\Staff;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\PortalNotificationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentRequestService
{
    public function __construct(
        private readonly DocumentPdfGenerator $pdfGenerator,
        private readonly PortalNotificationService $notifications,
    ) {}

    public function index(): Collection
    {
        return DocumentRequest::with(['user', 'documentType'])
            ->latest()
            ->get();
    }

    public function updateStatus(User $staff, DocumentRequest $documentRequest, array $data): DocumentRequest
    {
        $previousStatus = $documentRequest->status;
        $updatedRequest = DB::transaction(function () use ($staff, $documentRequest, $data): DocumentRequest {
            $documentRequest->update([
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? $documentRequest->remarks,
                'reviewed_by' => $staff->id,
            ]);

            if (
                $data['status'] === 'approved'
                && ! $documentRequest->generated_document_path
            ) {
                return $this->pdfGenerator->generate($documentRequest);
            }

            return $documentRequest->fresh()->load(['user', 'documentType', 'reviewer']);
        });

        if ($previousStatus !== $updatedRequest->status) {
            $documentName = $updatedRequest->documentType?->name ?? 'document request';
            $message = "Your {$documentName} request ({$updatedRequest->tracking_number}) is now {$updatedRequest->status}.";
            if (filled($updatedRequest->remarks)) {
                $message .= " Staff note: {$updatedRequest->remarks}";
            }

            $this->notifications->send(
                $updatedRequest->user,
                'document_request.status',
                'Document request update',
                $message,
                ['tracking_number' => $updatedRequest->tracking_number, 'request_id' => $updatedRequest->id, 'status' => $updatedRequest->status],
            );
        }

        return $updatedRequest;
    }

    public function generatedDocumentPath(DocumentRequest $documentRequest): string
    {
        $path = $documentRequest->generated_document_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException('The generated document is not available. Approve the request first.');
        }

        return $path;
    }

    public function generatedDocumentAbsolutePath(DocumentRequest $documentRequest): string
    {
        $path = $this->generatedDocumentPath($documentRequest);

        return Storage::disk('local')->path($path);
    }
}
