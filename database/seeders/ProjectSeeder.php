<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        // Free WiFi-only platform: single program (see docs/FREEWIFI_MONITORING_PLAN.md, P0)
        DB::table('projects')->insert([
            ['code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi', 'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'logo_filename' => 'freewifi.png', 'description' => 'Free WiFi for All / Broadband ng Masa program', 'is_active' => true],
        ]);
    }
}
