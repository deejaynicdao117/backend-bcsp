<?php

namespace App\Services;

use App\Models\DocumentType;
use Illuminate\Support\Collection;

class DocumentTypeService
{
    public function all(): Collection
    {
        return DocumentType::all();
    }

    public function create(array $data): DocumentType
    {
        return DocumentType::create($data);
    }
}
