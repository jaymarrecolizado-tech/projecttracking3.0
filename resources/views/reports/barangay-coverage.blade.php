<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Barangay Coverage Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Barangay Coverage — Installed/Existing vs Total', 'scope' => $coverage['scope'] ?? 'All areas', 'userName' => $userName])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$coverage['totals']['covered'].' / '.$coverage['totals']['barangays'], 'Barangays with Free WiFi'],
        [$coverage['totals']['deployed'], 'With deployed device'],
        [$coverage['totals']['remaining'], 'Remaining'],
        [$coverage['totals']['coverage_pct'].'%', 'Coverage'],
    ]])

    <table class="grid">
        <thead>
            <tr>
                <th>Province</th>
                <th>Barangays with Free WiFi</th>
                <th>With deployed device</th>
                <th>Remaining</th>
                <th>Total barangays</th>
                <th>Coverage %</th>
            </tr>
        </thead>
        <tbody>
            @php $byProvince = collect($coverage['rows'])->groupBy('province'); @endphp
            @foreach ($byProvince as $province => $rows)
            <tr style="font-weight:bold;background:#f1f5f9">
                <td>{{ $province }}</td>
                <td class="num">{{ $rows->sum('covered') }}</td>
                <td class="num">{{ $rows->sum('deployed') }}</td>
                <td class="num">{{ max(0, $rows->sum('total_barangays') - $rows->sum('covered')) }}</td>
                <td class="num">{{ $rows->sum('total_barangays') }}</td>
                <td class="num">{{ $rows->sum('total_barangays') > 0 ? round($rows->sum('covered') / $rows->sum('total_barangays') * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#e2e8f0">
                <td>{{ ($coverage['scope'] ?? 'All areas') === 'All areas' ? 'REGION II — TOTAL' : $coverage['scope'] }}</td>
                <td class="num">{{ $coverage['totals']['covered'] }}</td>
                <td class="num">{{ $coverage['totals']['deployed'] }}</td>
                <td class="num">{{ $coverage['totals']['remaining'] }}</td>
                <td class="num">{{ $coverage['totals']['barangays'] }}</td>
                <td class="num">{{ $coverage['totals']['coverage_pct'] }}%</td>
            </tr>
        </tfoot>
    </table>

    @foreach ($byProvince as $province => $rows)
    <h2>{{ $province }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Municipality / City</th>
                <th>Covered</th>
                <th>Deployed</th>
                <th>Remaining</th>
                <th>Total</th>
                <th>Coverage %</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row['municipality'] }}</td>
                <td class="num">{{ $row['covered'] }}</td>
                <td class="num">{{ $row['deployed'] }}</td>
                <td class="num">{{ $row['remaining'] }}</td>
                <td class="num">{{ $row['total_barangays'] }}</td>
                <td class="num">{{ $row['coverage_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    @if (! empty($coverage['uncovered']))
    <h2>Uncovered barangays ({{ count($coverage['uncovered']) }})</h2>
    <table class="grid">
        <thead>
            <tr><th>Barangay</th><th>Municipality / City</th><th>Province</th></tr>
        </thead>
        <tbody>
            @foreach ($coverage['uncovered'] as $place)
            <tr>
                <td>{{ $place['barangay'] }}</td>
                <td>{{ $place['municipality'] }}</td>
                <td>{{ $place['province'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="muted" style="font-size:9px">
        "Covered" = at least one registered Free WiFi site in the barangay; "Deployed" = an active device
        deployment. Barangay totals come from the reference list in this application — reconcile against the
        PSA count for Region II and add missing barangays to keep the percentages exact.
        @if (($coverage['unattributed_sites'] ?? 0) > 0)
            {{ $coverage['unattributed_sites'] }} site(s) have no barangay recorded and are not attributed.
        @endif
        @if (($coverage['district_blank_sites'] ?? 0) > 0)
            {{ $coverage['district_blank_sites'] }} site(s) in the selected province(s) have no legislative district
            recorded and are therefore excluded by the district filter.
        @endif
    </p>

    @include('reports.partials.footer')
</body>
</html>
