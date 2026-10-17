<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentProfile extends Model
{
    protected $fillable = [
        'user_id', 'last_name', 'first_name', 'middle_name', 'phone', 'birth_date', 'sex', 'civil_status', 'occupation', 'address', 'purok',
        'verification_status', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
