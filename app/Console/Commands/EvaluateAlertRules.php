<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\DeviceMetric;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Models\User;
use App\Services\Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Rules engine for device_metrics (docs §4.3): each active rule is evaluated
 * over its duration window; violations open alerts (deduped per rule+site)
 * and notify email + Telegram; recovered windows auto-resolve open alerts.
 */
class EvaluateAlertRules extends Command
{
    protected $signature = 'alerts:evaluate';

    protected $description = 'Evaluate alert rules against device metrics and daily statuses';

    /** metric key => SQL column on device_metrics */
    private const METRIC_COLUMNS = [
        'latency_ms' => 'latency_ms',
        'cpu_pct' => 'cpu_pct',
        'mem_pct' => 'mem_pct',
        'clients' => 'clients',
        'rx_mbps' => 'rx_mbps',
        'tx_mbps' => 'tx_mbps',
        'battery_v' => 'battery_v',
    ];

    public function __construct(private Telegram $telegram)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rules = AlertRule::where('is_active', true)->get();
        $fired = 0;
        $resolved = 0;

        foreach ($rules as $rule) {
            foreach ($this->sitesUnderWatch($rule) as $site) {
                $series = $this->seriesFor($rule, $site);
                $violating = $this->violatingPoints($rule, $series);

                // Instant rules (no duration) key off the LATEST reading only —
                // a stale violating sample must not hold the alert open after a
                // healthy one arrives.
                if ($rule->duration_minutes === 0) {
                    $latestPoint = $series === [] ? null : end($series);
                    $held = $latestPoint !== null && $this->compare((float) $latestPoint['value'], $rule->operator, (float) $rule->threshold);
                    $violating = $held ? [$latestPoint] : [];
                }

                $open = Alert::where('rule_id', $rule->id)->where('site_id', $site->id)->whereNull('resolved_at')->first();

                if ($rule->duration_minutes > 0) {
                    // "Held for N minutes": the earliest violating sample is at
                    // least N minutes old, i.e. the condition has been true
                    // continuously across the window.
                    $windowStart = Carbon::now()->subMinutes($rule->duration_minutes);
                    $earliest = collect($violating)->min(fn ($point) => $point['ts']);
                    $held = $earliest !== null && $earliest->lte($windowStart);
                } else {
                    $held = $violating !== [];
                }

                if ($held && ! $open) {
                    $this->fire($rule, $site, $violating);
                    $fired++;
                } elseif ($open && $violating === []) {
                    $open->update(['resolved_at' => now()]);
                    $resolved++;
                }
            }
        }

        $this->info("alerts:evaluate — {$fired} fired, {$resolved} resolved.");

        return self::SUCCESS;
    }

    /** Active sites are always watched (offline rule); metric rules need data. */
    private function sitesUnderWatch(AlertRule $rule)
    {
        return Site::where('status', 'active')->get(['id', 'location_name', 'municipality', 'province', 'bw_download_cir']);
    }

    /** Ordered [(ts, value)] observations for the rule's metric+window. */
    private function seriesFor(AlertRule $rule, Site $site): array
    {
        $since = now()->subMinutes(max($rule->duration_minutes, 15) + 30);

        if ($rule->metric === 'offline_minutes') {
            $last = DeviceMetric::where('site_id', $site->id)->max('ts');
            $minutes = $last ? Carbon::parse($last)->diffInMinutes(now()) : null;

            return $minutes === null ? [] : [['ts' => now(), 'value' => (float) $minutes]];
        }

        if ($rule->metric === 'firmware_outdated') {
            $approved = config('monitoring.approved_firmware');
            if ($approved === [] || $approved === null) {
                return [];
            }

            $latest = DeviceMetric::where('site_id', $site->id)->orderByDesc('ts')->first(['firmware']);
            if (! $latest || ! $latest->firmware) {
                return [];
            }

            $outdated = ! in_array($latest->firmware, (array) $approved, true);

            return [['ts' => now(), 'value' => $outdated ? 1.0 : 0.0]];
        }

        if ($rule->metric === 'bandwidth_pct') {
            $cir = (float) $site->bw_download_cir;
            if ($cir <= 0) {
                return [];
            }
            $latest = SiteDailyStatus::where('site_id', $site->id)
                ->whereDate('date', '>=', $since->toDateString())
                ->orderByDesc('date')->first(['bandwidth_utilization_mbps']);
            if (! $latest || $latest->bandwidth_utilization_mbps === null) {
                return [];
            }

            return [['ts' => now(), 'value' => (float) $latest->bandwidth_utilization_mbps / $cir * 100]];
        }

        $column = self::METRIC_COLUMNS[$rule->metric] ?? null;
        if (! $column) {
            return [];
        }

        return DeviceMetric::where('site_id', $site->id)
            ->where('ts', '>=', $since)
            ->whereNotNull($column)
            ->orderBy('ts')
            ->get(['ts', $column])
            ->map(fn ($row) => ['ts' => Carbon::parse($row->ts), 'value' => (float) $row->{$column}])
            ->all();
    }

    private function violatingPoints(AlertRule $rule, array $series): array
    {
        return array_values(array_filter($series, fn ($point) => $this->compare($point['value'], $rule->operator, (float) $rule->threshold)));
    }

    private function compare(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '<' => $value < $threshold,
            '<=' => $value <= $threshold,
            '>' => $value > $threshold,
            '>=' => $value >= $threshold,
            '==' => abs($value - $threshold) < 0.001,
            default => false,
        };
    }

    private function fire(AlertRule $rule, Site $site, array $violating): void
    {
        $latest = end($violating);

        $alert = Alert::create([
            'rule_id' => $rule->id,
            'site_id' => $site->id,
            'triggered_at' => now(),
            'context' => [
                'observed' => $latest['value'],
                'threshold' => (float) $rule->threshold,
                'operator' => $rule->operator,
                'samples' => count($violating),
            ],
        ]);

        $text = sprintf(
            "%s ALERT — %s\nRule: %s\nObserved: %s (threshold %s %s)\nLocation: %s\n\nManage: %s",
            strtoupper($rule->severity),
            $site->location_name,
            $rule->name,
            $latest['value'],
            $rule->operator,
            $rule->threshold,
            trim(($site->municipality ?? '').', '.($site->province ?? ''), ', '),
            route('sites.show', $site),
        );

        $recipients = $this->recipients($rule);
        if ($recipients !== []) {
            Mail::raw($text, fn ($m) => $m->to($recipients)->subject("[{$rule->severity}] {$site->location_name}: {$rule->name}"));
        }

        if ($rule->severity !== 'info' && $this->telegram->configured()) {
            $this->telegram->sendMessage($text);
        }
    }

    /** Role-holders named by the rule (permissions), plus the watchdog. */
    private function recipients(AlertRule $rule): array
    {
        $recipients = User::query()
            ->when($rule->notify_roles, fn ($q, $roles) => $q->whereHas('roles.permissions', fn ($p) => $p->whereIn('permissions.name', $roles)))
            ->pluck('email')->unique()->values()->all();

        if ($watchdog = config('monitoring.watchdog_email')) {
            $recipients[] = $watchdog;
        }

        return array_unique($recipients);
    }
}
