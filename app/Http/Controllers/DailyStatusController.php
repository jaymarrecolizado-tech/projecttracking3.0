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
        SiteDailyStatus::create($request->safe()->except(['entry_status']) + [
            'entry_status' => 'DRAFT',
            'created_by' => auth()->id(),
        ]);

        return redirect()->back();
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
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach ($data['entries'] as $entry) {
                SiteDailyStatus::updateOrCreate(
                    ['site_id' => $entry['site_id'], 'date' => $entry['date']],
                    $entry + ['created_by' => auth()->id()]
                );
            }
        });

        return redirect()->back()->with('success', 'Batch statuses saved.');
    }
}
