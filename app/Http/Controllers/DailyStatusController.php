<?php
namespace App\Http\Controllers;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use App\Http\Requests\StoreDailyStatusRequest;
use Illuminate\Http\Request;
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
    public function batchStore(Request $request)
    {
        $data = $request->validate([
            'entries' => 'required|array',
            'entries.*.site_id' => 'required|exists:sites,id',
            'entries.*.date' => 'required|date',
            'entries.*.status' => 'required|in:UP,DOWN,NO_DATA',
            'entries.*.total_unique_users' => 'nullable|integer|min:0',
            'entries.*.bandwidth_utilization_mbps' => 'nullable|numeric|min:0',
            'entries.*.uptime_percent' => 'nullable|numeric|between:0,100',
        ]);
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
