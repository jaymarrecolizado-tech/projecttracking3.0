<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Province Report - {{ $province }}</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Province Report: '.$province, 'scope' => $scope, 'userName' => $userName])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$sites->count(), 'Sites'],
        [$rollup->sum('up').' UP', 'Reporting UP'],
        [$rollup->count(), 'Municipalities'],
    ]])

    <h2>Municipality rollup</h2>
    <table class="grid">
        <thead>
            <tr><th>Municipality / City</th><th>Sites</th><th>UP</th><th>UP %</th></tr>
        </thead>
        <tbody>
            @foreach($rollup as $row)
            <tr>
                <td>{{ $row['municipality'] }}</td>
                <td>{{ $row['sites'] }}</td>
                <td>{{ $row['up'] }}</td>
                <td>{{ $row['up_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($grouped as $municipality => $municipalitySites)
    <h2>{{ $municipality }} ({{ $municipalitySites->count() }} sites)</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Location</th>
                <th>Barangay</th>
                <th>Project</th>
                <th>Status</th>
                <th>Daily Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($municipalitySites as $site)
            @php($daily = $statusesAtTo->get($site->id))
            <tr>
                <td>{{ $site->location_name }}</td>
                <td>{{ $site->barangay ?? '—' }}</td>
                <td>{{ $site->project?->code ?? '—' }}</td>
                <td>{{ $site->status }}</td>
                <td>
                    @if($daily)
                    <span class="badge @if($daily === 'UP') b-green @elseif(in_array($daily, ['DOWN', 'DOWN_SERVER'])) b-red @elseif($daily === 'NO_NMS') b-amber @else b-slate @endif">{{ $daily }}</span>
                    @else
                    <span class="badge b-slate">NO DATA</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    @include('reports.partials.footer')
</body>
</html>
