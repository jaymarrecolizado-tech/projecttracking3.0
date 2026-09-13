<?php

namespace Tests\Feature;

use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan.md gap #2 — queue:check exposes the stuck-queue signal (same one as
 * the Reports-page banner) to cron and external monitors.
 */
class QueueCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_no_pending_work(): void
    {
        $this->artisan('queue:check')->assertSuccessful();
    }

    public function test_fails_when_pending_work_is_stuck(): void
    {
        $user = User::factory()->create();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'type' => 'province',
            'params' => ['province' => 'Cagayan'],
            'download_name' => 'stuck.pdf',
        ]);
        $export->created_at = now()->subMinutes(30);
        $export->save();

        $this->artisan('queue:check')->assertFailed();
        // Fresh work is not stuck yet.
        $this->artisan('queue:check', ['--minutes' => 60])->assertSuccessful();
    }
}
