<?php

namespace App\Services;

use App\Models\Plot;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

/**
 * Logique métier liée aux parcelles : calcul de superficie via PostGIS
 * (stratégie validée en Phase 0, §M : ST_Area(geography(geom)) / 10000).
 */
class PlotService
{
    /**
     * Crée une parcelle et calcule sa superficie réelle à partir de la géométrie fournie.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Plot
    {
        $plot = new Plot;
        $plot->fill($this->prepareAttributes($attributes));
        $plot->save();

        $plot->area_ha = $this->calculateAreaHa($plot);
        $plot->saveQuietly();

        return $plot;
    }

    /**
     * Met à jour une parcelle et recalcule la superficie si la géométrie a changé.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Plot $plot, array $attributes): Plot
    {
        $plot->fill($this->prepareAttributes($attributes));
        $geometryChanged = $plot->isDirty('geom');
        $plot->save();

        if ($geometryChanged) {
            $plot->area_ha = $this->calculateAreaHa($plot);
            $plot->saveQuietly();
        }

        return $plot;
    }

    /**
     * Superficie réelle en hectares, calculée par PostGIS (méthode géographique, pas planaire).
     * Ne jamais laisser l'utilisateur saisir cette valeur manuellement (règle §24 — pas de valeur
     * géographique inventée).
     */
    public function calculateAreaHa(Plot $plot): float
    {
        $result = DB::selectOne(
            'SELECT ST_Area(geography(geom)) / 10000 AS area_ha FROM plots WHERE id = ?',
            [$plot->id]
        );

        return round((float) $result->area_ha, 4);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepareAttributes(array $attributes): array
    {
        if (array_key_exists('geometry', $attributes)) {
            $attributes['geom'] = Polygon::fromArray($attributes['geometry']);
            unset($attributes['geometry']);
        }

        return $attributes;
    }
}
