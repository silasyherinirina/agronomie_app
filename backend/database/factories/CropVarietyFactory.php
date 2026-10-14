<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\CropVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropVariety>
 */
class CropVarietyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crop_id' => Crop::factory(),
            'name' => 'Variété '.fake()->unique()->word(),
        ];
    }
}
