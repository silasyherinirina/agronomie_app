<?php

namespace Database\Seeders;

use App\Models\Input;
use Illuminate\Database\Seeder;

/**
 * Intrants agricoles génériques (semences, engrais, produits phytosanitaires, amendements) —
 * catégories couramment utilisées à Madagascar. Aucune marque ni formulation commerciale
 * précise n'est utilisée pour les produits réglementés (Phase 13) : uniquement des désignations
 * génériques (ex. "NPK 11-22-16", pas un nom de marque).
 *
 * Idempotent : identifié par son nom (pas de contrainte unique en base, donc vérification
 * manuelle explicite avant création).
 */
class InputSeeder extends Seeder
{
    /**
     * @var list<array{name: string, type: string, unit: string}>
     */
    private const INPUTS = [
        ['name' => 'Semences de riz améliorées', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Semences de riz traditionnelles', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Semences de maïs', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Boutures de manioc', 'type' => 'semence', 'unit' => 'sac'],
        ['name' => 'Semences de haricot', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Semences d\'arachide', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Semences de pomme de terre', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Semences de patate douce', 'type' => 'semence', 'unit' => 'sac'],
        ['name' => 'Semences de soja', 'type' => 'semence', 'unit' => 'kg'],
        ['name' => 'Urée (46 % N)', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'NPK 11-22-16', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'DAP (phosphate diammonique)', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'Phosphate naturel', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'Dolomie agricole', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'Chaux agricole', 'type' => 'engrais', 'unit' => 'kg'],
        ['name' => 'Compost', 'type' => 'engrais', 'unit' => 'sac'],
        ['name' => 'Fumier de parc', 'type' => 'engrais', 'unit' => 'sac'],
        ['name' => 'Fongicide générique', 'type' => 'phytosanitaire', 'unit' => 'L'],
        ['name' => 'Insecticide générique', 'type' => 'phytosanitaire', 'unit' => 'L'],
        ['name' => 'Herbicide générique', 'type' => 'phytosanitaire', 'unit' => 'L'],
    ];

    public function run(): void
    {
        foreach (self::INPUTS as $input) {
            Input::firstOrCreate(
                ['name' => $input['name']],
                ['type' => $input['type'], 'unit' => $input['unit']]
            );
        }
    }
}
