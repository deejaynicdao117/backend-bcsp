<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    protected $appends = ['logo_url'];

    protected $fillable = [
        'psgc_code', 'name', 'municipality', 'province', 'logo_path',
        'contact_email', 'contact_phone', 'office_address', 'primary_color', 'is_active',
        'onboarding_status', 'onboarding_reviewed_at', 'onboarding_reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'onboarding_reviewed_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function onboardingReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onboarding_reviewed_by');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}