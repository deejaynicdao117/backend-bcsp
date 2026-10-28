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
            'user.barangay',
            'documentType',
            'documentType.barangay',
        ]);

        $documentRequest->generated_document_at = now();
        $barangay = $documentRequest->documentType?->barangay ?? $documentRequest->user?->barangay;
        $template = $documentRequest->documentType?->body_template ?: <<<'BODY'
TO WHOM IT MAY CONCERN:

This is to certify that [resident_name], a resident of [barangay_name], [barangay_municipality], Bulacan, has requested a [document_name].

This certification is issued upon request for the purpose of: [purpose].

Issued this [date] at [barangay_name], [barangay_municipality], Bulacan.
BODY;
        $body = strtr($template, [
            '[resident_name]' => (string) $documentRequest->user?->name,
            '[document_name]' => (string) $documentRequest->documentType?->name,
            '[purpose]' => (string) $documentRequest->purpose,
            '[barangay_name]' => (string) ($barangay?->name ?? 'Barangay Office'),
            '[barangay_municipality]' => (string) ($barangay?->municipality ?? ''),
            '[date]' => now()->format('F j, Y'),
            '[resident_address]' => (string) ($documentRequest->user?->residentProfile?->address ?? ''),
        ]);

        $pdf = Pdf::loadView('documents.generated-request', [
            'documentRequest' => $documentRequest,
            'resident' => $documentRequest->user,
            'profile' => $documentRequest->user?->residentProfile,
            'documentType' => $documentRequest->documentType,
            'barangay' => $barangay,
            'body' => $body,
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
