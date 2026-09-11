<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchStoreDailyStatusRequest;
use App\Http\Requests\StoreDailyStatusRequest;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DailyStatusController extends Controller
{
    public function index()
    {
        $statuses = SiteDailyStatus::with('site:id,location_name,project_id', 'site.project:id,code,name')
            ->latest('date')->paginate(20);

        return Inertia::render('FreeWifi/DailyGrid', ['statuses' => $statuses]);
    }

    public function store(StoreDailyStatusRequest $request)
    {
        // entry_status never comes from the client (Plan_revision §Phase 2.2):
        // hand-entered rows start as DRAFT and move through the workflow
        // endpoints (approve/lock) gated on daily.approve.
        $data = $request->safe()->except(['entry_status']);
        $site = Site::findOrFail($data['site_id']);
        $user = $request->user();

        $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', $data['date'])->first();

        // Same guards as batchStore and the Daily Ops board: LOCKED rows are
        // immutable, APPROVED rows need an approver, and everything else
        // resolves through per-project create/edit grants.
        if ($existing && $existing->entry_status === 'LOCKED') {
            return redirect()->back()->with('error', 'That record is locked.');
        }
        if ($existing && $existing->entry_status === 'APPROVED') {
            abort_unless($user->hasPermission('daily.approve', $site->project_id), 403);
        } elseif (! $user->hasPermission($existing ? 'daily.edit' : 'daily.create', $site->project_id)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        if ($existing) {
            $existing->update($data);
        } else {
            SiteDailyStatus::create($data + [
                'entry_status' => 'DRAFT',
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->back()->with('success', 'Status saved.');
    }

    public function approve(Request $request, SiteDailyStatus $status)
    {
        Gate::authorize('approve', $status);

        abort_if($status->entry_status === 'LOCKED', 409, 'Record is locked.');

        $status->update([
            'entry_status' => 'APPROVED',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Record approved.');
    }

    public function lock(Request $request, SiteDailyStatus $status)
    {
        Gate::authorize('approve', $status);

        $status->update([
            'entry_status' => 'LOCKED',
            'approved_by' => $status->approved_by ?? $request->user()->id,
            'approved_at' => $status->approved_at ?? now(),
        ]);

        return redirect()->back()->with('success', 'Record locked.');
    }

    public function grid(Site $site)
    {
        $statuses = $site->dailyStatuses()->latest('date')->paginate(31);

        return Inertia::render('FreeWifi/DailyGrid', ['site' => $site, 'statuses' => $statuses]);
    }

    public function batchStore(BatchStoreDailyStatusRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $saved = 0;
        $skipped = [];

        DB::transaction(function () use ($data, $user, &$saved, &$skipped) {
            foreach ($data['entries'] as $entry) {
                $site = Site::find($entry['site_id']);
                if (! $site) {
                    continue;
                }

                $existing = SiteDailyStatus::where('site_id', $site->id)->whereDate('date', $entry['date'])->first();

                // Same guards as the Daily Ops board: LOCKED rows are
                // immutable, APPROVED rows need an approver, and everything
                // else resolves through per-project create/edit grants.
                if ($existing && $existing->entry_status === 'LOCKED') {
                    $skipped[] = $site->ap_site_code.' is locked';

                    continue;
                }
                if ($existing && $existing->entry_status === 'APPROVED') {
                    if (! $user->hasPermission('daily.approve', $site->project_id)) {
                        $skipped[] = $site->ap_site_code.' is approved and locked for you';

                        continue;
                    }
                } elseif (! $user->hasPermission($existing ? 'daily.edit' : 'daily.create', $site->project_id)) {
                    $skipped[] = $site->ap_site_code.' not permitted';

                    continue;
                }

                SiteDailyStatus::updateOrCreate(
                    ['site_id' => $entry['site_id'], 'date' => $entry['date']],
                    $entry + ['created_by' => auth()->id()]
                );
                $saved++;
            }
        });

        $message = "Saved {$saved} entr".($saved === 1 ? 'y' : 'ies').'.';
        if ($skipped) {
            $message .= ' Skipped: '.implode('; ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? '…' : '');

            return redirect()->back()->with('error', $message);
        }

        return redirect()->back()->with('success', $message);
    }
}
