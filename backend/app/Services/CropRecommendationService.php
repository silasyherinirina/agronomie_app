<?php

namespace App\Services;

use App\Models\Crop;
use Illuminate\Support\Collection;

/**
 * Moteur de score d'adaptation culture/météo — règles transparentes et explicables (cahier des
 * charges §9-10), seuils entièrement centralisés dans config/agronomy.php. AUCUN modèle de
 * Machine Learning ici : un simple barème pondéré, documenté, jamais présenté comme une vérité
 * scientifique (§7, §20).
 */
class CropRecommendationService
{
    /**
     * @param  Collection<int, Crop>  $crops  Cultures de la zone (déjà résolues contre la table `crops`).
     * @param  array<string, mixed>  $summary  WeatherAnalysisService::analyze()['summary'].
     * @return list<array{crop_id: int, crop: string, score: int, level: string, reasons: list<string>}>
     */
    public function recommendationsFor(Collection $crops, ?array $summary): array
    {
        return $crops
            ->map(fn (Crop $crop) => $this->scoreCrop($crop, $summary))
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array{crop_id: int, crop: string, score: int, level: string, reasons: list<string>}
     */
    private function scoreCrop(Crop $crop, ?array $summary): array
    {
        $weights = config('agronomy.scoring');
        $reasons = ["+ Culture traditionnellement cultivée dans cette zone agroécologique"];
        $score = (float) $weights['zone_match_points'];

        $profile = config("agronomy.crop_climate_profiles.{$crop->name}");

        if (! $profile || $summary === null) {
            $reasons[] = $profile === null
                ? '◦ Profil climatique non encore renseigné pour cette culture — score basé uniquement sur son adéquation à la zone.'
                : '◦ Données météo insuffisantes pour affiner le score — basé uniquement sur son adéquation à la zone.';

            return $this->result($crop, $score, $reasons);
        }

        if ($summary['temperature_min'] !== null && $summary['temperature_max'] !== null) {
            $midTemp = ($summary['temperature_min'] + $summary['temperature_max']) / 2;
            [$points, $isOptimal] = $this->bandScore($midTemp, $profile['temp_min'], $profile['temp_optimal'], $profile['temp_max'], $weights['temperature_points']);
            $score += $points;
            $reasons[] = $this->temperatureReason($points, $isOptimal, $weights['temperature_points'], $profile);
        }

        if ($summary['humidity_avg'] !== null) {
            [$points, $isOptimal] = $this->bandScore($summary['humidity_avg'], $profile['humidity_min'], $profile['humidity_optimal'], $profile['humidity_max'], $weights['humidity_points']);
            $score += $points;
            $reasons[] = $this->humidityReason($points, $isOptimal, $weights['humidity_points']);
        }

        if ($summary['rainfall_category'] !== null) {
            [$points, $label] = $this->rainfallScore($summary['rainfall_category'], $profile['rainfall_preference'], $weights['rainfall_points']);
            $score += $points;
            $reasons[] = $label;
        }

        return $this->result($crop, $score, $reasons);
    }

    /**
     * Score en cloche : plein score dans [optimal[0], optimal[1]], dégradé linéaire jusqu'aux
     * bornes [min, max], zéro en dehors. Retourne aussi si la valeur était dans l'optimum.
     *
     * @param  array{0: float, 1: float}  $optimal
     * @return array{0: float, 1: bool}
     */
    private function bandScore(float $value, float $min, array $optimal, float $max, float $points): array
    {
        [$optMin, $optMax] = $optimal;

        if ($value >= $optMin && $value <= $optMax) {
            return [$points, true];
        }
        if ($value < $min || $value > $max) {
            return [0.0, false];
        }

        $ratio = $value < $optMin
            ? ($value - $min) / max($optMin - $min, 0.001)
            : ($max - $value) / max($max - $optMax, 0.001);

        return [round($points * max(0, min(1, $ratio)), 1), false];
    }

    private function temperatureReason(float $points, bool $isOptimal, float $maxPoints, array $profile): string
    {
        if ($isOptimal) {
            return '+ Température favorable (dans la plage optimale de la culture)';
        }
        if ($points <= 0) {
            return sprintf('- Température en dehors de la plage tolérée par cette culture (%s–%s °C)', $profile['temp_min'], $profile['temp_max']);
        }

        return $points < $maxPoints / 2
            ? '- Température proche des limites tolérées par cette culture'
            : '◦ Température acceptable, mais en dehors de la plage optimale';
    }

    private function humidityReason(float $points, bool $isOptimal, float $maxPoints): string
    {
        if ($isOptimal) {
            return '+ Humidité favorable pour cette culture';
        }
        if ($points <= 0) {
            return '- Humidité trop éloignée des besoins de cette culture';
        }

        return $points < $maxPoints / 2 ? '- Humidité peu favorable' : '◦ Humidité acceptable';
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function rainfallScore(string $observedCategory, string $preferredCategory, float $maxPoints): array
    {
        if ($observedCategory === $preferredCategory) {
            return [$maxPoints, '+ Pluviométrie favorable pour cette culture'];
        }

        $order = ['low' => 0, 'moderate' => 1, 'high' => 2];
        $distance = abs($order[$observedCategory] - $order[$preferredCategory]);

        if ($distance === 1) {
            return [$maxPoints / 2, '◦ Pluviométrie acceptable, légèrement différente de l\'idéal pour cette culture'];
        }

        $label = $preferredCategory === 'low'
            ? '- Pluviométrie trop importante pour cette culture'
            : '- Pluviométrie insuffisante pour cette culture';

        return [0.0, $label];
    }

    /**
     * @param  list<string>  $reasons
     * @return array{crop_id: int, crop: string, score: int, level: string, reasons: list<string>}
     */
    private function result(Crop $crop, float $score, array $reasons): array
    {
        $score = (int) round(max(0, min(100, $score)));
        $thresholds = config('agronomy.scoring.level_thresholds');
        $level = match (true) {
            $score >= $thresholds['recommended'] => 'recommended',
            $score >= $thresholds['moderate'] => 'moderate',
            default => 'unfavorable',
        };

        return ['crop_id' => $crop->id, 'crop' => $crop->name, 'score' => $score, 'level' => $level, 'reasons' => $reasons];
    }

    /**
     * Risques météo génériques (indépendants d'une culture précise) — cahier des charges §9
     * point 3/4. Retourne une liste vide si aucun seuil n'est franchi (jamais un risque
     * inventé pour "remplir" la réponse).
     *
     * @param  array<string, mixed>|null  $summary
     * @return list<array{type: string, level: string, message: string}>
     */
    public function detectRisks(?array $summary): array
    {
        if ($summary === null) {
            return [];
        }

        $t = config('agronomy.risk_thresholds');
        $risks = [];

        if ($summary['humidity_avg'] !== null && $summary['humidity_avg'] >= $t['high_humidity']) {
            $risks[] = ['type' => 'humidity', 'level' => 'moderate', 'message' => 'Humidité élevée sur la période analysée : risque accru de maladies cryptogamiques (mildiou, rouille…).'];
        }
        if ($summary['precipitation_avg_per_day'] !== null && $summary['precipitation_avg_per_day'] >= $t['heavy_rainfall_mm_day']) {
            $risks[] = ['type' => 'excess_water', 'level' => 'high', 'message' => 'Précipitations importantes attendues/observées : risque d\'excès d\'eau, d\'érosion ou d\'asphyxie racinaire.'];
        }
        if ($summary['precipitation_avg_per_day'] !== null && $summary['precipitation_avg_per_day'] <= $t['drought_mm_day']
            && $summary['temperature_max'] !== null && $summary['temperature_max'] >= 28) {
            $risks[] = ['type' => 'drought', 'level' => 'moderate', 'message' => 'Précipitations très faibles combinées à des températures élevées : risque de stress hydrique.'];
        }
        if ($summary['wind_speed_max'] !== null && $summary['wind_speed_max'] >= $t['strong_wind_kmh']) {
            $risks[] = ['type' => 'wind', 'level' => 'moderate', 'message' => 'Vent soutenu : risque de verse pour les cultures hautes et de dommages mécaniques.'];
        }
        if ($summary['temperature_max'] !== null && $summary['temperature_max'] >= $t['heat_stress_c']) {
            $risks[] = ['type' => 'heat', 'level' => 'moderate', 'message' => 'Températures élevées : risque de stress thermique pour les cultures sensibles à la chaleur.'];
        }
        if ($summary['temperature_min'] !== null && $summary['temperature_min'] <= $t['cold_stress_c']) {
            $risks[] = ['type' => 'cold', 'level' => 'moderate', 'message' => 'Températures basses : risque de ralentissement de croissance, voire de dégâts pour les cultures sensibles au froid.'];
        }

        return $risks;
    }
}
