<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['name', 'office', 'phone', 'alternate_phone', 'availability', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
