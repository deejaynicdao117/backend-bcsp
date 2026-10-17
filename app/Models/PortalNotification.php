<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'message', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}