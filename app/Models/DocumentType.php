<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use BelongsToBarangay;

    protected $fillable = [
        'name',
        'description',
        'body_template',
    ];

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
