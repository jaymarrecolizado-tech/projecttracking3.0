<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\MaintenanceTicket;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\SiteStatusEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Scoped KPI bundle for the analytics PDF family (Plan.md).
 *
 * One scope in → every figure out: site mix, daily-status mix at the period
 * end, uptime + trend over the period, coverage snapshots, fleet, open DOWN
 * episodes, active alerts, open tickets. Uptime follows config/daily_status.php
 * (UP over every observed status); charts stay HTML/CSS bars because DomPDF
 * cannot run JS.
 */
class ReportAnalytics
{
    /** Hard ceiling on the trend window — bars past a month are unreadable. */
    private const MAX_TREND_DAYS = 31;

    /** @return array{0: Carbon, 1: Carbon} inclusive [from, to], default last 7d. */
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

    /** Site ids in scope as a subquery — every KPI below reads through it. */
    public function siteIds(array $params)
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
            'uptime_pct' => $observed > 0
                ? round((int) $uptimeCounts->get('UP', 0) / $observed * 100, 1)
                : 0.0,
            'uptime_base' => $observed,
            'trend' => $this->trend($ids, $from, $to),
            'site_coverage' => app(SiteCoverageService::class)->coverage($this->coverageFilters($params))['totals'],
            'barangay_coverage' => app(BarangayCoverageService::class)->coverage($this->coverageFilters($params))['totals'],
            'fleet' => $this->fleet($ids),
            'down_episodes' => $this->downEpisodes($ids),
            'alerts' => $this->alerts($ids),
            'tickets' => $this->tickets($ids),
        ];
    }

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

    private function trend($ids, Carbon $from, Carbon $to): array
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

    private function fleet($ids): array
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

    private function downEpisodes($ids): Collection
    {
        return SiteStatusEvent::query()
            ->whereIn('site_id', (clone $ids))
            ->whereNull('resolved_at')
            ->with('site:id,location_name,municipality,province')
            ->orderBy('started_at')
            ->take(10)
            ->get(['id', 'site_id', 'to_status', 'started_at', 'cause'])
            ->map(fn ($event) => [
                'site' => data_get($event->site, 'location_name', '—'),
                'where' => trim(($event->site->municipality ?? '').', '.($event->site->province ?? ''), ', '),
                'status' => $event->to_status,
                'cause' => $event->cause,
                'started_at' => $event->started_at->toDateTimeString(),
                'duration_h' => (int) $event->started_at->diffInHours(now()),
            ]);
    }

    private function alerts($ids): array
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

    private function tickets($ids): array
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
