<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Harvest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'harvest_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 100, 5000),
            'unit' => 'kg',
            'yield_source' => 'declared',
            'yield_value' => fake()->randomFloat(2, 500, 3000),
        ];
    }
}
