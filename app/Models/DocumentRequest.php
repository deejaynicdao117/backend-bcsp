<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use BelongsToBarangay;

    protected $fillable = [
        'user_id',
        'document_type_id',
        'tracking_number',
        'purpose',
        'status',
        'remarks',
        'rejection_reason',
        'ready_for_pickup_at',
        'released_at',
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
            'ready_for_pickup_at' => 'date:Y-m-d',
            'released_at' => 'date:Y-m-d',
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
