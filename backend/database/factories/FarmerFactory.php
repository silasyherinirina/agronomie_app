<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'join_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
