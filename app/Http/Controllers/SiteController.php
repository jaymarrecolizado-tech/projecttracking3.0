<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteSurvey;
use App\Services\SiteSurveyAnalytics;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    /** Placards per printable survey sheet — see surveyQr(). */
    private const QR_SHEET_LIMIT = 200;

    public function index(Request $request, SiteSurveyAnalytics $analytics): Response
    {
        $sites = Site::query()
            ->with(['project:id,code,name,marker_color', 'latestDailyStatus'])
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('location_name', 'like', "%{$v}%")
                ->orWhere('ap_site_code', 'like', "%{$v}%")
                ->orWhere('municipality', 'like', "%{$v}%")
                ->orWhere('barangay', 'like', "%{$v}%")
                ->orWhere('nationwide_id', 'like', "%{$v}%")))
            ->when($request->input('project_id'), fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('province'), fn ($q, $v) => $q->where('province', $v))
            ->when($request->input('today') === 'down', fn ($q) => $q->whereHas('latestDailyStatus', fn ($s) => $s->whereIn('status', ['DOWN', 'DOWN_SERVER'])))
            ->orderBy('location_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            // Satisfaction for the current page only — one grouped query, no N+1.
            'satisfaction' => $analytics->forSites($sites->pluck('id')->all()),
            'filters' => $this->filterPayload($request),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'provinces' => Site::whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
        ]);
    }

    public function show(Site $site, SiteSurveyAnalytics $analytics): Response
    {
        $site->load(['project', 'latestDailyStatus', 'dailyStatuses' => fn ($q) => $q->latest('date')->take(30),
            'activeDeployments.device.deviceModel:id,manufacturer,model_name,model_number']);

        return Inertia::render('Sites/Show', [
            'site' => $site,
            'satisfaction' => $analytics->forSite($site),
            // The public link for this site — the QR the field team prints.
            'surveyUrl' => SiteSurvey::urlFor($site->ap_site_code),
            'deviceModels' => DeviceModel::where('is_active', true)
                ->orderBy('manufacturer')->orderBy('model_name')
                ->get(['id', 'manufacturer', 'model_name', 'model_number']),
            // ponytail: stock picker capped at 200 rows; searchable async select if stock outgrows it.
            'stockDevices' => Device::where('status', 'in_stock')
                ->with('deviceModel:id,manufacturer,model_name')
                ->orderBy('asset_tag')
                ->limit(200)
                ->get(['id', 'asset_tag', 'serial_number', 'device_model_id']),
        ]);
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $site = Site::create($request->validated() + [
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('sites.show', $site);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated() + ['updated_by' => auth()->id()]);

        return redirect()->route('sites.show', $site);
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return redirect()->route('sites.index');
    }

    public function byProject(Request $request, Project $project): Response
    {
        $sites = $project->sites()
            ->with(['latestDailyStatus'])
            ->orderBy('location_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'project' => $project,
            'filters' => $this->filterPayload($request),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'provinces' => Site::whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
        ]);
    }

    /**
     * Printable survey QR placards for a slice of the fleet (Plan.md S8).
     *
     * A plain Blade page, not Inertia and not a queued PDF, for the same reason
     * devices.label is: it has to print clean from the browser, and a QR per
     * site is a few KB of SVG — going through DomPDF would mean rasterising
     * every code and losing the crispness that makes a wall placard scannable.
     *
     * ponytail: capped at 200 placards per sheet (a real municipal print run).
     * Beyond that the DOM gets slow enough to stall the print dialog; the
     * truncation is stated on the page, never silent. Drop the cap if field
     * printing ever needs a whole province in one go.
     */
    public function surveyQr(Request $request): View
    {
        $query = Site::query()
            ->whereNotNull('ap_site_code')
            ->when($request->input('project_id'), fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->input('province'), fn ($q, $v) => $q->where('province', $v))
            ->when($request->input('district'), fn ($q, $v) => $q->where('district', $v))
            ->when($request->input('municipality'), fn ($q, $v) => $q->where('municipality', $v))
            ->orderBy('province')->orderBy('municipality')->orderBy('location_name');

        $total = (clone $query)->count();
        $sites = $query->limit(self::QR_SHEET_LIMIT)->get();

        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'scale' => 4,
        ]);
        $placards = $sites
            ->map(fn (Site $site) => [
                'site' => $site,
                'qr' => (new QRCode($options))->render(SiteSurvey::urlFor($site->ap_site_code)),
            ])
            ->filter(fn (array $placard) => $placard['qr'] !== null)
            ->values();

        return view('sites.survey-qr', [
            'placards' => $placards,
            'total' => $total,
            'limit' => self::QR_SHEET_LIMIT,
            'filters' => $this->qrFilterPayload($request),
            'surveyTitle' => SiteSurvey::active()?->title,
        ]);
    }

    /** @return array<string, mixed> */
    private function qrFilterPayload(Request $request): array
    {
        return array_filter([
            'project_id' => $request->input('project_id'),
            'province' => $request->input('province'),
            'district' => $request->input('district'),
            'municipality' => $request->input('municipality'),
        ]);
    }

    /** @return array<string, mixed> */
    private function filterPayload(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'project_id' => $request->input('project_id'),
            'status' => $request->input('status'),
            'province' => $request->input('province'),
            'today' => $request->input('today'),
        ];
    }
}
