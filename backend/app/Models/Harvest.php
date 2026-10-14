<?php

namespace App\Models;

use Database\Factories\HarvestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['crop_cycle_id', 'harvest_date', 'quantity', 'unit', 'yield_value', 'yield_source', 'quality', 'observations'])]
class Harvest extends Model
{
    /** @use HasFactory<HarvestFactory> */
    use HasFactory, SoftDeletes;

    // Miroir du défaut SQL (migration) — voir Cooperative::$attributes pour le pourquoi.
    // Important ici : HarvestService lit aussi cet attribut pour choisir sa stratégie de rendement.
    protected $attributes = [
        'yield_source' => 'calculated',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harvest_date' => 'date',
            'quantity' => 'decimal:3',
            'yield_value' => 'decimal:3',
        ];
    }

    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }
}
