<?php

namespace App\Models;

use Database\Factories\PlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

#[Fillable(['cooperative_id', 'farmer_id', 'name', 'geom', 'area_ha', 'status'])]
class Plot extends Model
{
    /** @use HasFactory<PlotFactory> */
    use HasFactory, HasSpatial, SoftDeletes;

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
            'geom' => Polygon::class,
            'area_ha' => 'decimal:4',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    public function inputUsages(): HasMany
    {
        return $this->hasMany(InputUsage::class);
    }

    public function productionPredictions(): HasMany
    {
        return $this->hasMany(ProductionPrediction::class);
    }

    public function weatherObservations(): HasMany
    {
        return $this->hasMany(WeatherObservation::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
