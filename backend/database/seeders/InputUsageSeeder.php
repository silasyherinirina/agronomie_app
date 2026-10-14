<?php

namespace Database\Seeders;

use App\Models\CropCycle;
use App\Models\Input;
use App\Models\InputUsage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deux utilisations d'intrants par cycle cultural clôturé de démonstration (CropCycleSeeder) :
 * la semence de la culture concernée, puis un engrais NPK — quantités calculées au hectare
 * (superficie PostGIS réelle de la parcelle), jamais une quantité disproportionnée par rapport
 * à la taille de la parcelle (Phase 14 : "une parcelle de 1 ha ne doit pas recevoir 500 tonnes
 * d'engrais").
 *
 * Les prix unitaires (PRICES_AR ci-dessous, en Ariary) sont des PRIX DE DÉMONSTRATION : ils ne
 * proviennent d'aucune source officielle et ne doivent jamais être cités comme tels (Phase 13)
 * — voir docs/DATASET-MADAGASCAR.md.
 *
 * Idempotent : au plus une utilisation par (input_id, plot_id, campaign_id).
 */
class InputUsageSeeder extends Seeder
{
    /** Semence associée à chaque culture, et dose approximative en kg (ou sac) par hectare —
     * ordre de grandeur agronomique général, pas une prescription officielle. */
    private const SEED_BY_CROP = [
        'Riz' => ['input' => 'Semences de riz améliorées', 'per_ha' => 50],
        'Maïs' => ['input' => 'Semences de maïs', 'per_ha' => 22],
        'Manioc' => ['input' => 'Boutures de manioc', 'per_ha' => 4],
        'Haricot' => ['input' => 'Semences de haricot', 'per_ha' => 60],
        'Pomme de terre' => ['input' => 'Semences de pomme de terre', 'per_ha' => 1200],
    ];

    /** Prix de démonstration en Ariary par unité (kg, L ou sac selon l'intrant) — voir
     * avertissement dans le commentaire de classe. */
    private const PRICES_AR = [
        'Semences de riz améliorées' => 3500,
        'Semences de maïs' => 6000,
        'Boutures de manioc' => 8000,
        'Semences de haricot' => 5500,
        'Semences de pomme de terre' => 1800,
        'NPK 11-22-16' => 2600,
    ];

    public function run(): void
    {
        $npk = Input::where('name', 'NPK 11-22-16')->first();
        if (! $npk) {
            return;
        }

        $cycles = CropCycle::with(['plot', 'crop'])
            ->where('status', 'harvested')
            ->whereHas('plot.cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        foreach ($cycles as $i => $cycle) {
            $areaHa = $cycle->plot?->area_ha !== null ? (float) $cycle->plot->area_ha : null;
            if (! $areaHa || $areaHa <= 0) {
                continue;
            }

            $date = Carbon::parse($cycle->planting_date)->addDays(3);
            $seedSpec = self::SEED_BY_CROP[$cycle->crop->name] ?? null;

            if ($seedSpec) {
                $seedInput = Input::where('name', $seedSpec['input'])->first();
                if ($seedInput && ! $this->exists($seedInput->id, $cycle->plot_id, $cycle->campaign_id)) {
                    $quantity = round($seedSpec['per_ha'] * $areaHa, 2);
                    InputUsage::create([
                        'input_id' => $seedInput->id,
                        'plot_id' => $cycle->plot_id,
                        'campaign_id' => $cycle->campaign_id,
                        'farmer_id' => $cycle->plot->farmer_id,
                        'quantity' => $quantity,
                        'cost' => round($quantity * (self::PRICES_AR[$seedSpec['input']] ?? 3000), 2),
                        'date' => $date->toDateString(),
                    ]);
                }
            }

            if (! $this->exists($npk->id, $cycle->plot_id, $cycle->campaign_id)) {
                // Dose d'engrais générique ~180 kg/ha, avec une légère variation déterministe.
                $quantity = round((150 + ($i % 6) * 10) * $areaHa / 1, 2);
                // La formule ci-dessus est déjà "par ha" (constante × area_ha) — pas de double
                // multiplication par la superficie.
                InputUsage::create([
                    'input_id' => $npk->id,
                    'plot_id' => $cycle->plot_id,
                    'campaign_id' => $cycle->campaign_id,
                    'farmer_id' => $cycle->plot->farmer_id,
                    'quantity' => $quantity,
                    'cost' => round($quantity * self::PRICES_AR['NPK 11-22-16'], 2),
                    'date' => $date->copy()->addDays(15)->toDateString(),
                ]);
            }
        }
    }

    private function exists(int $inputId, int $plotId, int $campaignId): bool
    {
        return InputUsage::where('input_id', $inputId)
            ->where('plot_id', $plotId)
            ->where('campaign_id', $campaignId)
            ->exists();
    }
}
