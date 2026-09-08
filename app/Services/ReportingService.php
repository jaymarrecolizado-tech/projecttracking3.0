<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\FreewifiImportBatch;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;

class ReportingService
{
    public function generateProjectSummaryPdf(Project $project): \Barryvdh\DomPDF\PDF
    {
        $sites = $project->sites()->with('latestDailyStatus')->get();
        $stats = [
            'total' => $sites->count(),
            'active' => $sites->where('status', 'active')->count(),
            'inactive' => $sites->where('status', 'inactive')->count(),
            'planned' => $sites->where('status', 'planned')->count(),
        ];
        if ($project->report_type === 'freewifi') {
            $upCount = SiteDailyStatus::whereHas('site', fn ($q) => $q->where('project_id', $project->id))
                ->where('status', 'UP')->whereDate('date', today())->count();
            $stats['up_today'] = $upCount;
        }

        return Pdf::loadView('reports.project-summary', compact('project', 'sites', 'stats'));
    }

    public function generateProvinceReport(string $province, ?int $projectId = null): \Barryvdh\DomPDF\PDF
    {
        $query = Site::where('province', $province)->with('project');
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        $sites = $query->get();
        $grouped = $sites->groupBy(fn ($s) => $s->municipality ?? 'Unknown');

        return Pdf::loadView('reports.province-summary', compact('province', 'sites', 'grouped'));
    }

    /** Site Type coverage (actual vs registered) — same data as /map/coverage. */
    public function generateSiteTypeCoverageReport(array $filters): \Barryvdh\DomPDF\PDF
    {
        $coverage = app(SiteCoverageService::class)->coverage($filters);
        $sites = collect();
        if (($coverage['totals']['actual'] ?? 0) <= 200) {
            $query = Site::query()->whereHas('activeDeployments')->with(['project:id,code,name', 'activeDeployments.device:id,asset_tag']);
            foreach (['province', 'district', 'municipality', 'barangay'] as $column) {
                if (! empty($filters[$column])) {
                    $query->where("sites.{$column}", $filters[$column]);
                }
            }
            if (! empty($filters['project_id'])) {
                $query->where('sites.project_id', $filters['project_id']);
            }
            $sites = $query->orderBy('site_type')->orderBy('location_name')->get();
        }

        return Pdf::loadView('reports.site-type-coverage', [
            'coverage' => $coverage,
            'sites' => $sites,
            'filters' => $coverage['filters'],
        ]);
    }

    /** Barangay coverage (installed/existing vs total) — same data as /map/barangay-coverage. */
    public function generateBarangayCoverageReport(array $filters): \Barryvdh\DomPDF\PDF
    {
        $coverage = app(BarangayCoverageService::class)->coverage($filters);

        return Pdf::loadView('reports.barangay-coverage', [
            'coverage' => $coverage,
            'filters' => $coverage['filters'],
        ]);
    }

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
