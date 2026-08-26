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
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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

    /**
     * Real-world REGION II workbook: classifies every sheet and ingests the ones
     * that matter. Roster sheets (NEW SITES*, *District*, POP/PFIAPS/PICS…) create
     * sites + MAC-addressed AP devices; month sheets (JANUARY–AUGUST) carry per-day
     * UP/DOWN/NO NMS triplets keyed by Excel-serial headers. Summary/pivot sheets
     * are skipped. — see Reference/REGION II SITE STATUS 2026.xlsx
     */
    public function processRegionWorkbook(FreewifiImportBatch $batch, string $filePath, ?int $actorId = null): void
    {
        $batch->update(['job_status' => 'PROCESSING', 'started_at' => now()]);

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);

            $sitesTouched = 0;
            $devicesUpserted = 0;
            $statusesUpserted = 0;
            $skipped = [];
            $errors = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $title = $sheet->getTitle();
                $grid = $sheet->toArray(null, false, false, false);
                if (count($grid) < 2) {
                    $skipped[] = ['sheet' => $title, 'reason' => 'empty'];

                    continue;
                }

                $headers = $this->normalizeHeaders($grid[0]);
                $kind = $this->classifySheet($headers, $grid);

                try {
                    match ($kind) {
                        'roster' => $this->importRosterSheet($grid, $headers, $actorId, $sitesTouched, $devicesUpserted),
                        'telemetry' => $this->importTelemetrySheet($grid, $headers, $actorId, $sitesTouched, $statusesUpserted),
                        default => $skipped[] = ['sheet' => $title, 'reason' => 'not a roster/telemetry sheet'],
                    };
                } catch (\Throwable $e) {
                    $errors[] = ['sheet' => $title, 'message' => mb_substr($e->getMessage(), 0, 300)];
                }
            }

            $batch->update([
                'job_status' => $errors && $sitesTouched + $devicesUpserted + $statusesUpserted === 0 ? 'FAILED' : 'DONE',
                'rows_total' => $sitesTouched + $statusesUpserted,
                'rows_success' => $sitesTouched + $devicesUpserted + $statusesUpserted,
                'rows_failed' => count($errors),
                'error_log' => array_merge($errors, array_map(fn ($s) => ['message' => "Skipped sheet '{$s['sheet']}': {$s['reason']}"], $skipped)),
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => $e->getMessage()]]]);
        }
    }

    /** @param array<int, string> $headers */
    protected function classifySheet(array $headers, array $grid): string
    {
        if (! in_array('AP SITE CODE', $headers, true)) {
            return 'other';
        }
        if ($this->serialDateColumns($headers) !== []) {
            return 'telemetry';
        }
        foreach ($headers as $header) {
            if (str_contains($header, 'MAC')) {
                return 'roster';
            }
        }

        return 'other';
    }

    /** Header cells that are raw Excel serial dates — the day columns of month sheets. */
    protected function serialDateColumns(array $headers): array
    {
        $days = [];
        foreach ($headers as $i => $header) {
            if (is_numeric($header) && (float) $header >= 40000) {
                $days[$i] = ExcelDate::excelToDateTimeObject((float) $header)->format('Y-m-d');
            }
        }

        return $days;
    }

    /** @return array<int, string> uppercased, trimmed headers keyed by column index */
    protected function normalizeHeaders(array $row): array
    {
        return array_map(fn ($h) => strtoupper(trim((string) $h)), $row);
    }

    protected function headerIndexes(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $header) {
            if ($header !== '') {
                $map[$header] = $i;
            }
        }

        return $map;
    }

    protected function cell(array $row, array $index, string $key): mixed
    {
        $i = $index[$key] ?? null;

        return $i === null ? null : ($row[$i] ?? null);
    }

    protected function text(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    protected function numericDate(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));
        if ($trimmed === '') {
            return null;
        }
        if (is_numeric($trimmed) && (float) $trimmed >= 40000) {
            return ExcelDate::excelToDateTimeObject((float) $trimmed)->format('Y-m-d');
        }
        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Roster sheets: one row per AP. Creates the site (when coordinates exist),
     * then a MAC-identified AP device deployed at it. Sheets without coordinates
     * (e.g. "AP MAC ADDRESS") only enrich existing sites' equipment.
     */
    protected function importRosterSheet(array $grid, array $headers, ?int $actorId, int &$sitesTouched, int &$devicesUpserted): void
    {
        $index = $this->headerIndexes($headers);
        $hasCoords = isset($index['LATITUDE'], $index['LONGITUDE']);

        foreach (array_slice($grid, 1) as $row) {
            $siteCode = $this->text($this->cell($row, $index, 'AP SITE CODE') ?? $this->cell($row, $index, 'SITE CODE'));
            $mac = $this->text($this->cell($row, $index, 'AP MAC ADDRESS') ?? $this->cell($row, $index, 'MAC ADDRESS'));
            if ($siteCode === null && $mac === null) {
                continue;
            }

            $site = null;
            $siteCode !== null && $site = Site::withTrashed()
                ->where('ap_site_code', $siteCode)
                ->orWhere('ap_site_code', 'like', $siteCode.'%')
                ->orderByRaw('CASE WHEN ap_site_code = ? THEN 0 ELSE 1 END', [$siteCode])
                ->first();

            // Create/refresh the site when the sheet carries coordinates.
            $lat = $hasCoords ? $this->text($this->cell($row, $index, 'LATITUDE')) : null;
            $lng = $hasCoords ? $this->text($this->cell($row, $index, 'LONGITUDE')) : null;
            if (is_numeric($lat) && is_numeric($lng)) {
                $project = $this->resolveProject($this->text($this->cell($row, $index, 'PROJECT') ?? $this->cell($row, $index, 'PROJECT NAME')));
                $attributes = array_filter([
                    'nationwide_id' => $this->text($this->cell($row, $index, 'NATIONWIDE_ID') ?? $this->cell($row, $index, 'NATIONWIDE ID')),
                    'location_name' => $this->text($this->cell($row, $index, 'LOCATION NAME') ?? $this->cell($row, $index, 'SITE LOCATION')),
                    'ap_site_name' => $this->text($this->cell($row, $index, 'AP SITE NAME') ?? $this->cell($row, $index, 'AP LOCATION') ?? $this->cell($row, $index, 'AP NAME')),
                    'site_type' => $this->text($this->cell($row, $index, 'SITE TYPE')),
                    'site_classification' => $this->text($this->cell($row, $index, 'SITE CLASSIFICATION')),
                    'barangay' => $this->text($this->cell($row, $index, 'BARANGAY')),
                    'municipality' => $this->text($this->cell($row, $index, 'LOCALITY') ?? $this->cell($row, $index, 'MUNICIPALITY')),
                    'province' => $this->text($this->cell($row, $index, 'PROVINCE')),
                    'region' => $this->text($this->cell($row, $index, 'REGION')),
                    'island_group' => $this->islandGroup($this->cell($row, $index, 'ISLAND GROUP')),
                    'isp_provider' => $this->text($this->cell($row, $index, 'CMS PROVIDER') ?? $this->cell($row, $index, 'LINK PROVIDER')),
                    'cms_provider' => $this->text($this->cell($row, $index, 'CMS PROVIDER')),
                    'link_provider' => $this->text($this->cell($row, $index, 'LINK PROVIDER')),
                    'source_of_bw' => $this->text($this->cell($row, $index, 'SOURCE OF BW')),
                    'last_mile_tech' => $this->text($this->cell($row, $index, 'LAST MILE TECHNOLOGY')),
                    'bw_download_cir' => is_numeric($this->cell($row, $index, 'BW DOWNLOAD (CIR)')) ? (float) $this->cell($row, $index, 'BW DOWNLOAD (CIR)') : null,
                    'date_of_activation' => $this->numericDate($this->cell($row, $index, 'DATE OF ACTIVATION')),
                    'accepted' => $this->toBool($this->cell($row, $index, 'ACCEPTED?')),
                    'ap_brand' => $this->text($this->cell($row, $index, 'AP BRAND')),
                    'declaration_date' => $this->numericDate($this->cell($row, $index, 'DECLARATION DATE')),
                    'integrated_date' => $this->numericDate($this->cell($row, $index, 'INTEGRATED DATE (MM -') ?? $this->cell($row, $index, 'INTEGRATED DATE')),
                    'school_id' => $this->text($this->cell($row, $index, 'BEIS SCHOOL ID')),
                ], fn ($v) => $v !== null);

                if ($site && $site->trashed()) {
                    $site->restore();
                }
                $site = $site ?: new Site(['project_id' => $project->id, 'ap_site_code' => $siteCode, 'status' => 'active']);
                $site->project_id = $project->id;
                $site->ap_site_code = $site->ap_site_code ?: $siteCode;
                // Fill blanks only — never clobber richer values from other sheets.
                foreach ($attributes as $k => $v) {
                    if ($site->{$k} === null) {
                        $site->setAttribute($k, $v);
                    }
                }
                $site->latitude = (float) $lat;
                $site->longitude = (float) $lng;
                $site->save();
                $sitesTouched++;
            }

            if ($mac !== null && filter_var($mac, FILTER_VALIDATE_MAC)) {
                if ($site && $this->attachApDevice($site, $mac, $this->text($this->cell($row, $index, 'AP BRAND')), $actorId)) {
                    $devicesUpserted++;
                }
            }
        }
    }

    /**
     * Month sheets: metadata block + per-day triplets whose status column header
     * is an Excel serial date, followed by Bandwidth and Total Users columns.
     */
    protected function importTelemetrySheet(array $grid, array $headers, ?int $actorId, int &$sitesTouched, int &$statusesUpserted): void
    {
        $index = $this->headerIndexes($headers);
        $days = $this->serialDateColumns($headers);

        foreach (array_slice($grid, 1) as $row) {
            $siteCode = $this->text($this->cell($row, $index, 'AP SITE CODE'));
            if ($siteCode === null) {
                continue; // blank rows / trailing formula summaries
            }

            $site = Site::withTrashed()->where('ap_site_code', $siteCode)->first();
            if (! $site) {
                $project = $this->resolveProject($this->text($this->cell($row, $index, 'PROJECT')));
                $lat = $this->text($this->cell($row, $index, 'LATITUDE'));
                $lng = $this->text($this->cell($row, $index, 'LONGITUDE'));
                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    continue;
                }
                $site = Site::create(array_filter([
                    'project_id' => $project->id,
                    'ap_site_code' => $siteCode,
                    'nationwide_id' => $this->text($this->cell($row, $index, 'NATIONWIDE_ID')),
                    'location_name' => $this->text($this->cell($row, $index, 'LOCATION NAME')),
                    'ap_site_name' => $this->text($this->cell($row, $index, 'AP SITE NAME')),
                    'site_type' => $this->text($this->cell($row, $index, 'SITE TYPE')),
                    'site_classification' => $this->text($this->cell($row, $index, 'SITE CLASSIFICATION')),
                    'barangay' => $this->text($this->cell($row, $index, 'BARANGAY')),
                    'municipality' => $this->text($this->cell($row, $index, 'LOCALITY')),
                    'province' => $this->text($this->cell($row, $index, 'PROVINCE')),
                    'island_group' => $this->islandGroup($this->cell($row, $index, 'ISLAND GROUP')),
                    'cms_provider' => $this->text($this->cell($row, $index, 'CMS PROVIDER')),
                    'link_provider' => $this->text($this->cell($row, $index, 'LINK PROVIDER')),
                    'last_mile_tech' => $this->text($this->cell($row, $index, 'LAST MILE TECHNOLOGY')),
                    'bw_download_cir' => is_numeric($this->cell($row, $index, 'BW DOWNLOAD (CIR)')) ? (float) $this->cell($row, $index, 'BW DOWNLOAD (CIR)') : null,
                    'date_of_activation' => $this->numericDate($this->cell($row, $index, 'DATE OF ACTIVATION')),
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lng,
                    'status' => 'active',
                ], fn ($v) => $v !== null));
            }
            $sitesTouched++;

            // Lifecycle column (ACTIVE/ONGOING/EXPIRED/REBATES/SUPPORT/REMOVED)
            $lifecycle = $this->text($this->cell($row, $index, 'STATUS'));
            if ($lifecycle !== null) {
                $site->lifecycle_status = strtoupper($lifecycle);
                $site->status = $this->mapLifecycle($lifecycle);
                $site->save();
            }

            // One transaction per AP row: ~30 day-upserts commit together instead of
            // each hitting its own fsync — minutes saved across 1,500-row month sheets.
            DB::transaction(function () use ($site, $days, $row, &$statusesUpserted) {
                foreach ($days as $colIdx => $date) {
                    $raw = strtoupper(trim((string) ($row[$colIdx] ?? '')));
                    if ($raw === '' || str_starts_with($raw, '=')) {
                        continue; // untouched day or stray formula
                    }
                    $status = match ($raw) {
                        'UP' => 'UP',
                        'DOWN' => 'DOWN',
                        'DOWN SERVER' => 'DOWN_SERVER',
                        'NO NMS', 'NONMS', 'NO SERVER' => 'NO_NMS',
                        'NO DATA' => 'NO_DATA',
                        default => null,
                    };
                    if ($status === null) {
                        continue;
                    }

                    $bw = $row[$colIdx + 1] ?? null;
                    $users = $row[$colIdx + 2] ?? null;
                    $attributes = [
                        'status' => $status,
                        'bandwidth_utilization_mbps' => is_numeric($bw) ? (float) $bw : null,
                        'total_unique_users' => is_numeric($users) ? (int) $users : null,
                    ];

                    $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', $date)->first();
                    if ($existing) {
                        $existing->fill($attributes)->save();
                    } else {
                        SiteDailyStatus::create($attributes + ['site_id' => $site->id, 'date' => $date]);
                    }
                    $statusesUpserted++;
                }
            });
        }
    }

    protected function mapLifecycle(string $lifecycle): string
    {
        return match (strtoupper(trim($lifecycle))) {
            'ACTIVE', 'ONGOING', 'REBATES' => 'active',
            'EXPIRED' => 'inactive',
            'SUPPORT' => 'maintenance',
            'REMOVED' => 'decommissioned',
            default => 'planned',
        };
    }

    protected function toBool(mixed $value): ?bool
    {
        $raw = strtoupper(trim((string) ($value ?? '')));
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['YES', 'Y', 'TRUE', '1'], true);
    }

    /** Enum-safe island group: anything unrecognized stays NULL. */
    protected function islandGroup(mixed $value): ?string
    {
        $raw = strtolower(trim((string) ($value ?? '')));

        return in_array($raw, ['luzon', 'visayas', 'mindanao'], true) ? ucfirst($raw) : null;
    }

    /** Projects named in the workbook (PICS MUN Lot 2, Emergency Procurement, …). */
    protected function resolveProject(?string $name): Project
    {
        $name = $name ?: 'Uncategorized Free WiFi';
        // Key on the code: name spellings drift between sheets ("PoP Extension"
        // vs "POP Extension") but they are the same program.
        $code = strtoupper(str_replace('-', '', Str::slug($name)));
        $code = substr(preg_replace('/[^A-Z0-9]/', '', $code) ?: 'FW', 0, 20);

        return Project::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'report_type' => 'freewifi',
                'marker_color' => ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6'][abs(crc32($code)) % 6],
                'marker_shape' => 'circle',
                'marker_icon' => 'wifi',
            ],
        );
    }

    /** Attach (or adopt) a MAC-identified AP and deploy it at the site. */
    protected function attachApDevice(Site $site, string $mac, ?string $brand, ?int $actorId): bool
    {
        $device = Device::withTrashed()->where('mac_address', $mac)->first();
        if ($device && $device->trashed()) {
            $device->restore();
        }
        if (! $device) {
            $manufacturer = $brand ?: 'Unknown';
            $model = DeviceModel::firstOrCreate(
                ['manufacturer' => $manufacturer, 'model_number' => $brand ?: 'AP'],
                ['model_name' => $brand ?: 'Unspecified Access Point', 'type' => 'outdoor_ap', 'is_active' => true],
            );
            $device = Device::create([
                'device_model_id' => $model->id,
                'asset_tag' => $this->nextAssetTag(),
                'serial_number' => 'MAC-'.$mac,
                'mac_address' => $mac,
                'status' => 'in_stock',
            ]);
        }

        $active = $device->currentDeployment()->first();
        if ($active && (int) $active->site_id === (int) $site->id) {
            return false; // already deployed here
        }
        if ($active) {
            $active->update(['removed_at' => now()]);
        }
        $this->deployments->open($device, [
            'site_id' => $site->id,
            'role_at_site' => 'primary_ap',
            'installed_at' => $site->date_of_activation ?? now(),
        ], $actorId);
        $device->update(['status' => 'deployed']);

        return true;
    }
}
