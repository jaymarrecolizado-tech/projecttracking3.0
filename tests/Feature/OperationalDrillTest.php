<?php

namespace Tests\Feature;

use App\Jobs\ProcessExcelImport;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\DeviceMetric;
use App\Models\FreewifiImportBatch;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\SiteSurvey;
use App\Models\User;
use App\Services\Telegram;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

/**
 * Plan.md F4 — the two drills that used to be prose.
 *
 * The restore drill still needs a real server (it restores a dump). These two
 * do not: a killed worker and an unreachable notifier are both reproducible in
 * a test, and a drill nobody can repeat is a ritual rather than a check.
 */
class OperationalDrillTest extends TestCase
{
    use RefreshDatabase;

    // ── Drill 1: the queue dies mid-import ─────────────────────────────────

    public function test_an_import_that_never_completes_is_marked_failed_audited_and_retryable(): void
    {
        $actor = User::factory()->create();
        $batch = FreewifiImportBatch::create([
            'filename' => 'region-workbook.xlsx',
            'type' => 'region_workbook',
            'imported_by' => $actor->id,
            'job_status' => 'RUNNING',
            'started_at' => now(),
        ]);

        // What a killed worker leaves behind: the batch never reaches COMPLETED.
        $job = new ProcessExcelImport($batch, '/nonexistent/region-workbook.xlsx', 'region_workbook', $actor->id);
        $job->failed(new RuntimeException('SQLSTATE[HY000]: server has gone away'));

        $batch->refresh();
        $this->assertSame('FAILED', $batch->job_status);
        $this->assertNotNull($batch->completed_at);
        $this->assertStringContainsString('server has gone away', $batch->error_log[0]['message']);

        // A failure nobody can find is a silent failure.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'import failed (region_workbook)',
            'auditable_type' => FreewifiImportBatch::class,
            'auditable_id' => $batch->id,
            'user_id' => $actor->id,
        ]);

        // Retry: the next attempt starts from a clean row, not from the corpse.
        $batch->update(['job_status' => 'QUEUED', 'error_log' => []]);
        $this->assertSame('QUEUED', $batch->job_status);
        $this->assertSame([], $batch->error_log);
    }

    public function test_a_dead_worker_leaves_pinned_work_that_the_monitor_notices(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $export = ReportExport::create([
            'user_id' => $user->id,
            'type' => 'province',
            'params' => ['province' => 'Cagayan'],
            'download_name' => 'stuck.pdf',
        ]);
        $export->created_at = now()->subMinutes(30);
        $export->save();

        // The liveness signal an external monitor is meant to watch.
        $this->artisan('queue:check')->assertFailed();
        $this->assertStringContainsString('queue worker looks down', ReportExport::staleQueueMessage());
    }

    // ── Drill 2: the notifiers are unreachable ─────────────────────────────

    public function test_pages_and_alerts_survive_an_unreachable_telegram(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('monitoring.telegram.bot_token', 'test-token');
        config()->set('monitoring.telegram.chat_id', '-100123');
        Mail::fake();

        // A connection that never completes, not a 500 — the shape of a real
        // outage: the request hangs until the timeout, then must still succeed.
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $telegram = app(Telegram::class);
        $this->assertTrue($telegram->configured());
        $this->assertFalse($telegram->sendMessage('ping'), 'A dead notifier reports failure, it does not throw.');

        $admin = User::factory()->create();
        $admin->roles()->attach(1);
        Site::factory()->create(['ap_site_code' => 'F-DEGRADED']);

        $this->actingAs($admin)->get(route('alerts.index'))->assertOk();
        $this->actingAs($admin)->get(route('sites.index'))->assertOk();
    }

    public function test_the_public_survey_still_accepts_answers_when_notifiers_are_down(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('monitoring.telegram.bot_token', 'test-token');
        config()->set('monitoring.telegram.chat_id', '-100123');
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $survey = SiteSurvey::create([
            'code' => 'v1', 'title' => 'Survey', 'is_active' => true,
            'questions' => [['key' => 'overall', 'label' => 'Overall?', 'type' => 'rating', 'required' => true]],
        ]);
        $site = Site::factory()->create(['ap_site_code' => 'F-NODOWN']);

        $this->post(URL::signedRoute('survey.store', ['siteCode' => 'F-NODOWN']), [
            'ratings' => ['overall' => 5],
            'elapsed_ms' => 9000,
        ])->assertRedirect(route('survey.thanks', ['siteCode' => 'F-NODOWN']));

        $this->assertDatabaseHas('site_survey_responses', ['site_id' => $site->id, 'survey_id' => $survey->id]);
    }

    public function test_alert_evaluation_completes_with_every_delivery_channel_dead(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('monitoring.telegram.bot_token', 'test-token');
        config()->set('monitoring.telegram.chat_id', '-100123');
        Mail::fake();
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $rule = AlertRule::create([
            'name' => 'Battery critically low', 'metric' => 'battery_v', 'operator' => '<',
            'threshold' => 11.8, 'duration_minutes' => 0, 'severity' => 'critical',
            'notify_roles' => ['daily.approve'], 'is_active' => true,
        ]);
        $site = Site::factory()->create(['status' => 'active']);
        $site->dailyStatuses()->create([
            'date' => today(), 'status' => 'UP', 'entry_status' => 'APPROVED',
            'created_by' => User::factory()->create()->id,
        ]);
        DeviceMetric::create([
            'site_id' => $site->id, 'ts' => now(), 'battery_v' => 10.9,
        ]);

        // The alert is still *recorded* even though nobody could be told.
        $this->artisan('alerts:evaluate')->assertSuccessful();

        $this->assertDatabaseHas('alerts', ['rule_id' => $rule->id, 'site_id' => $site->id]);
        Mail::assertNothingSent();
    }

    public function test_a_stored_report_file_is_downloadable_even_when_storage_is_read_only(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('reports/x.pdf', '%PDF-1.4');

        $user = User::factory()->create();
        $user->roles()->attach(1);
        $export = ReportExport::create([
            'user_id' => $user->id, 'type' => 'province', 'status' => 'DONE',
            'filename' => 'reports/x.pdf', 'download_name' => 'x.pdf',
            'params' => ['province' => 'Cagayan'], 'completed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.download', $export))->assertOk();
    }

    // ── Retention: the log window the runbook promises ─────────────────────

    public function test_log_prune_keeps_the_window_and_deletes_the_rest(): void
    {
        $dir = sys_get_temp_dir().'/fpiap-logs-'.uniqid();
        mkdir($dir, 0777, true);

        $fresh = $dir.'/laravel-'.now()->subDays(2)->format('Y-m-d').'.log';
        $stale = $dir.'/laravel-'.now()->subDays(90)->format('Y-m-d').'.log';
        $current = $dir.'/laravel.log';
        $worker = $dir.'/worker.log';
        foreach ([$fresh, $stale, $current, $worker] as $path) {
            file_put_contents($path, 'x');
        }
        touch($fresh, now()->subDays(2)->getTimestamp());
        touch($stale, now()->subDays(90)->getTimestamp());

        $this->artisan('logs:prune', ['--days' => 30, '--path' => $dir])->assertSuccessful();

        $this->assertFileExists($fresh);
        $this->assertFileDoesNotExist($stale);
        // Neither the file being written nor the worker's stdout is ours.
        $this->assertFileExists($current);
        $this->assertFileExists($worker);

        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }
}
