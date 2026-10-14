<?php

namespace App\Models;

use Database\Factories\CropCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['plot_id', 'crop_id', 'variety_id', 'campaign_id', 'planting_date', 'expected_harvest_date', 'status'])]
class CropCycle extends Model
{
    /** @use HasFactory<CropCycleFactory> */
    use HasFactory;

    // Miroir du défaut SQL (migration) — voir Cooperative::$attributes pour le pourquoi.
    protected $attributes = [
        'status' => 'in_progress',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planting_date' => 'date',
            'expected_harvest_date' => 'date',
        ];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AgriculturalCampaign::class, 'campaign_id');
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
