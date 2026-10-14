<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Toutes les FK optionnelles (farmer_id, plot_id, crop_id, variety_id, crop_cycle_id,
     * campaign_id) restent null par défaut : les tests qui en ont besoin les précisent
     * explicitement (cf. SaleService::assertRelationsAreCoherent, qui exigerait sinon une
     * coopérative cohérente pour chacune). `total_amount` respecte la même règle que le
     * backend réel (quantity × unit_price) — jamais une valeur incohérente, même en test.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 50, 2000);
        $unitPrice = fake()->randomFloat(2, 200, 3000);

        return [
            'cooperative_id' => Cooperative::factory(),
            'buyer_name' => fake()->company(),
            'buyer_contact' => fake()->phoneNumber(),
            'sale_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 2),
            'currency' => 'MGA',
        ];
    }
}
