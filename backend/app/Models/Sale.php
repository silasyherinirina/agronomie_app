<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Module M — Ventes agricoles. `total_amount` n'est jamais assigné directement depuis une
 * requête HTTP (voir SaleService::create()/update()) : il est absent de `$fillable` ci-dessous
 * n'aurait pas suffi seul (un appel direct à Sale::create() pourrait encore l'inclure) — c'est
 * la responsabilité du Service de toujours le recalculer avant d'appeler le modèle.
 */
#[Fillable([
    'cooperative_id', 'farmer_id', 'plot_id', 'crop_id', 'variety_id', 'crop_cycle_id', 'campaign_id',
    'buyer_name', 'buyer_contact', 'sale_date', 'quantity', 'unit', 'unit_price', 'total_amount', 'currency', 'observations',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, SoftDeletes;

    // Miroir du défaut SQL (migration) — voir Cooperative::$attributes pour le pourquoi.
    protected $attributes = [
        'currency' => 'MGA',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
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

    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AgriculturalCampaign::class, 'campaign_id');
    }
}
