<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\MaintenanceTicket;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Scoped KPI bundle for the analytics PDF family (Plan.md).
 *
 * One scope in → every figure out: site mix, daily-status mix at the period
 * end, uptime + trend over the period, coverage snapshots, fleet, open DOWN
 * episodes, active alerts, open tickets. Uptime follows config/daily_status.php
 * (UP over every observed status); charts stay HTML/CSS bars because DomPDF
 * cannot run JS.
 *
 * The shapes below are the contract every pack, narrative and Blade reads, so
 * they are declared once here and imported rather than re-guessed per caller.
 *
 * @phpstan-import-type SiteTotals from \App\Services\SiteCoverageService
 * @phpstan-import-type BarangayTotals from \App\Services\BarangayCoverageService
 *
 * @phpstan-type ScopeParams array{project_id?: mixed, project?: string|null, from?: string|null, to?: string|null, province?: mixed, district?: mixed, municipality?: mixed, barangay?: mixed, site_type?: mixed, status?: mixed, region?: mixed}
 * @phpstan-type TrendDay array{date: string, up: int, down: int, no_nms: int, down_server: int}
 * @phpstan-type DownEpisode array{site: string, where: string, status: string, cause: string|null, started_at: string, duration_h: int}
 * @phpstan-type AlertRow array{severity: string, rule: string, site: string, triggered_at: string}
 * @phpstan-type TicketRow array{site: string, title: string|null, status: string, priority: string, created_at: string}
 * @phpstan-type Bundle array{
 *   from: string, to: string, scope: string,
 *   sites: array{total: int, active: int, inactive: int, planned: int},
 *   daily: array{up: int, down: int, down_server: int, no_nms: int, no_data: int, reported: int, progress_pct: float},
 *   uptime_pct: float, uptime_base: int,
 *   sla_target: float|null, sla_met: bool|null,
 *   trend: list<TrendDay>,
 *   site_coverage: SiteTotals, barangay_coverage: BarangayTotals,
 *   fleet: array{deployed: int, in_stock: int, under_repair: int, warranty_expiring: int},
 *   down_episodes: list<DownEpisode>,
 *   alerts: array{active: int, critical: int, by_severity: array<string, int>, latest: list<AlertRow>},
 *   tickets: array{open: int, critical_open: int, latest: list<TicketRow>}
 * }
 */
class ReportAnalytics
{
    /** Hard ceiling on the trend window — bars past a month are unreadable. */
    private const MAX_TREND_DAYS = 31;

    /**
     * @param  ScopeParams  $params
     * @return array{0: Carbon, 1: Carbon} inclusive [from, to], default last 7d.
     */
    public function period(array $params): array
    {
        $to = isset($params['to']) ? Carbon::parse($params['to'])->startOfDay() : today();
        $from = isset($params['from'])
            ? Carbon::parse($params['from'])->startOfDay()
            : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) >= self::MAX_TREND_DAYS) {
            $from = $to->copy()->subDays(self::MAX_TREND_DAYS - 1);
        }

        return [$from, $to];
    }

    /**
     * @param  ScopeParams  $params
     */
    public function describeScope(array $params): string
    {
        [$from, $to] = $this->period($params);
        $parts = [];
        foreach (['project' => 'Project', 'province' => 'Province', 'district' => 'District',
            'municipality' => 'Municipality', 'barangay' => 'Barangay',
            'site_type' => 'Site type', 'status' => 'Status'] as $key => $label) {
            if (! empty($params[$key])) {
                $parts[] = "{$label}: {$params[$key]}";
            }
        }
        $parts[] = $from->equalTo($to)
            ? 'Date: '.$to->format('M j, Y')
            : 'Period: '.$from->format('M j, Y').' – '.$to->format('M j, Y');

        return implode(' · ', $parts);
    }

    /**
     * Site ids in scope as a subquery — every KPI below reads through it.
     *
     * @param  ScopeParams  $params
     * @return Builder<Site>
     */
    public function siteIds(array $params): Builder
    {
        $query = Site::query()->select('sites.id');
        if (! empty($params['project_id'])) {
            $query->where('sites.project_id', $params['project_id']);
        }
        foreach (['status', 'province', 'district', 'municipality', 'barangay', 'site_type'] as $column) {
            if (! empty($params[$column])) {
                $query->where("sites.{$column}", $params[$column]);
            }
        }

        return $query;
    }

    /**
     * @param  ScopeParams  $params
     * @return Bundle
     */
    public function for(array $params): array
    {
        [$from, $to] = $this->period($params);
        $ids = $this->siteIds($params);

        $siteMix = (clone $ids)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $totalSites = (int) $siteMix->sum();
        $activeSites = (int) $siteMix->get('active', 0);

        $dailyMix = SiteDailyStatus::whereIn('site_id', (clone $ids))
            ->whereDate('date', $to)
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');
        $reported = SiteDailyStatus::whereIn('site_id', (clone $ids))
            ->whereDate('date', $to)
            ->whereIn('status', config('daily_status.observed'))
            ->distinct()->count('site_id');

        $uptimeCounts = SiteDailyStatus::whereIn('site_id', (clone $ids))
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', config('daily_status.observed'))
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');
        $observed = (int) $uptimeCounts->sum();
        $uptime = $observed > 0
            ? round((int) $uptimeCounts->get('UP', 0) / $observed * 100, 1)
            : 0.0;

        // Null target, null verdict: with no confirmed SLA there is nothing to
        // pass or fail, and printing a dash reads as "no target set" rather than
        // "target missed". is_numeric guards a typo in the env value.
        $slaTarget = config('monitoring.sla_uptime_target');
        $slaTarget = is_numeric($slaTarget) ? (float) $slaTarget : null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $this->describeScope($params),
            'sites' => [
                'total' => $totalSites,
                'active' => $activeSites,
                'inactive' => (int) $siteMix->get('inactive', 0),
                'planned' => (int) $siteMix->get('planned', 0),
            ],
            'daily' => [
                'up' => (int) $dailyMix->get('UP', 0),
                'down' => (int) $dailyMix->get('DOWN', 0),
                'down_server' => (int) $dailyMix->get('DOWN_SERVER', 0),
                'no_nms' => (int) $dailyMix->get('NO_NMS', 0),
                'no_data' => max(0, $activeSites - $reported),
                'reported' => $reported,
                'progress_pct' => $activeSites > 0 ? round($reported / $activeSites * 100, 1) : 0.0,
            ],
            'uptime_pct' => $uptime,
            'uptime_base' => $observed,
            'sla_target' => $slaTarget,
            'sla_met' => $slaTarget === null ? null : $uptime >= $slaTarget,
            'trend' => $this->trend($ids, $from, $to),
            'site_coverage' => app(SiteCoverageService::class)->coverage($this->coverageFilters($params))['totals'],
            'barangay_coverage' => app(BarangayCoverageService::class)->coverage($this->coverageFilters($params))['totals'],
            'fleet' => $this->fleet($ids),
            'down_episodes' => $this->downEpisodes($ids),
            'alerts' => $this->alerts($ids),
            'tickets' => $this->tickets($ids),
        ];
    }

    /**
     * The coverage services take fewer filters than siteIds() (no status, no
     * site type) so a coverage figure always describes the whole area.
     *
     * @param  ScopeParams  $params
     * @return ScopeParams
     */
    private function coverageFilters(array $params): array
    {
        return array_filter([
            'project_id' => $params['project_id'] ?? null,
            'province' => $params['province'] ?? null,
            'district' => $params['district'] ?? null,
            'municipality' => $params['municipality'] ?? null,
            'barangay' => $params['barangay'] ?? null,
        ]);
    }

    /**
     * @param  Builder<Site>  $ids
     * @return list<TrendDay>
     */
    private function trend(Builder $ids, Carbon $from, Carbon $to): array
    {
        $rows = SiteDailyStatus::whereIn('site_id', (clone $ids))
            ->whereBetween('date', [$from, $to])
            ->selectRaw("date,
                SUM(CASE WHEN status = 'UP' THEN 1 ELSE 0 END) AS up_count,
                SUM(CASE WHEN status = 'DOWN' THEN 1 ELSE 0 END) AS down_count,
                SUM(CASE WHEN status = 'NO_NMS' THEN 1 ELSE 0 END) AS no_nms_count,
                SUM(CASE WHEN status = 'DOWN_SERVER' THEN 1 ELSE 0 END) AS down_server_count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        $out = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $out[] = [
                'date' => $date->format('M j'),
                'up' => (int) ($row->up_count ?? 0),
                'down' => (int) ($row->down_count ?? 0),
                'no_nms' => (int) ($row->no_nms_count ?? 0),
                'down_server' => (int) ($row->down_server_count ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  Builder<Site>  $ids
     * @return array{deployed: int, in_stock: int, under_repair: int, warranty_expiring: int}
     */
    private function fleet(Builder $ids): array
    {
        return [
            'deployed' => Device::where('status', 'deployed')
                ->whereHas('currentDeployment', fn ($q) => $q->whereIn('site_id', (clone $ids)))
                ->count(),
            'in_stock' => Device::where('status', 'in_stock')->count(),
            'under_repair' => Device::where('status', 'under_repair')->count(),
            'warranty_expiring' => Device::where('status', 'deployed')
                ->whereBetween('warranty_until', [now(), now()->addDays(90)])->count(),
        ];
    }

    /**
     * Open DOWN episodes, worst-duration first.
     *
     * A plain list, not a Collection: Laravel's Eloquent Collection::map()
     * returns a union type that static analysis cannot match against a declared
     * shape, and the sibling `alerts.latest` / `tickets.latest` keys are lists
     * too — so this keeps the whole bundle one shape.
     *
     * @param  Builder<Site>  $ids
     * @return list<DownEpisode>
     */
    private function downEpisodes(Builder $ids): array
    {
        return SiteStatusEvent::query()
            ->whereIn('site_id', (clone $ids))
            ->whereNull('resolved_at')
            ->with('site:id,location_name,municipality,province')
            ->orderBy('started_at')
            ->take(10)
            ->get(['id', 'site_id', 'to_status', 'started_at', 'cause'])
            ->map(function (SiteStatusEvent $event): array {
                return [
                    'site' => (string) data_get($event->site, 'location_name', '—'),
                    'where' => trim(($event->site->municipality ?? '').', '.($event->site->province ?? ''), ', '),
                    'status' => $event->to_status,
                    'cause' => $event->cause,
                    'started_at' => $event->started_at->toDateTimeString(),
                    'duration_h' => (int) $event->started_at->diffInHours(now()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Site>  $ids
     * @return array{active: int, critical: int, by_severity: array<string, int>, latest: list<AlertRow>}
     */
    private function alerts(Builder $ids): array
    {
        $base = Alert::query()->whereIn('site_id', (clone $ids))->whereNull('resolved_at');
        $bySeverity = (clone $base)
            ->join('alert_rules', 'alert_rules.id', '=', 'alerts.rule_id')
            ->selectRaw('alert_rules.severity, COUNT(*) AS n')
            ->groupBy('alert_rules.severity')
            ->pluck('n', 'severity');

        return [
            'active' => (int) $bySeverity->sum(),
            'critical' => (int) $bySeverity->get('critical', 0),
            'by_severity' => $bySeverity->all(),
            'latest' => (clone $base)
                ->with(['rule:id,name,severity', 'site:id,location_name'])
                ->orderByDesc('triggered_at')->take(10)
                ->get(['id', 'rule_id', 'site_id', 'triggered_at'])
                ->map(fn ($alert) => [
                    'severity' => $alert->rule->severity ?? 'info',
                    'rule' => data_get($alert->rule, 'name', '—'),
                    'site' => data_get($alert->site, 'location_name', '—'),
                    'triggered_at' => $alert->triggered_at->toDateTimeString(),
                ])->all(),
        ];
    }

    /**
     * @param  Builder<Site>  $ids
     * @return array{open: int, critical_open: int, latest: list<TicketRow>}
     */
    private function tickets(Builder $ids): array
    {
        $base = MaintenanceTicket::query()
            ->whereIn('site_id', (clone $ids))
            ->whereIn('status', ['OPEN', 'IN_PROGRESS']);

        return [
            'open' => (clone $base)->count(),
            'critical_open' => (clone $base)->where('priority', 'critical')->count(),
            'latest' => (clone $base)
                ->with('site:id,location_name')
                ->orderByDesc('created_at')->take(10)
                ->get(['id', 'site_id', 'title', 'status', 'priority', 'created_at'])
                ->map(fn ($ticket) => [
                    'site' => data_get($ticket->site, 'location_name', '—'),
                    'title' => $ticket->title,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'created_at' => $ticket->created_at->toDateString(),
                ])->all(),
        ];
    }
}
