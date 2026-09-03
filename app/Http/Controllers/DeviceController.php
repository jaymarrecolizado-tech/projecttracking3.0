<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Site;
use App\Services\DeviceDeploymentService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeviceController extends Controller
{
    public function __construct(private DeviceDeploymentService $deployments) {}

    public function index(Request $request)
    {
        $devices = Device::with([
            'deviceModel:id,manufacturer,model_name,model_number,type,wifi_standard',
            'currentDeployment.site:id,location_name',
        ])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->whereHas('deviceModel', fn ($m) => $m->where('type', $v)))
            ->when($request->input('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('asset_tag', 'like', "%{$v}%")
                ->orWhere('serial_number', 'like', "%{$v}%")
                ->orWhere('mac_address', 'like', "%{$v}%")))
            ->when($request->input('warranty'), function ($q, $v) {
                if ($v === 'expired') {
                    $q->whereNotNull('warranty_until')->where('warranty_until', '<', now());
                } elseif ($v === 'expiring') {
                    $q->whereNotNull('warranty_until')->whereBetween('warranty_until', [now(), now()->addDays(90)]);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Devices/Index', [
            'devices' => $devices,
            'filters' => $request->only(['status', 'type', 'search', 'warranty']),
            'deviceModels' => DeviceModel::orderBy('manufacturer')->get(),
            'counts' => [
                'total' => Device::count(),
                'in_stock' => Device::inStock()->count(),
                'deployed' => Device::deployed()->count(),
                'under_repair' => Device::where('status', 'under_repair')->count(),
                'faulty' => Device::where('condition', 'faulty')->count(),
            ],
            // Inventory views — plan §3.2
            'stockByType' => \DB::table('devices')
                ->join('device_models', 'device_models.id', '=', 'devices.device_model_id')
                ->whereNull('devices.deleted_at')
                ->selectRaw("device_models.type, COUNT(*) AS total,
                    SUM(CASE WHEN devices.status = 'deployed' THEN 1 ELSE 0 END) AS deployed,
                    SUM(CASE WHEN devices.status = 'in_stock' THEN 1 ELSE 0 END) AS in_stock,
                    SUM(CASE WHEN devices.condition = 'faulty' THEN 1 ELSE 0 END) AS faulty")
                ->groupBy('device_models.type')
                ->orderByDesc('total')
                ->get(),
            'warranty' => [
                'expiring' => Device::whereNotNull('warranty_until')->whereBetween('warranty_until', [now(), now()->addDays(90)])->count(),
                'expired' => Device::whereNotNull('warranty_until')->where('warranty_until', '<', now())->count(),
            ],
        ]);
    }

    public function show(Device $device)
    {
        $device->load([
            'deviceModel',
            'currentDeployment.site:id,location_name,ap_site_code,municipality,province,status',
            'deployments' => fn ($q) => $q->with('site:id,location_name,ap_site_code,municipality,province')
                ->latest('installed_at'),
            'maintenanceLogs' => fn ($q) => $q->with(['performer:id,name', 'site:id,location_name'])
                ->latest('performed_at')->take(20),
            // Recent telemetry for the 48h sparklines (docs §Phase 2).
            'metrics' => fn ($q) => $q->where('ts', '>=', now()->subHours(48))
                ->orderBy('ts')
                ->get(['id', 'device_id', 'ts', 'latency_ms', 'clients', 'rx_mbps', 'tx_mbps', 'battery_v', 'solar_w']),
        ]);

        return Inertia::render('Devices/Show', [
            'device' => $device,
            'deviceModels' => DeviceModel::where('is_active', true)
                ->orderBy('manufacturer')->orderBy('model_name')
                ->get(['id', 'manufacturer', 'model_name', 'model_number']),
            'sites' => Site::where('status', 'active')->orderBy('location_name')->get(['id', 'location_name']),
        ]);
    }

    public function store(StoreDeviceRequest $request)
    {
        $validated = $request->validated();

        $device = $this->deployments->register(
            collect($validated)->except(['site_id', 'role_at_site', 'installed_at'])->all(),
            $validated,
        );

        return redirect()->route('devices.show', $device);
    }

    public function update(UpdateDeviceRequest $request, Device $device)
    {
        $validated = $request->validated();

        $device = $this->deployments->updateWithAssignment(
            $device,
            collect($validated)->except(['site_id', 'role_at_site', 'installed_at'])->all(),
            $validated,
        );

        return redirect()->route('devices.show', $device);
    }

    public function destroy(Device $device)
    {
        $device->delete();

        return redirect()->route('devices.index');
    }

    /** Short URL encoded in the QR tag — scanning lands on the device page. */
    public function scan(string $tag)
    {
        $device = Device::withTrashed()->where('asset_tag', $tag)->firstOrFail();

        return redirect()->route('devices.show', $device);
    }

    /** Standalone printable label page (not Inertia — must print clean). */
    public function label(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))->filter(fn ($v) => ctype_digit($v));
        $devices = $ids->isNotEmpty()
            ? Device::with('deviceModel')->whereIn('id', $ids)->orderBy('asset_tag')->get()
            : collect([Device::with('deviceModel')->findOrFail((int) $request->query('device'))]);

        // chillerlan/php-qrcode v6: SVG markup is the default output
        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'scale' => 4,
        ]);
        $labels = $devices->map(fn ($d) => [
            'device' => $d,
            'qr' => (new QRCode($options))->render(route('devices.scan', $d->asset_tag)),
        ]);

        return view('devices.label', ['labels' => $labels]);
    }
}
