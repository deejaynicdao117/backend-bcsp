<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'updated_by'];

    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
