<?php

namespace App\Models;

use Database\Factories\AgriculturalCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'year', 'season', 'start_date', 'end_date', 'status'])]
class AgriculturalCampaign extends Model
{
    /** @use HasFactory<AgriculturalCampaignFactory> */
    use HasFactory;

    // Miroir du défaut SQL (migration) — voir Cooperative::$attributes pour le pourquoi.
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class, 'campaign_id');
    }

    public function inputUsages(): HasMany
    {
        return $this->hasMany(InputUsage::class, 'campaign_id');
    }

    public function productionPredictions(): HasMany
    {
        return $this->hasMany(ProductionPrediction::class, 'campaign_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'campaign_id');
    }
}
