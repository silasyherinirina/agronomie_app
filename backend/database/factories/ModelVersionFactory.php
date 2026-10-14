<?php

namespace Database\Factories;

use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelVersion>
 */
class ModelVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => 'rice-yield-linear_regression-'.fake()->unique()->numerify('##########'),
            'name' => 'Rendement du riz (kg/ha)',
            'algorithm' => 'linear_regression',
            'version' => '1.0.0',
            'trained_at' => now(),
            'metrics' => ['mae' => 138.95, 'rmse' => 167.01, 'r2' => 0.5381],
            'is_active' => true,
        ];
    }
}
