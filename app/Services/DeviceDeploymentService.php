<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceDeployment;
use Illuminate\Support\Facades\DB;

/**
 * Owns the device lifecycle transitions so controllers stay thin and every
 * multi-table write happens inside one transaction.
 */
class DeviceDeploymentService
{
    /** Create a unit and, when registered as deployed, open its first assignment — atomically. */
    public function register(array $attributes, array $assignment = []): Device
    {
        return DB::transaction(function () use ($attributes, $assignment) {
            $device = Device::create($attributes);

            if (($assignment['status'] ?? null) === 'deployed' && ! empty($assignment['site_id'])) {
                $this->open($device, $assignment);
            }

            return $device;
        });
    }

    /** Apply attribute changes and reconcile the assignment history — atomically. */
    public function updateWithAssignment(Device $device, array $attributes, array $assignment): Device
    {
        DB::transaction(function () use ($device, $attributes, $assignment) {
            $device->update($attributes);
            $this->syncAssignment($device, $assignment);
        });

        return $device;
    }

    /**
     * Record an installation. $actorId lets queued contexts (imports) preserve
     * attribution; interactive requests fall back to the signed-in user.
     */
    public function open(Device $device, array $data, ?int $actorId = null): DeviceDeployment
    {
        return DeviceDeployment::create([
            'device_id' => $device->id,
            'site_id' => $data['site_id'],
            'role_at_site' => $data['role_at_site'] ?? 'primary_ap',
            'installed_at' => $data['installed_at'] ?? now(),
            'installed_by' => $actorId ?? auth()->id(),
        ]);
    }

    /**
     * Close the current deployment and/or open a new one so the assignment
     * history stays accurate. Callers wanting atomicity should wrap in a transaction.
     */
    public function syncAssignment(Device $device, array $data): void
    {
        $current = $device->currentDeployment()->first();
        $targetSite = ($data['status'] ?? null) === 'deployed' ? ($data['site_id'] ?? null) : null;

        if ($current && (! $targetSite || (int) $current->site_id !== (int) $targetSite)) {
            $current->update(['removed_at' => now()]);
            $current = null;
        }

        if ($targetSite && ! $current) {
            $this->open($device, $data);
        }
    }
}
