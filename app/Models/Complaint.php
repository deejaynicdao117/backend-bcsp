<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'resident_id', 'reference_number', 'subject', 'description', 'category', 'priority', 'location', 'purok',
        'status', 'ai_recommended_category', 'ai_recommended_priority', 'ai_summary', 'ai_engine', 'validation_flags',
        'possible_duplicate', 'final_category', 'final_priority', 'assigned_to', 'reviewed_by', 'staff_remarks',
        'first_response_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'validation_flags' => 'array',
            'possible_duplicate' => 'boolean',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo { return $this->belongsTo(User::class, 'resident_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
