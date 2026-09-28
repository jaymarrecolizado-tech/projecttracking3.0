<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSurveyResponse>
 */
class SiteSurveyResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'survey_id' => SiteSurvey::factory(),
            'ap_site_code' => 'F-'.strtoupper(fake()->lexify('????????')),
            'cms_provider' => fake()->randomElement(['DICT', 'PHILCOMSAT', 'Converge ICT Solutions, Inc.']),
            'last_mile_tech' => fake()->randomElement(['FIBER', 'LEO', 'RADIO', 'VSAT']),
            'ratings' => [
                'overall' => fake()->numberBetween(1, 5),
                'speed' => fake()->numberBetween(1, 5),
                'reliability' => fake()->numberBetween(1, 5),
            ],
            'comments' => null,
            'ip_hash' => hash('sha256', fake()->ipv4().config('app.key')),
            'user_agent' => 'Mozilla/5.0 (Test)',
            'submitted_at' => now(),
        ];
    }
}
