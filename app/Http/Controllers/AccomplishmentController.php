<?php
namespace App\Http\Controllers;
use App\Models\Site;
use App\Models\SiteAccomplishment;
use App\Http\Requests\StoreAccomplishmentRequest;
use Inertia\Inertia;
class AccomplishmentController extends Controller
{
    public function index()
    {
        $accomplishments = SiteAccomplishment::with('site:id,location_name', 'milestone:id,milestone_name')
            ->latest()->paginate(20);
        return Inertia::render('Accomplishments/Index', ['accomplishments' => $accomplishments]);
    }
    public function store(StoreAccomplishmentRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        SiteAccomplishment::updateOrCreate(
            ['site_id' => $data['site_id'], 'milestone_id' => $data['milestone_id']],
            $data
        );
        return redirect()->back();
    }
    public function bySite(Site $site)
    {
        $site->load('accomplishments.milestone');
        return Inertia::render('Accomplishments/Edit', ['site' => $site]);
    }
}
