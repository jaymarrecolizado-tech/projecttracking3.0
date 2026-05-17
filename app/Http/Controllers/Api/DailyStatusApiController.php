<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteDailyStatus;
use Illuminate\Http\Request;
class DailyStatusApiController extends Controller
{
    public function index(Request $request)
    {
        $query = SiteDailyStatus::with('site:id,location_name');
        if ($request->date) $query->whereDate('date', $request->date);
        if ($request->status) $query->where('status', $request->status);
        if ($request->project_id) $query->whereHas('site', fn($q) => $q->where('project_id', $request->project_id));
        return response()->json($query->paginate($request->per_page ?? 50));
    }
    public function bySite(Site $site, Request $request)
    {
        $statuses = $site->dailyStatuses()
            ->when($request->date, fn($q, $d) => $q->whereDate('date', $d))
            ->latest('date')
            ->paginate($request->per_page ?? 31);
        return response()->json($statuses);
    }
}
