<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\FreewifiImportBatch;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ReportExport;
use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use App\Models\SiteSurveyResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Every queued PDF in the analytics family. Each public generator is a thin
 * shell: read the filters, ask a `*Data()`/inventory method for the figures,
 * hand them to a Blade with a narrative beside them.
 *
 * The data shapes are declared here (and imported by ReportNarrative) so the
 * narrative, the template and the CSV companion cannot disagree about what a
 * figure is.
 *
 * @phpstan-import-type Bundle from ReportAnalytics
 * @phpstan-import-type ScopeParams from ReportAnalytics
 * @phpstan-import-type SiteCoverage from SiteCoverageService
 * @phpstan-import-type BarangayCoverage from BarangayCoverageService
 * @phpstan-import-type Summary from SiteSurveyAnalytics
 *
 * @phpstan-type ProjectRow array{location: mixed, municipality: string, province: string, site_type: string, daily_status: mixed, devices: int, cir: mixed}
 * @phpstan-type ProvinceRow array{municipality: string|int, sites: int, up: int, up_pct: float}
 * @phpstan-type Csv array{name: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}
 * @phpstan-type Comparison array{current: Bundle, previous: Bundle, previous_range: string, delta_uptime: float, delta_down: int, delta_sitedays: int}
 * @phpstan-type FirmwareRow array{version: string, count: int, approved: bool}
 * @phpstan-type Inventory array{
 *   deployed: int, in_stock: int, under_repair: int, warranty_expiring: int,
 *   approved_firmware: list<string>, firmware_rows: list<FirmwareRow>,
 *   outdated: int|null, register: Collection<int, Device>
 * }
 * @phpstan-type OpenAlert array{severity: string, rule: string, site: string, triggered_at: string, age_h: int}
 * @phpstan-type OpenTicket array{site: string, title: mixed, status: string, priority: string, age_h: int}
 * @phpstan-type Incidents array{
 *   from: string, to: string, scope: string, alerts_triggered: int,
 *   by_severity: array<string, int>, mtta_h: float|null, mttr_alerts_h: float|null,
 *   mttr_tickets_h: float|null, tickets_open: int, tickets_by_priority: array<string, int>,
 *   open_alerts: list<OpenAlert>, open_tickets: list<OpenTicket>
 * }
 * @phpstan-type MilestoneRow array{name: mixed, weight: float, avg_pct: float, sites: int}
 * @phpstan-type MilestoneGroup array{project: string, weighted_pct: float, milestones: list<MilestoneRow>}
 * @phpstan-type OverdueRow array{site: string, project: string, milestone: string, status: mixed, pct: float, target_date: string, days_overdue: int}
 * @phpstan-type Progress array{scope: string, overall_pct: float, projects: list<MilestoneGroup>, overdue: list<OverdueRow>}
 * @phpstan-type LowSite array{site_id: int, site: string, where: string, responses: int, overall: float|null, by_question: array<string, float|null>}
 * @phpstan-type Remark array{site: string, rating: float|null, comments: string, submitted_at: string}
 * @phpstan-type ProviderRollup array{cms_provider: string|null, last_mile_tech: string|null, responses: int, meets_minimum: bool, overall: float|null}
 * @phpstan-type Satisfaction array{
 *   scope: string, from: string, to: string, window_days: int, min_responses: int,
 *   responses: int, scope_mean: float|null, by_question: array<string, float|null>,
 *   distribution: array<int, int>, rated_sites: int, unrated_sites: int,
 *   low: list<LowSite>, comments: list<Remark>, providers: list<ProviderRollup>
 * }
 * @phpstan-type TrendDay array{date: string, up: int, down: int}
 */
class ReportingService
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function generateProjectSummaryPdf(Project $project, array $params = [], string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $analytics = app(ReportAnalytics::class)->for(
            $params + ['project_id' => $project->id, 'project' => $project->name]
        );

        // Chunk the fleet so a province-sized project never hydrates every
        // site row into memory at once; statuses at the period end resolve
        // in one query instead of N+1.
        $register = $this->projectRegister($project);
        $statusesAtTo = SiteDailyStatus::whereIn('site_id', $register->pluck('id'))
            ->whereDate('date', $analytics['to'])
            ->pluck('status', 'site_id');

        return Pdf::loadView('reports.project-summary', [
            'project' => $project,
            'analytics' => $analytics,
            'register' => $register,
            'statusesAtTo' => $statusesAtTo,
            'bullets' => app(ReportNarrative::class)->forProject($analytics),
            'userName' => $userName,
        ]);
    }

    /** @param  array<string, mixed>  $params  */
    public function generateProvinceReport(string $province, ?int $projectId = null, array $params = [], string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $analytics = app(ReportAnalytics::class);
        [$from, $to] = $analytics->period($params);
        $projectName = $projectId ? Project::where('id', $projectId)->value('name') : null;
        $scope = $analytics->describeScope($params + [
            'province' => $province,
            'project' => $projectName,
            'project_id' => $projectId,
        ]);

        $sites = collect();
        $query = Site::where('province', $province)->with('project');
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        $query->chunk(500, fn ($chunk) => $sites->push(...$chunk));
        $grouped = $sites->sortBy('municipality')->groupBy(fn (Site $s) => (string) ($s->municipality ?? 'Unknown'));

        // One statuses query for the period end, keyed by site — feeds both
        // the municipality rollup and the per-site daily-status column.
        $statusesAtTo = SiteDailyStatus::whereIn('site_id', $sites->pluck('id'))
            ->whereDate('date', $to)
            ->pluck('status', 'site_id');

        $rollup = $grouped->map(function ($municipalitySites, $municipality) use ($statusesAtTo) {
            $up = $municipalitySites->filter(fn ($s) => $statusesAtTo->get($s->id) === 'UP')->count();
            $total = $municipalitySites->count();

            return [
                'municipality' => $municipality,
                'sites' => $total,
                'up' => $up,
                'up_pct' => $total > 0 ? round($up / $total * 100, 1) : 0.0,
            ];
        })->values();

        return Pdf::loadView('reports.province-summary', compact(
            'province', 'sites', 'grouped', 'rollup', 'statusesAtTo', 'scope', 'userName'
        ) + ['bullets' => app(ReportNarrative::class)->forProvince($rollup)]);
    }

    /**
     * Site Type coverage (actual vs registered) — same data as /map/coverage.
     *
     * @param  ScopeParams  $filters
     */
    public function generateSiteTypeCoverageReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $coverage = app(SiteCoverageService::class)->coverage($filters);

        return Pdf::loadView('reports.site-type-coverage', [
            'coverage' => $coverage,
            'sites' => $this->siteTypeAppendix($filters),
            'bullets' => app(ReportNarrative::class)->forSiteType($coverage),
            'userName' => $userName,
        ]);
    }

    /**
     * Deployed-site appendix rows for the filters. Chunked and deliberately
     * uncapped — an earlier 200-row gate silently dropped rows past the cap.
     *
     * @param  ScopeParams  $filters
     * @return Collection<int, Site>
     */
    public function siteTypeAppendix(array $filters): Collection
    {
        $sites = collect();
        $query = Site::query()->whereHas('activeDeployments')->with(['project:id,code,name', 'activeDeployments.device:id,asset_tag']);
        foreach (['province', 'district', 'municipality', 'barangay'] as $column) {
            if (! empty($filters[$column])) {
                $query->where("sites.{$column}", $filters[$column]);
            }
        }
        if (! empty($filters['project_id'])) {
            $query->where('sites.project_id', $filters['project_id']);
        }
        if (! empty($filters['site_type'])) {
            $query->where('sites.site_type', $filters['site_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('sites.status', $filters['status']);
        }
        $query->orderBy('site_type')->orderBy('location_name')
            ->chunk(500, fn ($chunk) => $sites->push(...$chunk));

        return $sites;
    }

    /**
     * Barangay coverage (installed/existing vs total) — same data as /map/barangay-coverage.
     *
     * @param  ScopeParams  $filters
     */
    public function generateBarangayCoverageReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $coverage = app(BarangayCoverageService::class)->coverage($filters);

        return Pdf::loadView('reports.barangay-coverage', [
            'coverage' => $coverage,
            'bullets' => app(ReportNarrative::class)->forBarangay($coverage),
            'userName' => $userName,
        ]);
    }

    /**
     * CSV companion for an export's primary annex table (Plan.md Phase 4).
     * Regenerated live from the export params — no stored file to clean up.
     * Combined packs have no single table, so they get none.
     *
     * @return Csv
     */
    public function exportCsv(ReportExport $export): array
    {
        $params = $export->params ?? [];
        $filters = $params['filters'] ?? $params;

        $csv = match ($export->type) {
            'project' => $this->projectRegisterCsv(
                Project::findOrFail($params['project_id']), $params
            ),
            'province' => $this->provinceRegisterCsv(
                $params['province'], $params['project_id'] ?? null, $params
            ),
            'site_type' => [
                'name' => 'site-type-coverage.csv',
                'headings' => ['Site type', 'Registered', 'Actual', 'Gap', 'Devices', 'Coverage %'],
                'rows' => array_map(
                    fn ($r) => [$r['label'], $r['registered'], $r['actual'], $r['gap'], $r['devices'], $r['coverage_pct']],
                    app(SiteCoverageService::class)->coverage($filters)['rows']
                ),
            ],
            'barangay_coverage' => [
                'name' => 'barangay-coverage.csv',
                'headings' => ['Province', 'Municipality', 'Covered', 'Deployed', 'Remaining', 'Total', 'Coverage %'],
                'rows' => array_map(
                    fn ($r) => [$r['province'], $r['municipality'], $r['covered'], $r['deployed'], $r['remaining'], $r['total_barangays'], $r['coverage_pct']],
                    app(BarangayCoverageService::class)->coverage($filters)['rows']
                ),
            ],
            'ops_period' => [
                'name' => 'open-down-episodes.csv',
                'headings' => ['Site', 'Where', 'Status', 'Since', 'Duration (h)'],
                'rows' => array_map(
                    fn ($e) => [$e['site'], $e['where'], $e['status'], $e['started_at'], $e['duration_h']],
                    $this->opsPeriodComparison($filters)['current']['down_episodes']
                ),
            ],
            'fleet' => [
                'name' => 'device-register.csv',
                'headings' => ['Asset tag', 'Model', 'Status', 'Firmware', 'Site', 'Warranty until'],
                'rows' => $this->fleetInventory($filters)['register']
                    ->map(fn ($d) => [
                        $d->asset_tag,
                        trim(($d->deviceModel->manufacturer ?? '').' '.($d->deviceModel->model_name ?? '')),
                        $d->status,
                        $d->firmware_version ?? '',
                        data_get($d->currentDeployment, 'site.location_name', ''),
                        $d->warranty_until?->toDateString() ?? '',
                    ])->all(),
            ],
            'incidents' => [
                'name' => 'open-alerts.csv',
                'headings' => ['Severity', 'Rule', 'Site', 'Triggered', 'Age (h)'],
                'rows' => array_map(
                    fn ($a) => [$a['severity'], $a['rule'], $a['site'], $a['triggered_at'], $a['age_h']],
                    $this->incidentsData($filters)['open_alerts']
                ),
            ],
            'progress' => [
                'name' => 'overdue.csv',
                'headings' => ['Site', 'Milestone', 'Status', '%', 'Target', 'Days overdue'],
                'rows' => array_map(
                    fn ($r) => [$r['site'], $r['milestone'], $r['status'], $r['pct'], $r['target_date'], $r['days_overdue']],
                    $this->progressData($filters)['overdue']
                ),
            ],
            'satisfaction' => [
                'name' => 'site-satisfaction.csv',
                // $where is "municipality, province" - heading must say so, or
                // every row reads as a province that contains a comma.
                'headings' => ['Site', 'Location', 'Responses', 'Rating / 5'],
                'rows' => array_map(
                    fn ($r) => [$r['site'], $r['where'], $r['responses'], $r['overall'] ?? ''],
                    $this->satisfactionData($filters)['low']
                ),
            ],
            default => throw new InvalidArgumentException("No CSV companion for '{$export->type}' reports."),
        };

        // Formula-injection guard: a location/remark starting with = + - @
        // (or tab/CR) executes as a formula when the CSV is opened in Excel.
        // Prefixing with ' forces text treatment in every spreadsheet app.
        $csv['rows'] = array_map(
            fn ($row) => array_map(self::csvSafe(...), (array) $row),
            $csv['rows']
        );

        return $csv;
    }

    private static function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Csv
     */
    private function projectRegisterCsv(Project $project, array $params): array
    {
        $to = app(ReportAnalytics::class)->period($params)[1];
        $register = $this->projectRegister($project);
        $statuses = SiteDailyStatus::whereIn('site_id', $register->pluck('id'))
            ->whereDate('date', $to)->pluck('status', 'site_id');

        return [
            'name' => "project-{$project->code}-register.csv",
            'headings' => ['Location', 'Municipality', 'Province', 'Type', 'Daily status', 'Devices', 'CIR (Mbps)'],
            'rows' => $register->map(fn ($site) => [
                $site->location_name,
                $site->municipality ?? '',
                $site->province ?? '',
                $site->site_type ?? '',
                $statuses->get($site->id, 'NO DATA'),
                $site->activeDeployments->count(),
                $site->bw_download_cir ?? '',
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Csv
     */
    private function provinceRegisterCsv(string $province, ?int $projectId, array $params): array
    {
        $to = app(ReportAnalytics::class)->period($params)[1];
        $query = Site::where('province', $province);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        $sites = collect();
        $query->orderBy('municipality')->orderBy('location_name')
            ->chunk(500, fn ($chunk) => $sites->push(...$chunk));
        $statuses = SiteDailyStatus::whereIn('site_id', $sites->pluck('id'))
            ->whereDate('date', $to)->pluck('status', 'site_id');

        return [
            'name' => 'province-register.csv',
            'headings' => ['Municipality', 'Location', 'Barangay', 'Status', 'Daily status'],
            'rows' => $sites->map(fn ($site) => [
                $site->municipality ?? '',
                $site->location_name,
                $site->barangay ?? '',
                $site->status,
                $statuses->get($site->id, 'NO DATA'),
            ])->all(),
        ];
    }

    /**
     * Chunked site register shared by the project PDF and its CSV.
     *
     * @return Collection<int, Site>
     */
    private function projectRegister(Project $project): Collection
    {
        $register = collect();
        $project->sites()->with(['activeDeployments.device:id,asset_tag'])
            ->chunk(500, fn ($chunk) => $register->push(...$chunk));

        return $register->sortBy('location_name')->values();
    }

    /**
     * @param  ScopeParams  $filters
     * @return Comparison
     */
    public function opsPeriodComparison(array $filters): array
    {
        $analytics = app(ReportAnalytics::class);
        [$from, $to] = $analytics->period($filters);
        $days = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subDay()->toDateString();
        $prevFrom = $from->copy()->subDays($days)->toDateString();

        $current = $analytics->for($filters);
        $previous = $analytics->for(array_merge($filters, ['from' => $prevFrom, 'to' => $prevTo]));

        $delta = fn (float $now, float $was) => round($now - $was, 1);

        return [
            'current' => $current,
            'previous' => $previous,
            'previous_range' => $prevFrom.' – '.$prevTo,
            'delta_uptime' => $delta($current['uptime_pct'], $previous['uptime_pct']),
            'delta_down' => $current['daily']['down'] + $current['daily']['down_server']
                - $previous['daily']['down'] - $previous['daily']['down_server'],
            'delta_sitedays' => $current['uptime_base'] - $previous['uptime_base'],
        ];
    }

    /** @param  ScopeParams  $filters  */
    public function generateOpsPeriodReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $comparison = $this->opsPeriodComparison($filters);

        return Pdf::loadView('reports.ops-period', [
            'comparison' => $comparison,
            'bullets' => app(ReportNarrative::class)->forOps($comparison),
            'userName' => $userName,
        ]);
    }

    /**
     * Fleet inventory data: counts, firmware compliance vs APPROVED_FIRMWARE,
     * full device register. Fleet-wide except deployed scope, which follows
     * the project filter (stock has no geography).
     *
     * @param  ScopeParams  $filters
     * @return Inventory
     */
    public function fleetInventory(array $filters): array
    {
        $projectId = $filters['project_id'] ?? null;
        $siteIds = Site::query()->select('sites.id')
            ->when($projectId, fn ($q) => $q->where('sites.project_id', $projectId));
        $deployed = Device::where('status', 'deployed')
            ->when($projectId, fn ($q) => $q->whereHas(
                'currentDeployment', fn ($d) => $d->whereIn('site_id', (clone $siteIds))
            ));

        $approved = (array) config('monitoring.approved_firmware', []);
        $firmwareRows = (clone $deployed)
            ->selectRaw('firmware_version, COUNT(*) AS n')
            ->groupBy('firmware_version')
            ->get()
            ->map(fn ($row) => [
                'version' => $row->firmware_version ?: 'Unknown',
                'count' => (int) $row->getAttribute('n'),
                'approved' => $row->firmware_version !== null && in_array($row->firmware_version, $approved, true),
            ])
            ->sortByDesc('count')->values()->all();

        $register = collect();
        Device::with(['deviceModel:id,manufacturer,model_name', 'currentDeployment.site:id,location_name'])
            ->orderBy('status')->orderBy('asset_tag')
            ->chunk(500, fn ($chunk) => $register->push(...$chunk));

        return [
            'deployed' => (clone $deployed)->count(),
            'in_stock' => Device::where('status', 'in_stock')->count(),
            'under_repair' => Device::where('status', 'under_repair')->count(),
            'warranty_expiring' => Device::where('status', 'deployed')
                ->whereBetween('warranty_until', [now(), now()->addDays(90)])->count(),
            'approved_firmware' => $approved,
            'firmware_rows' => $firmwareRows,
            'outdated' => $approved === []
                ? null
                : array_sum(array_column(array_filter($firmwareRows, fn ($r) => ! $r['approved']), 'count')),
            'register' => $register,
        ];
    }

    /** @param  ScopeParams  $filters  */
    public function generateFleetReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $scope = app(ReportAnalytics::class)->describeScope($filters);
        $inventory = $this->fleetInventory($filters);

        return Pdf::loadView('reports.fleet', [
            'inventory' => $inventory,
            'scope' => $scope,
            'bullets' => app(ReportNarrative::class)->forFleet($inventory),
            'userName' => $userName,
        ]);
    }

    /**
     * Incident pack data: alert severity for the window, ticket backlog,
     * MTTA/MTTR in hours, open lists. Pure data (tested).
     *
     * @param  ScopeParams  $filters
     * @return Incidents
     */
    public function incidentsData(array $filters): array
    {
        $analytics = app(ReportAnalytics::class);
        [$from, $to] = $analytics->period($filters);
        $ids = $analytics->siteIds($filters);
        // Timestamp bounds run to end-of-day: the period end is midnight, and
        // intraday alerts/tickets still belong to the window.
        $window = [$from, $to->copy()->endOfDay()];

        $bySeverity = Alert::query()->whereIn('site_id', (clone $ids))
            ->whereBetween('triggered_at', $window)
            ->join('alert_rules', 'alert_rules.id', '=', 'alerts.rule_id')
            ->selectRaw('alert_rules.severity, COUNT(*) AS n')
            ->groupBy('alert_rules.severity')
            ->pluck('n', 'severity');

        $samples = Alert::query()->whereIn('site_id', (clone $ids))
            ->where(fn ($q) => $q
                ->whereBetween('acknowledged_at', $window)
                ->orWhereBetween('resolved_at', $window))
            ->take(1000)
            ->get(['triggered_at', 'acknowledged_at', 'resolved_at']);
        $avgHours = fn ($rows, string $col) => ($n = $rows->whereNotNull($col)->count()) > 0
            ? round($rows->whereNotNull($col)->average(
                fn ($r) => $r->triggered_at->diffInSeconds($r->{$col}) / 3600
            ), 1)
            : null;

        $ticketBase = MaintenanceTicket::query()->whereIn('site_id', (clone $ids));
        $backlog = (clone $ticketBase)->whereIn('status', ['OPEN', 'IN_PROGRESS']);
        $byPriority = (clone $backlog)
            ->selectRaw('priority, COUNT(*) AS n')->groupBy('priority')->pluck('n', 'priority');
        $ticketSamples = (clone $ticketBase)
            ->whereNotNull('resolved_at')->whereBetween('resolved_at', $window)
            ->take(1000)->get(['created_at', 'resolved_at']);
        $ticketMttr = $ticketSamples->isNotEmpty()
            ? round($ticketSamples->average(
                fn ($t) => $t->created_at->diffInSeconds($t->resolved_at) / 3600
            ), 1)
            : null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $analytics->describeScope($filters),
            'alerts_triggered' => (int) $bySeverity->sum(),
            'by_severity' => $bySeverity->all(),
            'mtta_h' => $avgHours($samples, 'acknowledged_at'),
            'mttr_alerts_h' => $avgHours($samples, 'resolved_at'),
            'tickets_open' => (clone $backlog)->count(),
            'tickets_by_priority' => $byPriority->all(),
            'mttr_tickets_h' => $ticketMttr,
            'open_alerts' => Alert::query()->whereIn('site_id', (clone $ids))->whereNull('resolved_at')
                ->with(['rule:id,name,severity', 'site:id,location_name'])
                ->orderBy('triggered_at')->take(10)
                ->get(['id', 'rule_id', 'site_id', 'triggered_at'])
                ->map(fn ($alert) => [
                    'severity' => data_get($alert->rule, 'severity', 'info'),
                    'rule' => data_get($alert->rule, 'name', '—'),
                    'site' => data_get($alert->site, 'location_name', '—'),
                    'triggered_at' => $alert->triggered_at->toDateTimeString(),
                    'age_h' => (int) $alert->triggered_at->diffInHours(now()),
                ])->all(),
            'open_tickets' => (clone $backlog)
                ->with('site:id,location_name')
                ->orderBy('created_at')->take(10)
                ->get(['id', 'site_id', 'title', 'status', 'priority', 'created_at'])
                ->map(fn ($ticket) => [
                    'site' => data_get($ticket->site, 'location_name', '—'),
                    'title' => $ticket->title,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'age_h' => (int) $ticket->created_at->diffInHours(now()),
                ])->all(),
        ];
    }

    /** @param  ScopeParams  $filters  */
    public function generateIncidentsReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $incidents = $this->incidentsData($filters);

        return Pdf::loadView('reports.incidents', [
            'incidents' => $incidents,
            'bullets' => app(ReportNarrative::class)->forIncidents($incidents),
            'userName' => $userName,
        ]);
    }

    /**
     * Progress pack data: weighted accomplishment % per project and overall,
     * per-milestone bars, overdue list. Pure data (tested).
     *
     * @param  ScopeParams  $filters
     * @return Progress
     */
    public function progressData(array $filters): array
    {
        $analytics = app(ReportAnalytics::class);
        $projectId = $filters['project_id'] ?? null;

        $milestones = ProjectMilestone::query()
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->with('project:id,name')
            ->orderBy('project_id')->orderBy('milestone_order')
            ->get(['id', 'project_id', 'milestone_name', 'milestone_order', 'weight_pct']);

        $avgs = SiteAccomplishment::query()
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->selectRaw('milestone_id, AVG(pct_complete) AS avg_pct, COUNT(*) AS n')
            ->groupBy('milestone_id')
            ->get()->keyBy('milestone_id');

        $projects = [];
        $grandWeight = 0.0;
        $grandPoints = 0.0;
        foreach ($milestones->groupBy('project_id') as $projectMilestones) {
            $rows = [];
            $weight = 0.0;
            $points = 0.0;
            foreach ($projectMilestones as $milestone) {
                $avg = (float) ($avgs->get($milestone->id)->avg_pct ?? 0);
                $w = (float) $milestone->weight_pct;
                $weight += $w;
                $points += $w * $avg;
                $grandWeight += $w;
                $grandPoints += $w * $avg;
                $rows[] = [
                    'name' => $milestone->milestone_name,
                    'weight' => $w,
                    'avg_pct' => round($avg, 1),
                    'sites' => (int) ($avgs->get($milestone->id)->n ?? 0),
                ];
            }
            $projects[] = [
                'project' => $projectMilestones->first()->project->name ?? "#{$projectMilestones->first()->project_id}",
                'weighted_pct' => $weight > 0 ? round($points / $weight, 1) : 0.0,
                'milestones' => $rows,
            ];
        }

        $overdue = SiteAccomplishment::query()
            ->whereIn('site_id', $analytics->siteIds($filters))
            ->whereDate('target_date', '<', today())
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->with(['site:id,location_name,project_id', 'milestone:id,milestone_name,project_id', 'site.project:id,name'])
            ->orderBy('target_date')->take(50)
            ->get(['id', 'site_id', 'milestone_id', 'status', 'pct_complete', 'target_date'])
            ->map(fn ($row) => [
                'site' => data_get($row->site, 'location_name', '—'),
                'project' => data_get($row->site, 'project.name', '—'),
                'milestone' => data_get($row->milestone, 'milestone_name', '—'),
                'status' => $row->status,
                'pct' => (float) $row->pct_complete,
                'target_date' => $row->target_date->toDateString(),
                'days_overdue' => (int) $row->target_date->diffInDays(today()),
            ])->all();

        return [
            'scope' => $analytics->describeScope($filters),
            'overall_pct' => $grandWeight > 0 ? round($grandPoints / $grandWeight, 1) : 0.0,
            'projects' => $projects,
            'overdue' => $overdue,
        ];
    }

    /** @param  ScopeParams  $filters  */
    public function generateProgressReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $progress = $this->progressData($filters);

        return Pdf::loadView('reports.progress', [
            'progress' => $progress,
            'bullets' => app(ReportNarrative::class)->forProgress($progress),
            'userName' => $userName,
        ]);
    }

    /**
     * Satisfaction pack data (Plan.md S6): what people connected actually said,
     * scoped to the same period + geo filters as every other pack.
     *
     * The headline number is the mean over *every* rating in the window, not the
     * mean of per-site means — averaging averages quietly over-weights the quiet
     * sites, which are exactly the ones a manager least wants to flatter.
     *
     * @param  ScopeParams  $filters
     * @return Satisfaction
     */
    public function satisfactionData(array $filters): array
    {
        $analytics = app(ReportAnalytics::class);
        [$from, $to] = $analytics->period($filters);
        $windowDays = (int) $from->diffInDays($to) + 1;
        $since = now()->subDays($windowDays);
        $surveys = app(SiteSurveyAnalytics::class);

        $siteIds = $analytics->siteIds($filters)->pluck('id')->all();

        $scope = $surveys->forScope($siteIds, $windowDays);
        $perSite = $surveys->forSites($siteIds, $windowDays);

        // Rated sites only, worst first: a manager opening this pack is looking
        // for the sites to visit, not for the fleet average they already have.
        $worst = collect($perSite)
            ->filter(fn (array $summary) => $summary['meets_minimum'])
            ->sortBy(fn (array $summary) => $summary['overall'])
            ->take(25);

        $names = $worst->isEmpty()
            ? collect()
            : Site::whereIn('id', $worst->keys())
                ->get(['id', 'location_name', 'municipality', 'province'])
                ->keyBy('id');

        $low = $worst->map(fn (array $summary, $id) => [
            'site_id' => $id,
            'site' => data_get($names->get($id), 'location_name', '—'),
            'where' => trim(implode(', ', array_filter([
                data_get($names->get($id), 'municipality'),
                data_get($names->get($id), 'province'),
            ])), ', '),
            'responses' => $summary['responses'],
            'overall' => $summary['overall'],
            'by_question' => $summary['by_question'],
        ])->values()->all();

        // Free text is the actionable part of a bad score, so the pack carries
        // the most recent low-rated remarks verbatim (escaped by Blade,
        // formula-guarded on CSV export).
        $comments = SiteSurveyResponse::query()
            ->whereIn('site_id', $siteIds ?: [0])
            ->where('submitted_at', '>=', $since)
            ->whereNotNull('comments')
            ->where('comments', '!=', '')
            ->with('site:id,location_name')
            ->orderByDesc('submitted_at')
            ->take(25)
            ->get(['id', 'site_id', 'ratings', 'comments', 'submitted_at'])
            ->map(fn (SiteSurveyResponse $r) => [
                'site' => data_get($r->site, 'location_name', '—'),
                'rating' => $this->primaryRating($r->ratings ?? []),
                'comments' => (string) $r->comments,
                'submitted_at' => $r->submitted_at->toDateString(),
            ])->all();

        return [
            'scope' => $analytics->describeScope($filters),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'window_days' => $windowDays,
            'min_responses' => SiteSurveyAnalytics::MIN_RESPONSES,
            'responses' => $scope['responses'],
            'scope_mean' => $scope['overall'],
            'by_question' => $scope['by_question'],
            'distribution' => $scope['distribution'],
            'rated_sites' => count($low),
            'unrated_sites' => count(array_filter($perSite, fn (array $s) => ! $s['meets_minimum'] && $s['responses'] > 0)),
            'low' => $low,
            'comments' => $comments,
            'providers' => $surveys->byProvider($windowDays, $siteIds ?: null),
        ];
    }

    /**
     * Mean of the numeric answers on one response, or null when it has none.
     *
     * @param  array<string, mixed>  $ratings
     */
    private function primaryRating(array $ratings): ?float
    {
        $values = array_filter($ratings, 'is_numeric');

        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }

    /** @param  ScopeParams  $filters  */
    public function generateSatisfactionReport(array $filters, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $satisfaction = $this->satisfactionData($filters);

        return Pdf::loadView('reports.satisfaction', [
            'satisfaction' => $satisfaction,
            'bullets' => app(ReportNarrative::class)->forSatisfaction($satisfaction),
            'userName' => $userName,
        ]);
    }

    /**
     * Combined operations pack: cover plus the selected analytic sections in
     * one PDF. Unknown sections are dropped; an empty set is a permanent
     * failure (it would retry identically forever).
     *
     * @param  ScopeParams  $filters
     * @param  list<string>  $sections
     */
    public function generateCombinedReport(array $filters, array $sections, string $userName = 'system'): \Barryvdh\DomPDF\PDF
    {
        $sections = array_values(array_intersect(
            $sections, ['ops_period', 'fleet', 'incidents', 'progress', 'satisfaction']
        ));
        if ($sections === []) {
            throw new InvalidArgumentException('No report sections selected.');
        }

        $analytics = app(ReportAnalytics::class);
        [$from, $to] = $analytics->period($filters);
        $data = [
            'sections' => $sections,
            'scope' => $analytics->describeScope($filters),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'userName' => $userName,
        ];
        foreach ($sections as $section) {
            if ($section === 'ops_period') {
                $data['comparison'] = $this->opsPeriodComparison($filters);
                $data['bullets']['ops_period'] = app(ReportNarrative::class)->forOps($data['comparison']);
            } elseif ($section === 'fleet') {
                $data['inventory'] = $this->fleetInventory($filters);
                $data['bullets']['fleet'] = app(ReportNarrative::class)->forFleet($data['inventory']);
            } elseif ($section === 'incidents') {
                $data['incidents'] = $this->incidentsData($filters);
                $data['bullets']['incidents'] = app(ReportNarrative::class)->forIncidents($data['incidents']);
            } elseif ($section === 'progress') {
                $data['progress'] = $this->progressData($filters);
                $data['bullets']['progress'] = app(ReportNarrative::class)->forProgress($data['progress']);
            } elseif ($section === 'satisfaction') {
                $data['satisfaction'] = $this->satisfactionData($filters);
                $data['bullets']['satisfaction'] = app(ReportNarrative::class)->forSatisfaction($data['satisfaction']);
            }
        }

        return Pdf::loadView('reports.combined', $data);
    }

    /** @return array<string, mixed> */
    public function getDashboardStats(): array
    {
        $activeSites = Site::where('status', 'active')->count();
        $reportedToday = $this->reportedSiteCount(today());

        // One grouped pass instead of one query per status, and every status is
        // represented so the counters actually reconcile with the site total.
        $todayCounts = SiteDailyStatus::whereDate('date', today())
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'total_projects' => Project::count(),
            'total_sites' => Site::count(),
            'active_sites' => $activeSites,
            'total_up_today' => (int) $todayCounts->get('UP', 0),
            'down_today' => (int) $todayCounts->get('DOWN', 0),
            'down_server_today' => (int) $todayCounts->get('DOWN_SERVER', 0),
            'no_nms_today' => (int) $todayCounts->get('NO_NMS', 0),
            'no_data_today' => max(0, $activeSites - $reportedToday),
            'reported_today' => $reportedToday,
            'uptime_pct_7d' => $this->uptimePct(now()->subDays(6)->startOfDay(), now()->endOfDay()),
            'trend' => $this->dailyTrend(14),
            'recent_imports' => FreewifiImportBatch::with('importer:id,name')->latest()->take(5)->get(),

            // Network reach (distinct places with at least one site).
            'network' => [
                'provinces' => Site::whereNotNull('province')->distinct()->count('province'),
                'municipalities' => Site::whereNotNull('municipality')->distinct()->count('municipality'),
                'barangays' => Site::whereNotNull('barangay')->distinct()->count('barangay'),
                'sites_per_province' => Site::whereNotNull('province')
                    ->selectRaw('province, COUNT(*) n')->groupBy('province')
                    ->orderByDesc('n')->get(),
            ],

            // Barangay coverage snapshot (PSA-reconciled totals).
            'barangay_coverage' => app(BarangayCoverageService::class)->coverage()['totals'],

            // Site Type coverage: registered vs actual.
            'site_type_totals' => app(SiteCoverageService::class)->coverage()['totals'],

            // Field equipment.
            'devices' => [
                'deployed' => Device::where('status', 'deployed')->count(),
                'in_stock' => Device::where('status', 'in_stock')->count(),
                'under_repair' => Device::where('status', 'under_repair')->count(),
                'warranty_expiring' => Device::where('status', 'deployed')
                    ->whereBetween('warranty_until', [now(), now()->addDays(90)])->count(),
            ],

            // Alerting.
            'alert_counts' => [
                'active' => Alert::whereNull('resolved_at')->count(),
                'critical' => Alert::whereNull('resolved_at')
                    ->whereHas('rule', fn ($r) => $r->where('severity', 'critical'))->count(),
            ],
            'active_alerts' => Alert::query()
                ->whereNull('resolved_at')
                ->with(['rule:id,name,severity', 'site:id,location_name'])
                ->orderByDesc('triggered_at')
                ->take(6)
                ->get(['id', 'rule_id', 'site_id', 'triggered_at', 'context'])
                ->map(fn ($alert) => [
                    'id' => $alert->id,
                    'severity' => $alert->rule->severity ?? 'info',
                    'rule' => $alert->rule?->name,
                    'site' => $alert->site?->location_name,
                    'observed' => data_get($alert->context, 'observed'),
                    'triggered_at' => $alert->triggered_at->toDateTimeString(),
                ]),

            // Open DOWN episodes — longest suffering first.
            'down_episodes' => SiteStatusEvent::query()
                ->whereNull('resolved_at')
                ->with('site:id,location_name,municipality,province')
                ->orderBy('started_at')
                ->take(6)
                ->get(['id', 'site_id', 'to_status', 'started_at', 'cause'])
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'site' => $event->site?->location_name,
                    'where' => trim(($event->site->municipality ?? '').', '.($event->site->province ?? ''), ', '),
                    'status' => $event->to_status,
                    'cause' => $event->cause,
                    'started_at' => $event->started_at->toDateTimeString(),
                    'duration_h' => (int) $event->started_at->diffInHours(now()),
                ]),
        ];
    }

    /** NOC wallboard payload — big numbers + who's down right now. */
    /** @return array<string, mixed> */
    public function getWallboardStats(): array
    {
        // DOWN_SERVER is a down site too — matching SiteController's "down"
        // filter and SiteStatusEvent::DOWN_STATUSES.
        $downSites = Site::query()
            ->whereHas('latestDailyStatus', fn ($q) => $q->whereIn('status', SiteStatusEvent::DOWN_STATUSES))
            ->orderBy('location_name')
            ->get(['id', 'location_name', 'municipality', 'province']);

        $todayCounts = SiteDailyStatus::whereDate('date', today())
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'total_sites' => Site::where('status', 'active')->count(),
            'up_today' => (int) $todayCounts->get('UP', 0),
            'down_today' => (int) $todayCounts->get('DOWN', 0),
            'down_server_today' => (int) $todayCounts->get('DOWN_SERVER', 0),
            'no_nms_today' => (int) $todayCounts->get('NO_NMS', 0),
            // Was counted against UP+DOWN rows only, so a NO_NMS or
            // DOWN_SERVER report looked like "no report at all".
            'no_data_today' => max(
                0,
                Site::where('status', 'active')->count() - $this->reportedSiteCount(today()),
            ),
            'uptime_pct_7d' => $this->uptimePct(now()->subDays(6)->startOfDay(), now()->endOfDay()),
            'trend' => $this->dailyTrend(14),
            'down_sites' => $downSites,
            'active_alerts' => Alert::query()
                ->whereNull('resolved_at')
                ->with(['rule:id,name,severity', 'site:id,location_name'])
                ->orderByDesc('triggered_at')
                ->take(8)
                ->get(['id', 'rule_id', 'site_id', 'triggered_at', 'context'])
                ->map(fn ($alert) => [
                    'id' => $alert->id,
                    'severity' => $alert->rule->severity ?? 'info',
                    'rule' => $alert->rule?->name,
                    'site' => $alert->site?->location_name,
                    'observed' => data_get($alert->context, 'observed'),
                    'triggered_at' => $alert->triggered_at->toDateTimeString(),
                ]),
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /** @return list<TrendDay>  */
    private function dailyTrend(int $days): array
    {
        $start = today()->subDays($days - 1);

        // Every observed status gets its own series. Folding NO_NMS and
        // DOWN_SERVER into "other" is what made a fifth of the fleet invisible.
        $rows = SiteDailyStatus::whereBetween('date', [$start, today()])
            ->selectRaw("date,
                SUM(CASE WHEN status = 'UP' THEN 1 ELSE 0 END) AS up_count,
                SUM(CASE WHEN status = 'DOWN' THEN 1 ELSE 0 END) AS down_count,
                SUM(CASE WHEN status = 'NO_NMS' THEN 1 ELSE 0 END) AS no_nms_count,
                SUM(CASE WHEN status = 'DOWN_SERVER' THEN 1 ELSE 0 END) AS down_server_count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        return collect(range(0, $days - 1))->map(function ($i) use ($rows, $start) {
            $date = $start->copy()->addDays($i);
            $row = $rows->get($date->toDateString());

            return [
                'date' => $date->format('M j'),
                'up' => (int) ($row->up_count ?? 0),
                'down' => (int) ($row->down_count ?? 0),
                'no_nms' => (int) ($row->no_nms_count ?? 0),
                'down_server' => (int) ($row->down_server_count ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Uptime over a window: UP as a share of every *observed* status.
     *
     * Previously only UP and DOWN were counted, which silently discarded
     * NO_NMS and DOWN_SERVER — 19.3% of all rows — and inflated the figure.
     * See config/daily_status.php.
     */
    private function uptimePct(CarbonInterface $from, CarbonInterface $to): float
    {
        $counts = SiteDailyStatus::whereBetween('date', [$from, $to])
            ->whereIn('status', config('daily_status.observed'))
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $observed = (int) $counts->sum();
        $up = (int) ($counts->get('UP') ?? 0);

        return $observed > 0 ? round($up / $observed * 100, 1) : 0.0;
    }

    /**
     * Distinct sites that reported an observed status on a day.
     *
     * Counts sites, not rows, and ignores NO_DATA — otherwise the 23:00 snapshot
     * would make a site with no real report look like it had reported.
     */
    private function reportedSiteCount(CarbonInterface $date): int
    {
        return (int) SiteDailyStatus::whereDate('date', $date)
            ->whereIn('status', config('daily_status.observed'))
            ->distinct()
            ->count('site_id');
    }
}
