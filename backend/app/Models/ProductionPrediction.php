<?php

namespace App\Models;

use Database\Factories\ProductionPredictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['model_version_id', 'crop_id', 'plot_id', 'campaign_id', 'predicted_yield', 'input_features', 'predicted_at'])]
class ProductionPrediction extends Model
{
    /** @use HasFactory<ProductionPredictionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'predicted_yield' => 'decimal:3',
            'input_features' => 'array',
            'predicted_at' => 'datetime',
        ];
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AgriculturalCampaign::class, 'campaign_id');
    }
}
