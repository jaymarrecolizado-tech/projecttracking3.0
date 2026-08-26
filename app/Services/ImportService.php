<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\FreewifiImportBatch;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportService
{
    public function __construct(private DeviceDeploymentService $deployments) {}

    public function beginImport(string $filename, int $userId, string $type = 'sites'): FreewifiImportBatch
    {
        return FreewifiImportBatch::create([
            'filename' => $filename,
            'type' => $type,
            'imported_by' => $userId,
            'job_status' => 'PENDING',
        ]);
    }

    /** Bulk device onboarding — see docs/FREEWIFI_MONITORING_PLAN.md §3.2. */
    public function processDeviceImport(FreewifiImportBatch $batch, string $filePath, ?int $actorId = null): void
    {
        $batch->update(['job_status' => 'PROCESSING', 'started_at' => now()]);
        try {
            $rows = Excel::toArray([], $filePath);
            $data = $rows[0] ?? [];
            if (empty($data)) {
                $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => 'No data found in file']]]);

                return;
            }
            $headers = array_map(fn ($h) => strtoupper(trim((string) $h)), $data[0]);
            $bodyRows = array_slice($data, 1);
            $success = 0;
            $failed = 0;
            $errors = [];
            foreach ($bodyRows as $rowIndex => $row) {
                try {
                    if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                        continue;
                    } // skip blank rows
                    $this->upsertDevice(array_combine($headers, $row), $actorId);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = ['row' => $rowIndex + 2, 'message' => $e->getMessage()];
                }
            }
            $batch->update([
                'job_status' => 'DONE',
                'rows_total' => count($bodyRows),
                'rows_success' => $success,
                'rows_failed' => $failed,
                'error_log' => $errors,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => $e->getMessage()]]]);
        }
    }

    /**
     * Expected headers: MODEL MANUFACTURER | MODEL NUMBER | MODEL NAME? | SERIAL NUMBER |
     * MAC ADDRESS? | ASSET TAG? (auto FW-#### when blank) | SITE CODE? | CONDITION? |
     * FIRMWARE VERSION? | WARRANTY UNTIL? | SUPPLIER?
     */
    protected function upsertDevice(array $data, ?int $actorId = null): void
    {
        $manufacturer = trim($data['MODEL MANUFACTURER'] ?? '');
        $modelNumber = trim($data['MODEL NUMBER'] ?? '');
        $serialNumber = trim($data['SERIAL NUMBER'] ?? '');
        if ($manufacturer === '' || $modelNumber === '' || $serialNumber === '') {
            throw new \InvalidArgumentException('MODEL MANUFACTURER, MODEL NUMBER and SERIAL NUMBER are required.');
        }

        // One row = one atomic unit: catalog + device + deployment commit together or not at all.
        DB::transaction(function () use ($data, $actorId, $manufacturer, $modelNumber, $serialNumber) {
            $model = DeviceModel::firstOrCreate(
                ['manufacturer' => $manufacturer, 'model_number' => $modelNumber],
                [
                    'model_name' => trim($data['MODEL NAME'] ?? '') ?: $modelNumber,
                    'type' => in_array(strtolower(trim($data['TYPE'] ?? '')), self::DEVICE_TYPES)
                        ? strtolower(trim($data['TYPE'])) : 'other',
                    'is_active' => true,
                ]
            );

            $macAddress = trim($data['MAC ADDRESS'] ?? '');
            $macAddress = $macAddress !== '' && filter_var($macAddress, FILTER_VALIDATE_MAC) ? $macAddress : null;
            $attributes = [
                'device_model_id' => $model->id,
                'firmware_version' => trim($data['FIRMWARE VERSION'] ?? '') ?: null,
                'condition' => in_array(strtolower(trim($data['CONDITION'] ?? '')), ['new', 'good', 'degraded', 'faulty'])
                    ? strtolower(trim($data['CONDITION'])) : 'new',
                'supplier' => trim($data['SUPPLIER'] ?? '') ?: null,
                'warranty_until' => $this->parseDateCell($data['WARRANTY UNTIL'] ?? ''),
            ];

            $device = Device::withTrashed()->where('serial_number', $serialNumber)->first();
            if ($device) {
                // Existing unit: the sheet fills blanks but never overwrites live identity/status.
                if ($device->trashed()) {
                    $device->restore();
                }
                $device->update(array_filter($attributes, fn ($v) => $v !== null));
                if ($device->mac_address === null && $macAddress !== null) {
                    $device->update(['mac_address' => $macAddress]);
                }
            } else {
                // New unit: auto asset tag (FW-####) when the sheet leaves it blank.
                $device = Device::create($attributes + [
                    'serial_number' => $serialNumber,
                    'asset_tag' => trim($data['ASSET TAG'] ?? '') ?: $this->nextAssetTag(),
                    'mac_address' => $macAddress,
                    'status' => 'in_stock',
                ]);
            }

            // Optional immediate assignment via site code
            $siteCode = trim($data['SITE CODE'] ?? '');
            if ($siteCode !== '') {
                $site = Site::where('ap_site_code', $siteCode)->first();
                if (! $site) {
                    throw new \InvalidArgumentException("Site code '{$siteCode}' not found.");
                }
                $this->deployments->open(
                    $device,
                    ['site_id' => $site->id, 'role_at_site' => 'primary_ap', 'installed_at' => now()],
                    $actorId,
                );
                $device->update(['status' => 'deployed']);
            }
        });
    }

    protected function nextAssetTag(): string
    {
        // SUBSTRING/UNSIGNED on MySQL & friends, SUBSTR/INTEGER elsewhere (SQLite).
        $isMysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $expression = $isMysql
            ? DB::raw('MAX(CAST(SUBSTRING(asset_tag, 4) AS UNSIGNED))')
            : DB::raw('MAX(CAST(SUBSTR(asset_tag, 4) AS INTEGER))');
        $max = Device::withTrashed()
            ->where('asset_tag', 'like', 'FW-%')
            ->value($expression);

        return 'FW-'.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function parseDateCell(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    public const DEVICE_TYPES = ['outdoor_ap', 'router', 'switch', 'cpe', 'solar_panel', 'charge_controller', 'battery', 'ups', 'poe_injector', 'antenna', 'camera', 'other'];

    public function processImport(FreewifiImportBatch $batch, string $filePath): void
    {
        $batch->update(['job_status' => 'PROCESSING', 'started_at' => now()]);
        try {
            $rows = Excel::toArray([], $filePath);
            $data = $rows[0] ?? [];
            if (empty($data)) {
                $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => 'No data found in file']]]);

                return;
            }
            $headers = array_map('trim', $data[0]);
            $rows = array_slice($data, 1);
            $success = 0;
            $failed = 0;
            $errors = [];
            $freewifi = Project::where('code', 'FREEWIFI')->first();
            if (! $freewifi) {
                $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => 'FREEWIFI project not found']]]);

                return;
            }
            $dateColumns = $this->parseDateColumns($headers);
            foreach ($rows as $rowIndex => $row) {
                try {
                    $rowData = array_combine($headers, $row);
                    $site = DB::transaction(function () use ($rowData, $freewifi, $dateColumns) {
                        $site = $this->upsertSite($rowData, $freewifi->id);
                        if ($site && $dateColumns) {
                            $this->upsertDailyStatuses($site->id, $rowData, $dateColumns);
                        }

                        return $site;
                    });
                    if ($site) {
                        $success++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowIndex + 2,
                        'message' => $e->getMessage(),
                    ];
                }
            }
            $batch->update([
                'job_status' => 'DONE',
                'rows_total' => count($rows),
                'rows_success' => $success,
                'rows_failed' => $failed,
                'error_log' => $errors,
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => $e->getMessage()]]]);
        }
    }

    protected function upsertSite(array $data, int $projectId): ?Site
    {
        $rules = [
            'LOCATION NAME' => 'required|string',
            'LATITUDE' => 'required|numeric|between:-90,90',
            'LONGITUDE' => 'required|numeric|between:-180,180',
        ];
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return null;
        }

        return Site::updateOrCreate(
            ['project_id' => $projectId, 'ap_site_code' => $data['AP SITE CODE'] ?? null],
            [
                'nationwide_id' => $data['NATIONWIDE_ID'] ?? null,
                'location_name' => $data['LOCATION NAME'],
                'ap_site_name' => $data['AP SITE NAME'] ?? null,
                'site_type' => $data['Site Type'] ?? null,
                'barangay' => $data['Barangay'] ?? null,
                'municipality' => $data['Municipality'] ?? null,
                'province' => $data['Province'] ?? null,
                'district' => $data['District'] ?? null,
                'region' => $data['Region'] ?? null,
                'island_group' => in_array($data['Island Group'] ?? '', ['Luzon', 'Visayas', 'Mindanao']) ? $data['Island Group'] : null,
                'latitude' => (float) $data['LATITUDE'],
                'longitude' => (float) $data['LONGITUDE'],
                'status' => $this->mapStatus($data['STATUS (overall)'] ?? 'planned'),
                'isp_provider' => $data['ISP/Provider'] ?? null,
                'last_mile_tech' => $data['Last Mile Technology'] ?? null,
                'bw_download_cir' => isset($data['BW Download (CIR)']) ? (float) preg_replace('/[^0-9.]/', '', $data['BW Download (CIR)']) : null,
            ]
        );
    }

    protected function upsertDailyStatuses(int $siteId, array $data, array $dateColumns): void
    {
        foreach ($dateColumns as $col) {
            $date = $col['date'];
            $statusValue = $data[$col['header']] ?? '';
            $status = match (strtoupper(trim($statusValue))) {
                'UP' => 'UP',
                'DOWN' => 'DOWN',
                default => 'NO_DATA',
            };
            $bwCol = str_replace('Status', 'BW', $col['header']);
            $usersCol = str_replace('Status', 'Users', $col['header']);
            SiteDailyStatus::updateOrCreate(
                ['site_id' => $siteId, 'date' => $date],
                [
                    'status' => $status,
                    'bandwidth_utilization_mbps' => isset($data[$bwCol]) ? (float) $data[$bwCol] : null,
                    'total_unique_users' => isset($data[$usersCol]) ? (int) $data[$usersCol] : null,
                ]
            );
        }
    }

    protected function parseDateColumns(array $headers): array
    {
        $dateCols = [];
        foreach ($headers as $header) {
            $trimmed = trim($header);
            if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d+$/i', $trimmed)) {
                $date = Carbon::parse($trimmed.' '.date('Y'))->format('Y-m-d');
                $dateCols[] = ['header' => $trimmed, 'date' => $date];
            }
        }

        return $dateCols;
    }

    protected function mapStatus(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'ACTIVE' => 'active',
            'DOWN', 'INACTIVE' => 'inactive',
            'DECOMMISSIONED' => 'decommissioned',
            'MAINTENANCE' => 'maintenance',
            default => 'planned',
        };
    }
}
