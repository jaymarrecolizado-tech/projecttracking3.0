<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DownAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function downSite(): Site
    {
        $this->seed(RolePermissionSeeder::class);
        $project = Project::create([
            'code' => 'FREEWIFI', 'name' => 'Free WiFi for All', 'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9', 'marker_shape' => 'circle', 'marker_icon' => 'wifi', 'is_active' => true,
        ]);
        $site = Site::create([
            'project_id' => $project->id,
            'location_name' => 'Down Site',
            'ap_site_code' => 'AP-DA-1',
            'municipality' => 'Tuguegarao City',
            'province' => 'Cagayan',
            'latitude' => 17.6,
            'longitude' => 121.7,
            'status' => 'active',
        ]);
        SiteDailyStatus::create(['site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'DOWN']);

        return $site;
    }

    public function test_down_episode_notifies_email_and_telegram_then_dedupes(): void
    {
        // Mail::raw() is not recorded by Mail::fake(), so mock the facade.
        Mail::shouldReceive('raw')->once()->andReturnNull();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config()->set('monitoring.telegram.bot_token', 'test-token');
        config()->set('monitoring.telegram.chat_id', '12345');

        $site = $this->downSite();

        $approver = User::factory()->create();
        $approver->roles()->attach(1); // admin holds daily.approve

        $this->artisan('alerts:down')->expectsOutputToContain('email + Telegram');

        Http::assertSentCount(1);
        $this->assertNotNull($site->fresh()->last_alerted_at);

        // Second run in the same DOWN episode: deduped, nothing sent.
        Mail::shouldReceive('raw')->never();
        $this->artisan('alerts:down')->expectsOutputToContain('No new DOWN episodes');
        Http::assertSentCount(1);
    }

    public function test_telegram_channel_skipped_when_unconfigured(): void
    {
        Mail::shouldReceive('raw')->once()->andReturnNull();
        Http::fake();
        config()->set('monitoring.telegram.bot_token', null);
        config()->set('monitoring.telegram.chat_id', null);

        $this->downSite();
        $approver = User::factory()->create();
        $approver->roles()->attach(1);

        $this->artisan('alerts:down')->expectsOutputToContain('via email.');

        Http::assertNothingSent();
    }
}
