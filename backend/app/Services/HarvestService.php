<?php

namespace App\Services;

use App\Models\CropCycle;
use App\Models\Harvest;

/**
 * Stratégie de rendement (Phase 0, §F) :
 * - 'declared'  : le rendement est saisi directement par l'utilisateur (yield_value obligatoire).
 * - 'calculated': le rendement est dérivé de quantity / plot.area_ha (kg ou unité déclarée / ha),
 *   uniquement si la superficie de la parcelle est connue (calculée par PostGIS, jamais inventée).
 */
class HarvestService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Harvest
    {
        $attributes['yield_value'] = $this->resolveYieldValue($attributes);

        return Harvest::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Harvest $harvest, array $attributes): Harvest
    {
        $merged = array_merge($harvest->only(['crop_cycle_id', 'quantity', 'yield_source']), $attributes);
        $attributes['yield_value'] = $this->resolveYieldValue($merged, $harvest);

        $harvest->update($attributes);

        return $harvest;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveYieldValue(array $attributes, ?Harvest $existing = null): ?float
    {
        $source = $attributes['yield_source'] ?? $existing?->yield_source ?? 'calculated';

        if ($source === 'declared') {
            return isset($attributes['yield_value']) ? (float) $attributes['yield_value'] : $existing?->yield_value;
        }

        $cropCycleId = $attributes['crop_cycle_id'] ?? $existing?->crop_cycle_id;
        $quantity = $attributes['quantity'] ?? $existing?->quantity;

        $plot = CropCycle::find($cropCycleId)?->plot;

        if (! $plot || $plot->area_ha === null || (float) $plot->area_ha <= 0.0 || $quantity === null) {
            return null;
        }

        return round((float) $quantity / (float) $plot->area_ha, 3);
    }
}
