<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class EvacuationCenter extends Model
{
    use BelongsToBarangay;

    protected $fillable = [
        'name',
        'address',
        'purok',
        'capacity',
        'current_occupancy',
        'contact_phone',
        'status',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
