<?php

namespace App\Models;

use Database\Factories\FarmerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['cooperative_id', 'user_id', 'first_name', 'last_name', 'join_date', 'status'])]
class Farmer extends Model
{
    /** @use HasFactory<FarmerFactory> */
    use HasFactory, SoftDeletes;

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
            'join_date' => 'date',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function inputUsages(): HasMany
    {
        return $this->hasMany(InputUsage::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
