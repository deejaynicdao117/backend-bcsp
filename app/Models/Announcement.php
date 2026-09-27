<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = ['author_id', 'title', 'body', 'type', 'severity', 'status', 'location', 'starts_at', 'ends_at', 'published_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
