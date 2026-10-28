<?php

namespace App\Models\Concerns;

use App\Models\Barangay;
use App\Support\CurrentBarangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

trait BelongsToBarangay
{
    protected static function bootBelongsToBarangay(): void
    {
        static::addGlobalScope('barangay', function (Builder $builder): void {
            $barangayId = app(CurrentBarangay::class)->id();
            if ($barangayId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('barangay_id'), $barangayId);
            }
        });

        static::creating(function ($model): void {
            $barangayId = app(CurrentBarangay::class)->id()
                ?? DB::table('barangays')->where('psgc_code', 'DEFAULT')->value('id');

            if (! $model->barangay_id && $barangayId) {
                $model->barangay_id = $barangayId;
            }
        });
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}