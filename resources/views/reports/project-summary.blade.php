<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $project->name }} - Summary Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => $project->name.' — Executive Summary', 'scope' => $analytics['scope'], 'userName' => $userName])

    @include('reports.partials.summary', ['bullets' => $bullets])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$analytics['sites']['active'].' / '.$analytics['sites']['total'], 'Active / total sites'],
        [$analytics['daily']['up'].' UP · '.$analytics['daily']['down'].' DOWN', 'Today ('.$analytics['to'].')'],
        [$analytics['daily']['reported'].' / '.$analytics['sites']['active'].' ('.$analytics['daily']['progress_pct'].'%)', 'Reporting progress'],
        [$analytics['uptime_pct'].'% ('.$analytics['uptime_base'].' obs.)', 'Uptime '.$analytics['from'].' – '.$analytics['to']],
    ]])

    @include('reports.partials.trend-bars', ['trend' => $analytics['trend']])

    <h2>Coverage &amp; fleet</h2>
    <table class="grid">
        <thead><tr><th>Signal</th><th>Figure</th></tr></thead>
        <tbody>
            <tr><td>Site types covered</td><td>{{ data_get($analytics, 'site_coverage.covered', 0) }} / {{ data_get($analytics, 'site_coverage.total', 0) }}</td></tr>
            <tr><td>Barangays with presence</td><td>{{ data_get($analytics, 'barangay_coverage.covered', 0) }} / {{ data_get($analytics, 'barangay_coverage.total', 0) }}</td></tr>
            <tr><td>Deployed units (in scope)</td><td>{{ $analytics['fleet']['deployed'] }}</td></tr>
            <tr><td>Stock / under repair (fleet-wide)</td><td>{{ $analytics['fleet']['in_stock'] }} / {{ $analytics['fleet']['under_repair'] }}</td></tr>
            <tr><td>Active alerts ({{ $analytics['alerts']['critical'] }} critical)</td><td>{{ $analytics['alerts']['active'] }}</td></tr>
            <tr><td>Open tickets ({{ $analytics['tickets']['critical_open'] }} critical)</td><td>{{ $analytics['tickets']['open'] }}</td></tr>
        </tbody>
    </table>

    @if($analytics['down_episodes']->isNotEmpty())
    <h2>Open DOWN episodes (longest first)</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Where</th><th>Status</th><th>Since</th><th>Duration</th></tr></thead>
        <tbody>
            @foreach($analytics['down_episodes'] as $episode)
            <tr>
                <td>{{ $episode['site'] }}</td>
                <td>{{ $episode['where'] }}</td>
                <td><span class="badge b-red">{{ $episode['status'] }}</span></td>
                <td>{{ $episode['started_at'] }}</td>
                <td>{{ $episode['duration_h'] }}h</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(! empty($analytics['tickets']['latest']))
    <h2>Open tickets</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Title</th><th>Status</th><th>Priority</th><th>Opened</th></tr></thead>
        <tbody>
            @foreach($analytics['tickets']['latest'] as $ticket)
            <tr>
                <td>{{ $ticket['site'] }}</td>
                <td>{{ $ticket['title'] }}</td>
                <td>{{ $ticket['status'] }}</td>
                <td><span class="badge @if($ticket['priority'] === 'critical') b-red @elseif($ticket['priority'] === 'high') b-amber @else b-slate @endif">{{ $ticket['priority'] }}</span></td>
                <td>{{ $ticket['created_at'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h2>Site register</h2>
    <table class="grid">
        <thead>
            <tr><th>Location</th><th>Municipality</th><th>Type</th><th>Daily status</th><th>Devices</th><th>CIR (Mbps)</th></tr>
        </thead>
        <tbody>
            @foreach($register as $site)
            @php($daily = $statusesAtTo->get($site->id))
            <tr>
                <td>{{ $site->location_name }}</td>
                <td>{{ $site->municipality ?? '—' }}</td>
                <td>{{ $site->site_type ?? '—' }}</td>
                <td>
                    @if($daily)
                    <span class="badge @if($daily === 'UP') b-green @elseif(in_array($daily, ['DOWN', 'DOWN_SERVER'])) b-red @elseif($daily === 'NO_NMS') b-amber @else b-slate @endif">{{ $daily }}</span>
                    @else
                    <span class="badge b-slate">NO DATA</span>
                    @endif
                </td>
                <td>{{ $site->activeDeployments->count() }}</td>
                <td>{{ $site->bw_download_cir ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @include('reports.partials.footer')
</body>
</html>
