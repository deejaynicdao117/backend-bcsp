<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['key', 'value', 'group', 'updated_by'];

    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
