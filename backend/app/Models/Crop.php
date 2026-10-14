<?php

namespace App\Models;

use Database\Factories\CropFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'category', 'cycle_duration_days', 'is_active'])]
class Crop extends Model
{
    /** @use HasFactory<CropFactory> */
    use HasFactory;

    // Miroir du défaut SQL (migration) — voir Cooperative::$attributes pour le pourquoi.
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function varieties(): HasMany
    {
        return $this->hasMany(CropVariety::class);
    }

    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
