<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Support\CropKey;
use Illuminate\Database\Seeder;

/**
 * Cultures réellement documentées comme cultivées à Madagascar (FAO, FOFIFA, INSTAT — voir
 * docs/SOURCES-AGRICULTURE-MADAGASCAR.md). `category`/`cycle_duration_days` sont des ordres de
 * grandeur agronomiques généraux, pas une fiche technique officielle par culture.
 *
 * Idempotent : `crops.name` est déjà UNIQUE en base (voir migration) — updateOrCreate dessus.
 * Les 8 cultures de test préexistantes (ex. "cupiditate (test)") ne sont ni supprimées ni
 * modifiées : elles ne portent aucun de ces noms, aucune collision possible.
 *
 * CORRECTIF RACINE (cause exacte du bug "Patate douce introuvable") : cette méthode `run()`
 * n'écrivait auparavant PAS `code`/`is_active` — seule la migration de backfill
 * (2026_09_10_121751_add_code_and_is_active_to_crops_table) les renseignait, et seulement pour
 * les lignes DÉJÀ présentes au moment où cette migration s'exécute. Sur une installation propre
 * (`migrate --seed` : les migrations tournent AVANT les seeders), la table `crops` est encore
 * vide au moment de la migration — le backfill ne trouve donc rien à corriger, et les 20
 * cultures créées ensuite par ce Seeder se retrouvaient avec `code = NULL`. Résultat :
 * `PredictionService::resolveCropKey()` refusait toute prédiction pour "Patate douce" (et pour
 * toutes les cultures hors des 5 déjà migrées manuellement) faute de `crop_key` exploitable.
 * `code` est donc désormais écrit explicitement ici, via le même normalisateur que
 * StoreCropRequest (§5 : une seule fonction de normalisation) — plus jamais dépendant de l'ordre
 * migrations/seeders.
 */
class CropSeeder extends Seeder
{
    /**
     * @var list<array{name: string, category: string, cycle_duration_days: ?int}>
     */
    private const CROPS = [
        ['name' => 'Riz', 'category' => 'céréale', 'cycle_duration_days' => 130],
        ['name' => 'Maïs', 'category' => 'céréale', 'cycle_duration_days' => 100],
        ['name' => 'Blé', 'category' => 'céréale', 'cycle_duration_days' => 130],
        ['name' => 'Triticale', 'category' => 'céréale', 'cycle_duration_days' => 130],
        ['name' => 'Sorgho', 'category' => 'céréale', 'cycle_duration_days' => 110],
        ['name' => 'Manioc', 'category' => 'tubercule', 'cycle_duration_days' => 300],
        ['name' => 'Patate douce', 'category' => 'tubercule', 'cycle_duration_days' => 105],
        ['name' => 'Pomme de terre', 'category' => 'tubercule', 'cycle_duration_days' => 100],
        ['name' => 'Taro', 'category' => 'tubercule', 'cycle_duration_days' => 240],
        ['name' => 'Gingembre', 'category' => 'épice', 'cycle_duration_days' => 270],
        ['name' => 'Haricot', 'category' => 'légumineuse', 'cycle_duration_days' => 80],
        ['name' => 'Arachide', 'category' => 'légumineuse', 'cycle_duration_days' => 100],
        ['name' => 'Niébé', 'category' => 'légumineuse', 'cycle_duration_days' => 75],
        ['name' => 'Pois du Cap', 'category' => 'légumineuse', 'cycle_duration_days' => 100],
        ['name' => 'Voandzou', 'category' => 'légumineuse', 'cycle_duration_days' => 130],
        ['name' => 'Soja', 'category' => 'légumineuse', 'cycle_duration_days' => 100],
        ['name' => 'Sésame', 'category' => 'oléagineuse', 'cycle_duration_days' => 100],
        ['name' => 'Café', 'category' => 'culture de rente', 'cycle_duration_days' => null],
        ['name' => 'Vanille', 'category' => 'culture de rente', 'cycle_duration_days' => null],
        ['name' => 'Coton', 'category' => 'culture de rente', 'cycle_duration_days' => 160],
    ];

    public function run(): void
    {
        foreach (self::CROPS as $crop) {
            Crop::updateOrCreate(
                ['name' => $crop['name']],
                [
                    'code' => CropKey::fromName($crop['name']),
                    'category' => $crop['category'],
                    'cycle_duration_days' => $crop['cycle_duration_days'],
                    'is_active' => true,
                ]
            );
        }
    }
}
