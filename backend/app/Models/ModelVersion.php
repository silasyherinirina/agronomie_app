<?php

namespace App\Models;

use Database\Factories\ModelVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['crop_id', 'external_id', 'name', 'algorithm', 'version', 'trained_at', 'metrics', 'is_active'])]
class ModelVersion extends Model
{
    /** @use HasFactory<ModelVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trained_at' => 'datetime',
            'metrics' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Architecture multi-cultures : culture pour laquelle ce modèle a été entraîné — nullable
     * pour les modèles enregistrés avant cette extension (cf. migration). */
    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(ProductionPrediction::class);
    }
}
