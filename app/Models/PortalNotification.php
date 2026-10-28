<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class PortalNotification extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['user_id', 'type', 'title', 'message', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}