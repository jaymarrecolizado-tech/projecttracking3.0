<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Site Type Coverage Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Site Type Coverage — Actual vs Registered', 'scope' => $coverage['scope'] ?? 'All areas', 'userName' => $userName])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$coverage['totals']['registered'], 'Registered sites'],
        [$coverage['totals']['actual'], 'With deployed device'],
        [$coverage['totals']['devices'], 'Deployed devices'],
        [$coverage['totals']['coverage_pct'].'%', 'Coverage'],
    ]])

    <table class="grid">
        <thead>
            <tr>
                <th>Site Type</th>
                <th>Registered</th>
                <th>Actual</th>
                <th>Gap</th>
                <th>Devices</th>
                <th style="width:30%">Coverage %</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($coverage['rows'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['registered'] }}</td>
                <td>{{ $row['actual'] }}</td>
                <td>{{ $row['gap'] }}</td>
                <td>{{ $row['devices'] }}</td>
                <td>
                    <table style="width:100%;border-collapse:collapse"><tr>
                        <td class="bar-fill-teal" style="width:{{ min(100, $row['coverage_pct']) }}%"></td>
                        <td class="bar-track" style="width:{{ max(0, 100 - $row['coverage_pct']) }}%"></td>
                    </tr></table>
                    {{ $row['coverage_pct'] }}%
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;background:#f1f5f9">
                <td>Total</td>
                <td>{{ $coverage['totals']['registered'] }}</td>
                <td>{{ $coverage['totals']['actual'] }}</td>
                <td>{{ $coverage['totals']['gap'] }}</td>
                <td>{{ $coverage['totals']['devices'] }}</td>
                <td>{{ $coverage['totals']['coverage_pct'] }}%</td>
            </tr>
        </tfoot>
    </table>

    @if ($sites->isNotEmpty())
    <h2>Deployed sites appendix ({{ $sites->count() }} sites)</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>Site Type</th>
                <th>Location</th>
                <th>Barangay / Municipality</th>
                <th>Devices</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sites as $site)
            <tr>
                <td>{{ config('site_types')[$site->site_type] ?? $site->site_type }}</td>
                <td>{{ $site->location_name }}</td>
                <td>{{ trim(($site->barangay ?? '').' · '.($site->municipality ?? ''), ' ·') }}</td>
                <td>{{ $site->activeDeployments->count() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @include('reports.partials.footer')
</body>
</html>
