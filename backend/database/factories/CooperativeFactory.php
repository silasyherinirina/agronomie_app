<?php

namespace Database\Factories;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cooperative>
 */
class CooperativeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Coopérative '.fake()->unique()->company(),
            'region' => fake()->randomElement(['Analamanga', 'Vakinankaratra', 'Haute Matsiatra', 'Boeny']),
            'status' => 'active',
        ];
    }
}
