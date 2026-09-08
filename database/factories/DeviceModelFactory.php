<?php

namespace Database\Factories;

use App\Models\DeviceModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceModel>
 */
class DeviceModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'manufacturer' => fake()->company(),
            'model_name' => fake()->word().' AP',
            'model_number' => strtoupper(fake()->bothify('??-###')),
            'type' => 'outdoor_ap',
            'is_active' => true,
        ];
    }
}
