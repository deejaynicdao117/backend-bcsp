<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'document_type_id',
        'tracking_number',
        'purpose',
        'status',
        'remarks',
        'reviewed_by',
        'generated_document_path',
        'generated_document_at',
    ];

    protected $hidden = [
        'generated_document_path',
    ];

    protected $appends = [
        'generated_document_available',
    ];

    protected function casts(): array
    {
        return [
            'generated_document_at' => 'datetime',
        ];
    }

    public function getGeneratedDocumentAvailableAttribute(): bool
    {
        return filled($this->generated_document_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
