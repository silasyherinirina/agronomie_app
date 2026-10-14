<?php

namespace Database\Seeders;

use App\Models\Farmer;
use App\Models\Plot;
use App\Services\PlotService;
use Database\Seeders\Support\MadagascarReferenceData as Ref;
use Illuminate\Database\Seeder;

/**
 * Une parcelle synthétique par agriculteur de démonstration (FarmerSeeder), géolocalisée près
 * du centre réel du district de sa coopérative (Ref::REGION_CENTERS) avec un léger décalage
 * aléatoire — jamais présentée comme une limite cadastrale officielle (Phase 9 du cahier des
 * charges : "parcelles SYNTHÉTIQUES de démonstration géolocalisées dans des zones agricoles
 * réelles").
 *
 * La superficie (area_ha) n'est JAMAIS écrite directement : comme pour une création via l'API,
 * ce Seeder passe par PlotService::create(), qui la fait calculer par PostGIS
 * (ST_Area(geography(geom))/10000) à partir de la géométrie — cf. règle §24 du cahier des
 * charges ("aucune valeur géographique inventée").
 *
 * Idempotent : une parcelle déjà présente sous un nom donné n'est jamais recréée ni sa
 * géométrie retouchée (recalculer PostGIS à chaque exécution de db:seed serait coûteux et
 * inutile pour une donnée qui ne doit de toute façon pas changer une fois posée).
 */
class PlotSeeder extends Seeder
{
    public function __construct(private readonly PlotService $plots) {}

    public function run(): void
    {
        $farmers = Farmer::with('cooperative')
            ->whereHas('cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        // Superficies cibles plausibles pour une petite exploitation familiale malgache (ordre
        // de grandeur général, pas une statistique INSTAT vérifiée pour ce dataset précis —
        // voir docs/DATASET-MADAGASCAR.md, "Limites") : de 0,3 à 3,5 ha environ.
        $targetAreasHa = [0.35, 0.5, 0.6, 0.8, 1.0, 1.2, 1.5, 1.8, 2.0, 2.5, 3.0, 3.5];

        foreach ($farmers as $i => $farmer) {
            $region = $farmer->cooperative->region;
            $center = Ref::REGION_CENTERS[$region] ?? null;
            if (! $center) {
                continue;
            }

            $commune = str_replace(' ', '-', $center['commune']);
            $name = sprintf('Parcelle %s %02d', $commune, $i + 1);

            if (Plot::where('name', $name)->exists()) {
                continue;
            }

            // Décalage aléatoire borné (~±0,03°, quelques km) autour du centre du district réel :
            // évite que toutes les parcelles d'une même région se superposent exactement.
            $lat = $center['lat'] + $this->jitter();
            $lng = $center['lng'] + $this->jitter();

            $areaHa = $targetAreasHa[$i % count($targetAreasHa)];
            $coordinates = $this->rectangleAround($lat, $lng, $areaHa);

            $this->plots->create([
                'cooperative_id' => $farmer->cooperative_id,
                'farmer_id' => $farmer->id,
                'name' => $name,
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$coordinates],
                ],
                'status' => 'active',
            ]);
        }
    }

    private function jitter(): float
    {
        return random_int(-300, 300) / 10000; // ±0.03°
    }

    /**
     * Rectangle simple (anneau fermé, 5 points) centré sur (lat, lng), dimensionné pour
     * approcher une superficie cible en hectares. La conversion degrés<->mètres utilise
     * l'approximation standard (111 320 m/° de latitude, corrigée par cos(latitude) pour la
     * longitude) — suffisante pour une géométrie de démonstration, PostGIS recalcule ensuite
     * l'aire réelle exacte (cf. commentaire de classe).
     *
     * @return list<array{0: float, 1: float}>
     */
    private function rectangleAround(float $lat, float $lng, float $areaHa): array
    {
        $areaM2 = $areaHa * 10000;
        $sideM = sqrt($areaM2);

        $deltaLat = $sideM / 111320;
        $deltaLng = $sideM / (111320 * cos(deg2rad($lat)));

        $lat1 = round($lat, 6);
        $lng1 = round($lng, 6);
        $lat2 = round($lat + $deltaLat, 6);
        $lng2 = round($lng + $deltaLng, 6);

        return [
            [$lng1, $lat1],
            [$lng2, $lat1],
            [$lng2, $lat2],
            [$lng1, $lat2],
            [$lng1, $lat1],
        ];
    }
}
