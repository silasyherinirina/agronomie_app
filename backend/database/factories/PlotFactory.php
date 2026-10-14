<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

/**
 * @extends Factory<Plot>
 */
class PlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Petit rectangle synthétique (données de test, non réelles) autour d'un point aléatoire
        // dans une zone plausible de Madagascar, pour ne jamais dépendre de coordonnées inventées
        // comme s'il s'agissait de données de terrain.
        $lng = fake()->randomFloat(4, 46, 48);
        $lat = fake()->randomFloat(4, -20, -18);
        $delta = 0.01;

        return [
            'cooperative_id' => Cooperative::factory(),
            'name' => 'Parcelle '.fake()->unique()->bothify('??-###'),
            'geom' => Polygon::fromArray([
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lng, $lat],
                    [$lng + $delta, $lat],
                    [$lng + $delta, $lat + $delta],
                    [$lng, $lat + $delta],
                    [$lng, $lat],
                ]],
            ]),
            'status' => 'active',
        ];
    }
}
