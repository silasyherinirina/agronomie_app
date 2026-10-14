<?php

namespace Database\Factories;

use App\Models\AgriculturalCampaign;
use App\Models\Input;
use App\Models\InputUsage;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InputUsage>
 */
class InputUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'input_id' => Input::factory(),
            'plot_id' => Plot::factory(),
            'campaign_id' => AgriculturalCampaign::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'cost' => fake()->randomFloat(2, 1000, 200000),
            'date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
