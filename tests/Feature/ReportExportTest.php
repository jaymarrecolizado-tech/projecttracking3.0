<?php

namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\ReportingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        return $admin;
    }

    private function project(): Project
    {
        return Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
        ]);
    }

    public function test_project_report_request_queues_export_then_job_completes_it(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $project = $this->project();

        $this->actingAs($admin)
            ->post(route('reports.project', $project))
            ->assertRedirect()
            ->assertSessionHas('success');

        $export = ReportExport::where('user_id', $admin->id)->where('type', 'project')->sole();
        // Tests run on QUEUE_CONNECTION=sync, so the job finishes inside the request.
        $this->assertSame('DONE', $export->status);
        $this->assertSame($project->id, $export->params['project_id']);
        Storage::disk('local')->assertExists($export->filename);

        // Owner can download the finished PDF.
        $response = $this->actingAs($admin)->get(route('reports.download', $export));
        $response->assertOk();
        $this->assertStringContainsString('%PDF', substr($response->streamedContent(), 0, 8));
    }

    public function test_province_report_generates_with_optional_project_filter(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('reports.province'), ['province' => 'Pangasinan'])
            ->assertRedirect();

        $export = ReportExport::where('type', 'province')->sole();
        $this->assertSame('Pangasinan', $export->params['province']);
        $this->assertSame('DONE', $export->fresh()->status);
    }

    public function test_users_cannot_download_other_users_exports(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $stranger = $this->admin(); // admin has reports.export but not ownership — owner-only unless export permission

        $export = ReportExport::create([
            'user_id' => $owner->id,
            'type' => 'province',
            'params' => ['province' => 'Cebu'],
            'download_name' => 'province-cebu-summary.pdf',
            'filename' => 'reports/test-export.pdf',
            'status' => 'DONE',
        ]);
        Storage::disk('local')->put('reports/test-export.pdf', '%PDF-1.4 test');

        // A user with neither ownership nor reports.export is blocked.
        $plain = User::factory()->create();
        $this->actingAs($plain)
            ->get(route('reports.download', $export))
            ->assertForbidden();
    }

    private function viewer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(DB::table('roles')->where('name', 'viewer')->value('id'));

        return $viewer;
    }

    /**
     * The reports console is gated: reports.view opens the page, reports.export
     * is required to queue a PDF (it costs CPU and storage).
     */
    public function test_viewer_can_open_reports_but_cannot_queue_pdfs(): void
    {
        $viewer = $this->viewer();
        $project = $this->project();

        $this->actingAs($viewer)->get(route('reports.index'))->assertOk();

        $this->actingAs($viewer)->post(route('reports.project', $project))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.province'), ['province' => 'Cagayan'])->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.site-type'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.barangay-coverage'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.ops-period'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.fleet'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.incidents'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.progress'))->assertForbidden();

        $this->assertSame(0, ReportExport::count());
    }

    public function test_export_permission_holder_can_queue_a_report(): void
    {
        $admin = $this->admin();
        $project = $this->project();

        $this->actingAs($admin)
            ->post(route('reports.project', $project))
            ->assertRedirect();

        $this->assertSame(1, ReportExport::where('type', 'project')->count());
    }

    public function test_builder_queues_combined_pack_with_sections(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('reports.combined'), [
            'province' => 'Cagayan', 'sections' => ['ops_period', 'fleet'],
        ])->assertRedirect()->assertSessionHas('success');

        $export = ReportExport::latest()->first();
        $this->assertSame('combined', $export->type);
        $this->assertSame(['ops_period', 'fleet'], $export->params['sections']);
        $this->assertSame('Cagayan', $export->params['filters']['province']);
    }

    public function test_builder_requires_at_least_one_valid_section(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('reports.combined'), ['province' => 'Cagayan'])
            ->assertSessionHasErrors('sections');
        $this->actingAs($admin)->post(route('reports.combined'), ['sections' => ['nope']])
            ->assertSessionHasErrors('sections.0');

        $this->assertSame(0, ReportExport::where('type', 'combined')->count());
    }

    public function test_done_export_has_csv_companion(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $site = Site::create([
            'project_id' => $project->id, 'location_name' => 'Csv Site',
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
        ]);
        SiteDailyStatus::create([
            'site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'UP',
            'entry_status' => 'DRAFT', 'created_by' => $admin->id,
        ]);
        $export = ReportExport::create([
            'user_id' => $admin->id, 'type' => 'project',
            'params' => ['project_id' => $project->id],
            'download_name' => 'project-summary.pdf', 'status' => 'DONE',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.csv', $export));
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Location,Municipality', $content);
        $this->assertStringContainsString('Csv Site', $content);
        $this->assertStringContainsString('UP', $content);
    }

    public function test_csv_neutralizes_formula_injection(): void
    {
        $admin = $this->admin();
        $project = $this->project();
        $site = Site::create([
            'project_id' => $project->id, 'location_name' => '=HYPERLINK("http://evil.example","x")',
            'province' => 'Cagayan', 'municipality' => 'Aparri',
            'latitude' => 18.3, 'longitude' => 121.6, 'status' => 'active',
        ]);
        SiteDailyStatus::create([
            'site_id' => $site->id, 'date' => today()->toDateString(), 'status' => 'UP',
            'entry_status' => 'DRAFT', 'created_by' => $admin->id,
        ]);
        $export = ReportExport::create([
            'user_id' => $admin->id, 'type' => 'project',
            'params' => ['project_id' => $project->id],
            'download_name' => 'project-summary.pdf', 'status' => 'DONE',
        ]);

        $content = $this->actingAs($admin)->get(route('reports.csv', $export))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(',=HYPERLINK', $content);
    }

    public function test_csv_is_forbidden_to_strangers_and_missing_for_combined(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        $export = ReportExport::create([
            'user_id' => $owner->id, 'type' => 'project',
            'params' => ['project_id' => 1],
            'download_name' => 'x.pdf', 'status' => 'DONE',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('reports.csv', $export))
            ->assertForbidden();

        $pack = ReportExport::create([
            'user_id' => $admin->id, 'type' => 'combined',
            'params' => ['filters' => [], 'sections' => ['fleet']],
            'download_name' => 'pack.pdf', 'status' => 'DONE',
        ]);
        $this->actingAs($admin)->get(route('reports.csv', $pack))->assertStatus(422);
    }

    public function test_stale_queue_notice_fires_only_for_old_pending_exports(): void
    {
        $admin = $this->admin();
        $this->assertNull(ReportExport::staleQueueMessage());

        $export = ReportExport::create([
            'user_id' => $admin->id, 'type' => 'project',
            'params' => ['project_id' => 1],
            'download_name' => 'x.pdf', 'status' => 'PENDING',
        ]);
        $this->assertNull(ReportExport::staleQueueMessage());

        $export->created_at = now()->subMinutes(10);
        $export->save();
        $this->assertStringContainsString('10 minutes', ReportExport::staleQueueMessage());
    }

    public function test_failed_generation_is_recorded_on_the_export(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $export = ReportExport::create([
            'user_id' => $admin->id,
            'type' => 'bogus-type',
            'params' => [],
            'status' => 'PENDING',
        ]);

        (new GenerateReport($export))->handle(app(ReportingService::class));

        $export->refresh();
        $this->assertSame('FAILED', $export->status);
        $this->assertStringContainsString('Unknown report type', $export->error);
    }
}
