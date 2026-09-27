<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
