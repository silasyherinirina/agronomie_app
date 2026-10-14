<?php

namespace Database\Factories;

use App\Models\ModelVersion;
use App\Models\ProductionPrediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionPrediction>
 */
class ProductionPredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_version_id' => ModelVersion::factory(),
            'predicted_yield' => fake()->randomFloat(3, 1500, 3000),
            'input_features' => [
                'area_ha' => 1.5,
                'fertilizer_kg_per_ha' => 50,
                'rainfall_mm' => 1300,
                'planting_month' => 11,
                'variety_class' => 'amelioree',
            ],
            'predicted_at' => now(),
        ];
    }
}
