<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\FreewifiImportBatch;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\ImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class RegionWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    /** Mirrors the real REGION II workbook: roster sheet + month sheet with serial-date triplets. */
    private function fixtureWorkbook(): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $roster = $book->createSheet();
        $roster->setTitle('NEW SITES (WIT)');
        $roster->fromArray([
            'PROJECT', 'NATIONWIDE_ID', 'AP SITE CODE', 'LOCATION NAME', 'AP SITE NAME',
            'AP MAC ADDRESS', 'BARANGAY', 'Locality', 'Province', 'LATITUDE', 'LONGITUDE', 'AP BRAND',
        ], null, 'A1');
        $roster->fromArray([
            'PICS GIDA via LEO', 77651, 'GIDA-R2-001A', 'Alcala West Central School', 'Alcala West Central School',
            'DC:62:79:97:59:34', 'Afusing Daga', 'Alcala', 'Cagayan', 17.858673, 121.617819, 'Ubiquiti',
        ], null, 'A2');

        $month = $book->createSheet();
        $month->setTitle('JANUARY');
        $serial = ExcelDate::PHPToExcel('2026-01-08');
        $month->fromArray([
            'PROJECT', 'STATUS', 'AP SITE CODE', 'LOCATION NAME', 'Locality', 'Province',
            'LATITUDE', 'LONGITUDE', 'Total Unique Users', $serial, 'Bandwidth Utilization/Remarks', 'Total Users',
            $serial + 1, 'Bandwidth Utilization/Remarks', 'Total Users',
        ], null, 'A1');
        // Day 1: UP with telemetry; Day 2: NO NMS (management plane silent).
        $month->fromArray([
            'PICS GIDA via LEO', 'ONGOING', 'GIDA-R2-001A', 'Alcala West Central School', 'Alcala', 'Cagayan',
            17.858673, 121.617819, 488, 'UP', 3.06, 17, 'NO NMS', null, null,
        ], null, 'A2');

        $path = storage_path('app/testing-region-workbook-'.uniqid().'.xlsx');
        (new XlsxWriter($book))->save($path);

        return $path;
    }

    public function test_region_workbook_creates_sites_devices_and_day_statuses(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $path = $this->fixtureWorkbook();
        $batch = FreewifiImportBatch::create([
            'filename' => 'region.xlsx', 'type' => 'region_workbook', 'imported_by' => $admin->id,
        ]);
        app(ImportService::class)->processRegionWorkbook($batch, $path, $admin->id);
        unlink($path);

        $this->assertSame('DONE', $batch->fresh()->job_status);

        // Site created from the roster sheet with mapped fields.
        $site = Site::where('ap_site_code', 'GIDA-R2-001A')->first();
        $this->assertNotNull($site);
        $this->assertSame('Alcala', $site->municipality);
        $this->assertSame('Cagayan', $site->province);
        $this->assertSame('active', $site->status);

        // Project auto-created from the sheet's PROJECT column.
        $this->assertNotNull(Project::where('name', 'PICS GIDA via LEO')->first());

        // MAC-identified AP deployed at the site.
        $device = Device::where('mac_address', 'DC:62:79:97:59:34')->first();
        $this->assertNotNull($device);
        $this->assertSame('deployed', $device->status);
        $this->assertNotNull(DeviceDeployment::where('device_id', $device->id)->where('site_id', $site->id)->first());

        // Telemetry: UP day carries bandwidth/users; NO NMS day recorded as its own status.
        $up = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', '2026-01-08')->first();
        $this->assertSame('UP', $up->status);
        $this->assertEquals('3.06', $up->bandwidth_utilization_mbps);
        $this->assertSame(17, (int) $up->total_unique_users);

        $noNms = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', '2026-01-09')->first();
        $this->assertSame('NO_NMS', $noNms->status);
        $this->assertNull($noNms->bandwidth_utilization_mbps);

        // Lifecycle column mapped onto the site.
        $this->assertSame('ONGOING', $site->lifecycle_status);
    }

    public function test_reimport_is_idempotent_and_fills_blanks(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(1);

        $path = $this->fixtureWorkbook();
        $service = app(ImportService::class);
        $batch1 = FreewifiImportBatch::create(['filename' => 'a.xlsx', 'type' => 'region_workbook', 'imported_by' => $admin->id]);
        $service->processRegionWorkbook($batch1, $path, $admin->id);
        $batch2 = FreewifiImportBatch::create(['filename' => 'b.xlsx', 'type' => 'region_workbook', 'imported_by' => $admin->id]);
        $service->processRegionWorkbook($batch2, $path, $admin->id);
        unlink($path);

        $this->assertSame('DONE', $batch2->fresh()->job_status);
        $this->assertSame(1, Site::count());
        $this->assertSame(1, Device::count());
        $this->assertSame(2, SiteDailyStatus::count());
    }
}
