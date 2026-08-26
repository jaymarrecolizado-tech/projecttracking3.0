<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchStoreDailyStatusRequest;
use App\Http\Requests\StoreDailyStatusRequest;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Support\Facades\DB;
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
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        SiteDailyStatus::create($data);

        return redirect()->back();
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
