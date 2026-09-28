<?php

namespace Database\Seeders;

use App\Models\SiteSurvey;
use Illuminate\Database\Seeder;

/**
 * The v1 questionnaire. Deliberately short — 4 questions, well under the
 * 60-second budget in Plan.md S4, because completion rate is what makes a
 * per-site average meaningful in the first place.
 *
 * Idempotent: re-seeding updates the existing `v1` row rather than creating a
 * second active survey (two active surveys would double-count a site).
 */
class SiteSurveySeeder extends Seeder
{
    public function run(): void
    {
        SiteSurvey::updateOrCreate(
            ['code' => 'v1'],
            [
                'title' => 'Free WiFi Experience Survey',
                'is_active' => true,
                'questions' => [
                    [
                        'key' => 'overall',
                        'label' => 'Overall, how was the connection?',
                        'type' => 'rating',
                        'required' => true,
                    ],
                    [
                        'key' => 'speed',
                        'label' => 'How was the speed?',
                        'type' => 'rating',
                        'required' => true,
                    ],
                    [
                        'key' => 'reliability',
                        'label' => 'Did it stay connected while you used it?',
                        'type' => 'rating',
                        'required' => true,
                    ],
                    [
                        'key' => 'comments',
                        'label' => 'Anything else you would like to tell us? (optional)',
                        'type' => 'text',
                        'required' => false,
                    ],
                ],
            ],
        );
    }
}
