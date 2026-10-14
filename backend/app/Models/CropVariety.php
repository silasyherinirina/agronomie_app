<?php

namespace App\Models;

use Database\Factories\CropVarietyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['crop_id', 'name'])]
class CropVariety extends Model
{
    /** @use HasFactory<CropVarietyFactory> */
    use HasFactory;

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class, 'variety_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'variety_id');
    }
}
