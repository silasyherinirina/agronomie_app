<?php

namespace App\Models;

use Database\Factories\InputUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['input_id', 'plot_id', 'campaign_id', 'farmer_id', 'quantity', 'cost', 'date', 'observations'])]
class InputUsage extends Model
{
    /** @use HasFactory<InputUsageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:3',
            'cost' => 'decimal:2',
        ];
    }

    public function input(): BelongsTo
    {
        return $this->belongsTo(Input::class);
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AgriculturalCampaign::class, 'campaign_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
