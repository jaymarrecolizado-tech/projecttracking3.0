<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Barangay Coverage Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1e40af; font-size: 20px; margin-bottom: 5px; }
        h2 { color: #1e40af; font-size: 15px; margin: 18px 0 8px; border-bottom: 2px solid #dbeafe; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .num { text-align: right; }
        .totals td { font-weight: bold; background: #f1f5f9; }
        .footer { margin-top: 26px; font-size: 9px; color: #94a3b8; text-align: center; }
        .note { font-size: 9px; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Barangay Coverage — Free WiFi Installed/Existing vs Total</h1>
    <p style="color:#64748b;font-size:11px">
        Scope:
        @if (empty($filters))
            Region II · all projects
        @else
            {{ implode(' · ', array_filter($filters)) }}
        @endif
        | Generated: {{ now()->format('Y-m-d H:i') }}
    </p>

    <table>
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
            <tr class="totals">
                <td>{{ $province }}</td>
                <td class="num">{{ $rows->sum('covered') }}</td>
                <td class="num">{{ $rows->sum('deployed') }}</td>
                <td class="num">{{ max(0, $rows->sum('total_barangays') - $rows->sum('covered')) }}</td>
                <td class="num">{{ $rows->sum('total_barangays') }}</td>
                <td class="num">{{ $rows->sum('total_barangays') > 0 ? round($rows->sum('covered') / $rows->sum('total_barangays') * 100, 1) : 0 }}%</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr>
                <td>REGION II — TOTAL</td>
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
    <table>
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

    <p class="note">
        "Covered" = at least one registered Free WiFi site in the barangay; "Deployed" = an active device
        deployment. Barangay totals come from the reference list in this application — reconcile against the
        PSA count for Region II and add missing barangays to keep the percentages exact.
        @if (($coverage['unattributed_sites'] ?? 0) > 0)
            {{ $coverage['unattributed_sites'] }} site(s) have no barangay recorded and are not attributed.
        @endif
    </p>

    <div class="footer">
        Free Public Internet Access Program (FPIAP) — FreeWiFi Device Operations — Confidential
    </div>
</body>
</html>
