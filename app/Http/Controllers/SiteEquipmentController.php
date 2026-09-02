<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceDeployment;
use App\Models\DeviceModel;
use App\Models\Site;
use App\Services\DeviceDeploymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Attach/detach equipment on a site (Site Show → Installed Equipment).
 * "Register" creates a brand-new unit already deployed here; "Assign" pulls
 * an in-stock unit into service. Detaching closes the deployment and returns
 * the unit to stock.
 */
class SiteEquipmentController extends Controller
{
    public function __construct(private DeviceDeploymentService $deployments) {}

    public function store(Request $request, Site $site)
    {
        $data = $request->validate([
            'mode' => 'required|in:new,existing',
            'device_model_id' => 'required_if:mode,new|exists:device_models,id',
            'asset_tag' => 'required_if:mode,new|string|max:100|unique:devices,asset_tag',
            'serial_number' => 'required_if:mode,new|string|max:255|unique:devices,serial_number',
            'mac_address' => 'nullable|mac_address|unique:devices,mac_address',
            'firmware_version' => 'nullable|string|max:100',
            'device_id' => 'required_if:mode,existing|exists:devices,id',
            'role_at_site' => 'required|in:primary_ap,backup_ap,backhaul,power,surveillance,other',
            'installed_at' => 'nullable|date',
        ]);

        if ($data['mode'] === 'new') {
            $this->deployments->register(
                [
                    'device_model_id' => $data['device_model_id'],
                    'asset_tag' => $data['asset_tag'],
                    'serial_number' => $data['serial_number'],
                    'mac_address' => $data['mac_address'] ?? null,
                    'firmware_version' => $data['firmware_version'] ?? null,
                    'condition' => 'new',
                    'status' => 'deployed',
                ],
                [
                    'status' => 'deployed',
                    'site_id' => $site->id,
                    'role_at_site' => $data['role_at_site'],
                    'installed_at' => $data['installed_at'] ?? now(),
                ],
            );
        } else {
            $device = Device::where('status', 'in_stock')->findOrFail($data['device_id']);

            DB::transaction(function () use ($device, $site, $data) {
                $this->deployments->open($device, [
                    'site_id' => $site->id,
                    'role_at_site' => $data['role_at_site'],
                    'installed_at' => $data['installed_at'] ?? now(),
                ]);
                $device->update(['status' => 'deployed']);
            });
        }

        return back()->with('success', 'Equipment attached to '.$site->location_name.'.');
    }

    public function destroy(Site $site, DeviceDeployment $deployment)
    {
        abort_unless((int) $deployment->site_id === (int) $site->id, 404);
        abort_unless($deployment->removed_at === null, 409);

        DB::transaction(function () use ($deployment) {
            $deployment->update(['removed_at' => now()]);
            $deployment->device->update(['status' => 'in_stock']);
        });

        return back()->with('success', 'Equipment detached and returned to stock.');
    }
}
