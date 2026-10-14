<?php

namespace Database\Seeders;

use App\Models\ModelVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Historique de versions du modèle de prévision de rendement (service IA FastAPI/scikit-learn,
 * ai-service/ — Phase 9/10 déjà existante et NON modifiée ici). Ces lignes représentent un
 * historique d'entraînement PLAUSIBLE pour la démonstration (plusieurs itérations, métriques
 * s'améliorant progressivement) — même ordre de grandeur que la ligne réelle déjà produite par
 * une requête de prédiction (mae≈139, rmse≈167, r2≈0.54 pour un modèle linéaire sur le
 * rendement du riz en kg/ha, cf. ModelVersionFactory).
 *
 * `is_experimental` (Phase 16) n'est PAS une colonne stockée : ProductionPredictionResource la
 * force à `true` par défaut pour toute prédiction sans appel IA live associé (voir
 * app/Http/Resources/ProductionPredictionResource.php) — ces modèles de démonstration
 * n'échappent donc jamais à l'avertissement affiché côté frontend.
 *
 * Idempotent : `external_id` est déjà UNIQUE en base — updateOrCreate dessus. Seule la DERNIÈRE
 * version créée ici est marquée is_active=true (une ligne de test préexistante éventuelle n'est
 * jamais modifiée : elle ne partage aucun de ces external_id).
 */
class ModelVersionSeeder extends Seeder
{
    private const ALGORITHMS = ['linear_regression', 'random_forest', 'gradient_boosting'];

    public function run(): void
    {
        $count = 20;
        $trainedAt = Carbon::now()->subMonths(18);
        $lastId = null;

        // MAE/RMSE décroissants, R² croissant au fil des itérations — progression plausible
        // d'un entraînement itératif, bornée à des valeurs du même ordre de grandeur que la
        // version réelle déjà en base (voir commentaire de classe).
        $mae = 210.0;
        $rmse = 250.0;
        $r2 = 0.35;

        for ($i = 0; $i < $count; $i++) {
            $algorithm = self::ALGORITHMS[$i % count(self::ALGORITHMS)];
            $version = sprintf('1.%d.0', intdiv($i, count(self::ALGORITHMS)));
            $externalId = "rice-yield-{$algorithm}-demo-".str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            $mae = round(max(95, $mae - 5.5), 2);
            $rmse = round(max(120, $rmse - 6.2), 2);
            $r2 = round(min(0.67, $r2 + 0.015), 4);
            $trainedAt = $trainedAt->copy()->addDays(27);

            $modelVersion = ModelVersion::updateOrCreate(
                ['external_id' => $externalId],
                [
                    'name' => 'Rendement du riz (kg/ha)',
                    'algorithm' => $algorithm,
                    'version' => $version,
                    'trained_at' => $trainedAt,
                    'metrics' => ['mae' => $mae, 'rmse' => $rmse, 'r2' => $r2, 'dataset_size' => 400 + $i * 12],
                    'is_active' => false,
                ]
            );
            $lastId = $modelVersion->id;
        }

        if ($lastId) {
            ModelVersion::where('id', $lastId)->update(['is_active' => true]);
        }
    }
}
