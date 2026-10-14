<?php

namespace Database\Factories;

use App\Models\AgriculturalCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgriculturalCampaign>
 */
class AgriculturalCampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2030);

        return [
            'name' => "Campagne {$year}",
            'year' => $year,
            'season' => fake()->randomElement(['saison sèche', 'saison des pluies']),
            'start_date' => "{$year}-04-01",
            'end_date' => "{$year}-10-31",
            'status' => 'active',
        ];
    }
}
