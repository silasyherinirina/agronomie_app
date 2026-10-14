<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Support\CropKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crop>
 */
class CropFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'name' => "{$word} (test)",
            // Un `code` (crop_key) est toujours présent même pour une culture de test, comme en
            // production (StoreCropRequest le génère de la même façon si omis) — sans lui,
            // PredictionService::resolveCropKey() refuserait toute prédiction pour cette culture.
            'code' => CropKey::fromName($word).'_test',
            'category' => fake()->randomElement(['céréale', 'tubercule', 'légumineuse']),
            'cycle_duration_days' => fake()->numberBetween(60, 180),
        ];
    }
}
