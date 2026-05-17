<?php
namespace App\Services;
use App\Models\FreewifiImportBatch;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
class ImportService
{
    public function beginImport(string $filename, int $userId): FreewifiImportBatch
    {
        return FreewifiImportBatch::create([
            'filename' => $filename,
            'imported_by' => $userId,
            'job_status' => 'PENDING',
        ]);
    }
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
            if (!$freewifi) {
                $batch->update(['job_status' => 'FAILED', 'error_log' => [['message' => 'FREEWIFI project not found']]]);
                return;
            }
            $dateColumns = $this->parseDateColumns($headers);
            foreach ($rows as $rowIndex => $row) {
                try {
                    $rowData = array_combine($headers, $row);
                    $site = $this->upsertSite($rowData, $freewifi->id);
                    if ($site && $dateColumns) {
                        $this->upsertDailyStatuses($site->id, $rowData, $dateColumns);
                    }
                    $success++;
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
        if ($validator->fails()) return null;
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
                'island_group' => in_array($data['Island Group'] ?? '', ['Luzon','Visayas','Mindanao']) ? $data['Island Group'] : null,
                'latitude' => (float)$data['LATITUDE'],
                'longitude' => (float)$data['LONGITUDE'],
                'status' => $this->mapStatus($data['STATUS (overall)'] ?? 'planned'),
                'isp_provider' => $data['ISP/Provider'] ?? null,
                'last_mile_tech' => $data['Last Mile Technology'] ?? null,
                'bw_download_cir' => isset($data['BW Download (CIR)']) ? (float)preg_replace('/[^0-9.]/', '', $data['BW Download (CIR)']) : null,
            ]
        );
    }
    protected function upsertDailyStatuses(int $siteId, array $data, array $dateColumns): void
    {
        foreach ($dateColumns as $col) {
            $date = $col['date'];
            $statusValue = $data[$col['header']] ?? '';
            $status = match(strtoupper(trim($statusValue))) {
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
                    'bandwidth_utilization_mbps' => isset($data[$bwCol]) ? (float)$data[$bwCol] : null,
                    'total_unique_users' => isset($data[$usersCol]) ? (int)$data[$usersCol] : null,
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
                $date = \Carbon\Carbon::parse($trimmed . ' ' . date('Y'))->format('Y-m-d');
                $dateCols[] = ['header' => $trimmed, 'date' => $date];
            }
        }
        return $dateCols;
    }
    protected function mapStatus(string $status): string
    {
        return match(strtoupper(trim($status))) {
            'ACTIVE' => 'active',
            'DOWN', 'INACTIVE' => 'inactive',
            'DECOMMISSIONED' => 'decommissioned',
            'MAINTENANCE' => 'maintenance',
            default => 'planned',
        };
    }
}
