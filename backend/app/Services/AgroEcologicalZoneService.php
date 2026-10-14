<?php

namespace App\Services;

use App\Models\Crop;
use Illuminate\Support\Collection;

/**
 * Détermine la zone agroécologique d'une région administrative de Madagascar, et résout la
 * liste de cultures indicativement adaptées à une zone contre le catalogue RÉEL de cultures
 * (`crops`) — jamais une nouvelle table (cahier des charges §11).
 *
 * Classification centralisée dans config/agronomy.php — voir l'avertissement en tête de ce
 * fichier : première version d'aide à la décision, pas une vérité agronomique absolue.
 */
class AgroEcologicalZoneService
{
    /**
     * @return array{key: string, name: string, climate: string, regions: list<string>, crops: list<string>}|null
     *         null si la région n'est rattachée à aucune zone connue (cahier des charges §21,
     *         cas "parcelle sans région identifiable").
     */
    public function zoneForRegion(?string $region): ?array
    {
        if (! $region) {
            return null;
        }

        foreach (config('agronomy.zones') as $key => $zone) {
            if (in_array($region, $zone['regions'], true)) {
                return array_merge(['key' => $key], $zone);
            }
        }

        return null;
    }

    /**
     * Cultures du catalogue existant (`crops`) réellement associées à cette zone. Les noms de
     * la configuration absents de la table `crops` sont silencieusement écartés ici (voir
     * missingCropNames() pour les lister explicitement, à des fins de documentation).
     *
     * @return Collection<int, Crop>
     */
    public function cropsForZone(array $zone): Collection
    {
        return Crop::whereIn('name', $zone['crops'])->get()->sortBy(
            fn (Crop $crop) => array_search($crop->name, $zone['crops'], true)
        )->values();
    }

    /**
     * Noms de cultures listés dans la configuration de la zone mais absents de la table `crops`
     * — à documenter/ajouter au catalogue plutôt qu'à inventer une valeur (cahier des charges
     * §11). Non exposé par l'API : utilisé pour la documentation et les tests.
     *
     * @return list<string>
     */
    public function missingCropNames(array $zone): array
    {
        $existing = Crop::whereIn('name', $zone['crops'])->pluck('name')->all();

        return array_values(array_diff($zone['crops'], $existing));
    }
}
