<?php

namespace App\Services\Staff;

use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $previousDetails = [
            'rejection_reason' => $documentRequest->rejection_reason,
            'ready_for_pickup_at' => $documentRequest->ready_for_pickup_at?->toDateString(),
            'released_at' => $documentRequest->released_at?->toDateString(),
        ];
        $updatedRequest = DB::transaction(function () use ($staff, $documentRequest, $data): DocumentRequest {
            $documentRequest->update([
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? $documentRequest->remarks,
                'rejection_reason' => $data['rejection_reason'] ?? $documentRequest->rejection_reason,
                'ready_for_pickup_at' => $data['ready_for_pickup_at'] ?? $documentRequest->ready_for_pickup_at,
                'released_at' => $data['released_at'] ?? $documentRequest->released_at,
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

        $detailsChanged = $previousDetails !== [
            'rejection_reason' => $updatedRequest->rejection_reason,
            'ready_for_pickup_at' => $updatedRequest->ready_for_pickup_at?->toDateString(),
            'released_at' => $updatedRequest->released_at?->toDateString(),
        ];

        if ($previousStatus !== $updatedRequest->status || $detailsChanged) {
            $documentName = $updatedRequest->documentType?->name ?? 'document request';
            $message = "Your {$documentName} request ({$updatedRequest->tracking_number}) is now {$updatedRequest->status}.";
            if (filled($updatedRequest->remarks)) {
                $message .= " Staff note: {$updatedRequest->remarks}";
            }
            if ($updatedRequest->status === 'rejected' && filled($updatedRequest->rejection_reason)) {
                $message .= " Reason: {$updatedRequest->rejection_reason}";
            }
            if ($updatedRequest->status === 'ready_for_pickup' && $updatedRequest->ready_for_pickup_at) {
                $message .= ' Ready for pickup on '.$updatedRequest->ready_for_pickup_at->format('F j, Y').'.';
            }
            if ($updatedRequest->status === 'released' && $updatedRequest->released_at) {
                $message .= ' Released on '.$updatedRequest->released_at->format('F j, Y').'.';
            }

            $this->notifications->send(
                $updatedRequest->user,
                'document_request.status',
                'Document request update',
                $message,
                [
                    'tracking_number' => $updatedRequest->tracking_number,
                    'request_id' => $updatedRequest->id,
                    'status' => $updatedRequest->status,
                    'rejection_reason' => $updatedRequest->rejection_reason,
                    'ready_for_pickup_at' => $updatedRequest->ready_for_pickup_at?->toDateString(),
                    'released_at' => $updatedRequest->released_at?->toDateString(),
                ],
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
