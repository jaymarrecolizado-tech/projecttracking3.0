<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertRule;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Alerts console (Plan backlog: alerts UI). Viewing/acknowledging/resolving
 * requires daily.approve — the same audience the notifications go to. Rule
 * management is admin-only (users.manage).
 */
class AlertController extends Controller
{
    private const METRICS = ['offline_minutes', 'latency_ms', 'cpu_pct', 'mem_pct', 'clients', 'rx_mbps', 'tx_mbps', 'battery_v', 'bandwidth_pct'];

    public function index(Request $request)
    {
        $alerts = Alert::query()
            ->with(['rule:id,name,metric,operator,threshold,severity', 'site:id,location_name,municipality,province', 'device:id,asset_tag', 'acknowledger:id,name'])
            ->when($request->input('state') === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when(in_array($request->input('state'), [null, '', 'active'], true), fn ($q) => $q->whereNull('resolved_at'))
            ->when($request->input('severity'), fn ($q, $v) => $q->whereHas('rule', fn ($r) => $r->where('severity', $v)))
            ->orderByRaw('resolved_at IS NOT NULL, triggered_at DESC')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Alerts/Index', [
            'alerts' => $alerts,
            'filters' => $request->only(['state', 'severity']),
            'counts' => [
                'active' => Alert::whereNull('resolved_at')->count(),
                'critical' => Alert::whereNull('resolved_at')->whereHas('rule', fn ($r) => $r->where('severity', 'critical'))->count(),
                'unacknowledged' => Alert::whereNull('resolved_at')->whereNull('acknowledged_at')->count(),
            ],
            'rules' => AlertRule::orderBy('name')->get(),
            'canManageRules' => $request->user()->hasPermission('users.manage'),
        ]);
    }

    public function acknowledge(Request $request, Alert $alert)
    {
        abort_if($alert->resolved_at !== null, 409);

        $alert->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(Alert $alert)
    {
        $alert->update(['resolved_at' => now()]);

        return back()->with('success', 'Alert resolved.');
    }

    public function storeRule(Request $request)
    {
        $rule = AlertRule::create($this->validated($request));

        return back()->with('success', "Rule '{$rule->name}' created.");
    }

    public function updateRule(Request $request, AlertRule $rule)
    {
        $rule->update($this->validated($request));

        return back()->with('success', "Rule '{$rule->name}' updated.");
    }

    public function destroyRule(AlertRule $rule)
    {
        $name = $rule->name;
        $rule->delete();

        return back()->with('success', "Rule '{$name}' deleted.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'metric' => 'required|in:'.implode(',', self::METRICS),
            'operator' => 'required|in:<,<=,>,>=,==',
            'threshold' => 'required|numeric',
            'duration_minutes' => 'required|integer|min:0|max:1440',
            'severity' => 'required|in:info,warning,critical',
            'notify_roles' => 'nullable|array',
            'notify_roles.*' => 'string|max:60',
            'is_active' => 'required|boolean',
        ]);
    }
}
