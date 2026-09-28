<?php

namespace Database\Factories;

use App\Models\SiteSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSurvey>
 */
class SiteSurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'v'.fake()->unique()->numberBetween(1, 9999),
            'title' => 'Free WiFi Experience Survey',
            'questions' => [
                ['key' => 'overall', 'label' => 'Overall, how was the connection?', 'type' => 'rating', 'required' => true],
                ['key' => 'speed', 'label' => 'How was the speed?', 'type' => 'rating', 'required' => true],
                ['key' => 'reliability', 'label' => 'Did it stay connected?', 'type' => 'rating', 'required' => true],
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
