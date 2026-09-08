<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('??????????')),
            'name' => fake()->company().' Free WiFi',
            'report_type' => 'freewifi',
            'marker_color' => fake()->hexColor(),
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
