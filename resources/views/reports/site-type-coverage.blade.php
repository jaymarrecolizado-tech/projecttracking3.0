<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Site Type Coverage Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1e40af; font-size: 20px; margin-bottom: 5px; }
        h2 { color: #1e40af; font-size: 16px; margin: 20px 0 10px; border-bottom: 2px solid #dbeafe; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .totals td { font-weight: bold; background: #f1f5f9; }
        .footer { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>Site Type Coverage — Actual vs Registered</h1>
    <p style="color:#64748b;font-size:11px">
        Scope:
        @if (empty($filters))
            All provinces · all projects
        @else
            {{ implode(' · ', array_filter($filters)) }}
        @endif
        | Generated: {{ now()->format('Y-m-d H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Site Type</th>
                <th>Registered</th>
                <th>Actual (with deployed device)</th>
                <th>Gap</th>
                <th>Deployed devices</th>
                <th>Coverage %</th>
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
                <td>{{ $row['coverage_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr>
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
    <h2>Deployed sites appendix</h2>
    <table>
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

    <div class="footer">
        Free Public Internet Access Program (FPIAP) — FreeWiFi Device Operations — Confidential
    </div>
</body>
</html>
