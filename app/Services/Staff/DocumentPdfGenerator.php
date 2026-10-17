<?php

namespace App\Services\Staff;

use App\Models\DocumentRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DocumentPdfGenerator
{
    public function generate(DocumentRequest $documentRequest): DocumentRequest
    {
        $documentRequest->loadMissing([
            'user.residentProfile',
            'documentType',
        ]);

        $documentRequest->generated_document_at = now();

        $pdf = Pdf::loadView('documents.generated-request', [
            'documentRequest' => $documentRequest,
            'resident' => $documentRequest->user,
            'profile' => $documentRequest->user?->residentProfile,
            'documentType' => $documentRequest->documentType,
        ])->setPaper('a4');

        $path = sprintf(
            'document-requests/%d/%s.pdf',
            $documentRequest->id,
            $documentRequest->tracking_number,
        );

        Storage::disk('local')->put($path, $pdf->output());

        $documentRequest->forceFill([
            'generated_document_path' => $path,
            'generated_document_at' => now(),
        ])->save();

        return $documentRequest->fresh()->load(['user', 'documentType', 'reviewer']);
    }
}
