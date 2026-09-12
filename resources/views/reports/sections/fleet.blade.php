{{-- Fleet section body. Expects $inventory (fleetInventory) + $bullets. --}}
    @include('reports.partials.summary', ['bullets' => $bullets])
    @include('reports.partials.kpi-strip', ['kpis' => [
        [$inventory['deployed'], 'Deployed'],
        [$inventory['in_stock'], 'In stock'],
        [$inventory['under_repair'], 'Under repair'],
        [$inventory['warranty_expiring'], 'Warranty ≤ 90d'],
    ]])

    <h2>Firmware vs approved list</h2>
    @if(empty($inventory['approved_firmware']))
    <p class="muted">No approved firmware list configured (<code>APPROVED_FIRMWARE</code> is empty), so every deployed version below is informational only.</p>
    @endif
    <table class="grid">
        <thead><tr><th>Firmware version</th><th>Deployed units</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($inventory['firmware_rows'] as $row)
            <tr>
                <td>{{ $row['version'] }}</td>
                <td>{{ $row['count'] }}</td>
                <td>
                    @if(empty($inventory['approved_firmware']))
                    <span class="badge b-slate">unlisted</span>
                    @elseif($row['approved'])
                    <span class="badge b-green">approved</span>
                    @else
                    <span class="badge b-red">outdated</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($inventory['outdated'] !== null)
    <p class="muted">{{ $inventory['outdated'] }} deployed unit(s) run firmware outside the approved list.</p>
    @endif

    <h2>Device register ({{ $inventory['register']->count() }} units)</h2>
    <p class="muted">Inventory is fleet-wide — stock has no geography; the deployed count follows the project filter.</p>
    <table class="grid">
        <thead>
            <tr><th>Asset tag</th><th>Model</th><th>Status</th><th>Firmware</th><th>Site</th><th>Warranty until</th></tr>
        </thead>
        <tbody>
            @foreach($inventory['register'] as $device)
            <tr>
                <td>{{ $device->asset_tag }}</td>
                <td>{{ trim(($device->deviceModel->manufacturer ?? '').' '.($device->deviceModel->model_name ?? '')) ?: '—' }}</td>
                <td>{{ $device->status }}</td>
                <td>{{ $device->firmware_version ?? '—' }}</td>
                <td>{{ data_get($device->currentDeployment, 'site.location_name', '—') }}</td>
                <td>{{ $device->warranty_until?->toDateString() ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
