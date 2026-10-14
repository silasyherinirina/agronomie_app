<?php

namespace Database\Factories;

use App\Models\Input;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Input>
 */
class InputFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Intrant '.fake()->unique()->word(),
            'type' => fake()->randomElement(['semence', 'engrais', 'phytosanitaire', 'autre']),
            'unit' => fake()->randomElement(['kg', 'L', 'sac']),
        ];
    }
}
