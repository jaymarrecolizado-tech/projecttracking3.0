<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDailyOpsRequest;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * The Daily Ops Board: one screen to record today's UP/DOWN/NO NMS for every
 * site the user may touch. Replaces row-by-row entry for 1,100+ sites.
 */
class DailyOpsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $date = $request->input('date', today()->toDateString());

        // Only projects whose daily data this user may view.
        $projects = Project::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'marker_color'])
            ->filter(fn (Project $p) => $user->hasPermission('daily.view', $p->id))
            ->values();

        $allowedProjectIds = $projects->pluck('id');

        $sitesQuery = Site::query()
            ->whereIn('project_id', $allowedProjectIds)
            ->orderBy('location_name');

        if ($request->filled('project_id') && $allowedProjectIds->contains((int) $request->input('project_id'))) {
            $sitesQuery->where('project_id', (int) $request->input('project_id'));
        }
        if ($request->filled('province')) {
            $sitesQuery->where('province', $request->input('province'));
        }

        $sites = $sitesQuery
            ->with(['project:id,name,marker_color', 'dailyStatuses' => fn ($q) => $q->whereDate('date', $date)])
            ->get([
                'id', 'ap_site_code', 'location_name', 'barangay', 'municipality',
                'province', 'project_id', 'status as site_status',
            ]);

        $rows = $sites->map(function (Site $site) {
            $record = $site->dailyStatuses->first();

            return [
                'site_id' => $site->id,
                'ap_site_code' => $site->ap_site_code,
                'location_name' => $site->location_name,
                'municipality' => $site->municipality,
                'province' => $site->province,
                'project_name' => $site->project->name,
                'marker_color' => $site->project->marker_color,
                'entry_status' => $record?->entry_status,
                'status' => $record?->status,
                'bandwidth_utilization_mbps' => $record?->bandwidth_utilization_mbps,
                'total_unique_users' => $record?->total_unique_users,
                'remarks' => $record?->notes,
            ];
        })->values();

        return Inertia::render('DailyOps/Index', [
            'date' => $date,
            'rows' => $rows,
            'projects' => $projects,
            'provinces' => Site::whereIn('project_id', $allowedProjectIds)
                ->whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
            'counts' => [
                'total' => $rows->count(),
                'reported' => $rows->whereNotNull('status')->count(),
            ],
            'filters' => $request->only(['project_id', 'province']),
        ]);
    }

    public function batch(BulkDailyOpsRequest $request)
    {
        $user = $request->user();
        $action = $request->validated('action');
        $date = $request->validated('date');
        $entries = $request->validated('entries');

        $saved = 0;
        $skipped = [];

        DB::transaction(function () use ($entries, $action, $date, $user, &$saved, &$skipped) {
            foreach ($entries as $entry) {
                $site = Site::find($entry['site_id']);
                if (! $site) {
                    continue;
                }

                $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', $date)->first();

                if ($existing && $existing->entry_status === 'LOCKED') {
                    $skipped[] = $site->ap_site_code.' is locked';

                    continue;
                }

                if ($action === 'approve') {
                    if (! $existing || ! $user->hasPermission('daily.approve', $site->project_id)) {
                        $skipped[] = $site->ap_site_code.' cannot be approved by you';

                        continue;
                    }
                } elseif ($existing && in_array($existing->entry_status, ['APPROVED'], true)) {
                    if (! $user->hasPermission('daily.approve', $site->project_id)) {
                        $skipped[] = $site->ap_site_code.' is approved and locked for you';

                        continue;
                    }
                } else {
                    $permission = $existing ? 'daily.edit' : 'daily.create';
                    if (! $user->hasPermission($permission, $site->project_id)) {
                        $skipped[] = $site->ap_site_code.' not permitted';

                        continue;
                    }
                }

                $attributes = [
                    'status' => $entry['status'],
                    'bandwidth_utilization_mbps' => $entry['bandwidth_utilization_mbps'] ?? null,
                    'total_unique_users' => $entry['total_unique_users'] ?? null,
                    'notes' => $entry['remarks'] ?? null,
                ];

                // Workflow transitions — APPROVED rows only move via approve action.
                $statusMap = [
                    'save_draft' => ['entry_status' => 'DRAFT'],
                    'submit' => ['entry_status' => 'SUBMITTED', 'submitted_at' => now()],
                    'approve' => ['entry_status' => 'APPROVED', 'approved_by' => $user->id, 'approved_at' => now()],
                ];

                if ($existing) {
                    $merged = $attributes + $statusMap[$action];
                    // Editing content of an APPROVED row keeps its approved state.
                    if ($action !== 'approve' && $existing->entry_status === 'APPROVED') {
                        unset($merged['submitted_at']);
                        $merged['entry_status'] = 'APPROVED';
                    }
                    $existing->fill($merged)->save();
                } else {
                    SiteDailyStatus::create($attributes + $statusMap[$action] + [
                        'site_id' => $site->id,
                        'date' => $date,
                        'created_by' => $user->id,
                    ]);
                }
                $saved++;
            }
        });

        $message = "Saved {$saved} entr".($saved === 1 ? 'y' : 'ies').'.';
        if ($skipped) {
            $message .= ' Skipped: '.implode('; ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : '');

            return redirect()->route('daily-ops.index', ['date' => $date])
                ->with('error', $message);
        }

        return redirect()->route('daily-ops.index', ['date' => $date])->with('success', $message);
    }
}
