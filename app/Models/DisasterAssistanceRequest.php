<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisasterAssistanceRequest extends Model
{
    protected $fillable = ['resident_id', 'reference_number', 'incident_type', 'description', 'location', 'purok', 'priority', 'status', 'assigned_to', 'evacuation_center_id', 'staff_remarks', 'responded_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function resident(): BelongsTo { return $this->belongsTo(User::class, 'resident_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function evacuationCenter(): BelongsTo { return $this->belongsTo(EvacuationCenter::class); }
}
