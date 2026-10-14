<?php

namespace Database\Seeders;

use App\Models\Plot;
use App\Models\WeatherObservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Observations météo MANUELLES de démonstration (une par parcelle de démonstration) — à ne pas
 * confondre avec les prévisions Open-Meteo, qui restent récupérées EN DIRECT et mises en cache
 * par WeatherProviderService (aucune donnée Open-Meteo n'est persistée en base : voir
 * docs/DATASET-MADAGASCAR.md, "Météo"). Cette table sert uniquement au relevé de terrain saisi
 * manuellement (WeatherObservationController), fonctionnalité déjà existante et non modifiée
 * ici.
 *
 * Les plages de température par grande zone climatique (hauts-plateaux plus frais / côtes et
 * ouest plus chauds) reflètent une connaissance géographique générale de Madagascar, pas une
 * mesure officielle précise — valeurs clairement SYNTHÉTIQUES (source = 'manuel', comme toute
 * saisie de terrain dans l'application).
 *
 * Idempotent : au plus une observation par (plot_id, observed_date).
 */
class WeatherObservationSeeder extends Seeder
{
    /** Régions des hauts-plateaux (climat tempéré d'altitude) — le reste est traité comme
     * côtier/bas-plateaux (plus chaud et humide), par exemple Atsinanana, Boeny, Diana, Sava,
     * Menabe, Melaky, Atsimo-Andrefana, Androy, Anosy, Alaotra-Mangoro, Sofia. */
    private const HIGHLAND_REGIONS = ['Analamanga', 'Vakinankaratra', 'Itasy', "Amoron'i Mania", 'Haute Matsiatra', 'Ihorombe', 'Bongolava'];

    public function run(): void
    {
        $plots = Plot::with('cooperative')
            ->whereHas('cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        foreach ($plots as $i => $plot) {
            $observedDate = Carbon::today()->subDays(2 + $i * 2);
            if (WeatherObservation::where('plot_id', $plot->id)->where('observed_date', $observedDate->toDateString())->exists()) {
                continue;
            }

            $isHighland = in_array($plot->cooperative->region, self::HIGHLAND_REGIONS, true);
            [$tempMin, $tempMax] = $isHighland ? [12, 27] : [20, 33];
            [$humMin, $humMax] = $isHighland ? [45, 85] : [55, 95];

            WeatherObservation::create([
                'plot_id' => $plot->id,
                'observed_date' => $observedDate->toDateString(),
                'temperature' => round($tempMin + (($i * 13) % 100) / 100 * ($tempMax - $tempMin), 1),
                'precipitation' => round((($i * 29) % 100) / 100 * 25, 1),
                'humidity' => round($humMin + (($i * 17) % 100) / 100 * ($humMax - $humMin), 1),
                'source' => 'manuel',
            ]);
        }
    }
}
