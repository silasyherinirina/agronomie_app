<?php

namespace Database\Factories;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plot_id' => Plot::factory(),
            'crop_id' => Crop::factory(),
            'campaign_id' => AgriculturalCampaign::factory(),
            'planting_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'status' => 'in_progress',
        ];
    }
}
