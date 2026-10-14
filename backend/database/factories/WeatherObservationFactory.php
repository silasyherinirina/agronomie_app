<?php

namespace Database\Factories;

use App\Models\Plot;
use App\Models\WeatherObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherObservation>
 */
class WeatherObservationFactory extends Factory
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
            'observed_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'temperature' => fake()->randomFloat(2, 18, 32),
            'precipitation' => fake()->randomFloat(2, 0, 60),
            'humidity' => fake()->randomFloat(2, 40, 95),
            'source' => 'manuel',
        ];
    }
}
