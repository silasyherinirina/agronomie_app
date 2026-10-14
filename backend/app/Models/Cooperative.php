<?php

namespace App\Models;

use Database\Factories\CooperativeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

#[Fillable(['name', 'region', 'district', 'commune', 'location', 'manager_user_id', 'status'])]
class Cooperative extends Model
{
    /** @use HasFactory<CooperativeFactory> */
    use HasFactory, HasSpatial, SoftDeletes;

    // Miroir du défaut SQL (migration) : sans ceci, un create() qui omet 'status' insère
    // correctement 'active' en base mais le modèle en mémoire garderait null tant qu'il
    // n'est pas rechargé (Eloquent ne relit pas les défauts calculés côté SQL après INSERT).
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => Point::class,
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
