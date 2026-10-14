<?php

namespace App\Models;

use Database\Factories\WeatherObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Placeholder Module L (Météo — extension non bloquante, Phase 11).
 */
#[Fillable(['plot_id', 'observed_date', 'temperature', 'precipitation', 'humidity', 'source'])]
class WeatherObservation extends Model
{
    /** @use HasFactory<WeatherObservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_date' => 'date',
            'temperature' => 'decimal:2',
            'precipitation' => 'decimal:2',
            'humidity' => 'decimal:2',
        ];
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }
}
