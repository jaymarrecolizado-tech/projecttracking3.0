<?php

namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\ReportingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_project_report_request_queues_export_then_job_completes_it(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $project = Project::create([
            'code' => 'FREEWIFI',
            'name' => 'Free WiFi for All',
            'report_type' => 'freewifi',
            'marker_color' => '#0ea5e9',
            'marker_shape' => 'circle',
            'marker_icon' => 'wifi',
        ]);

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
