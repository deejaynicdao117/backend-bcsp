<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class CurrentBarangay
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id ?? (Auth::user()?->barangay_id ? (int) Auth::user()->barangay_id : null);
    }
}