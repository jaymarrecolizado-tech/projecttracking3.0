<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $project->name }} - Summary Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1e40af; font-size: 20px; margin-bottom: 5px; }
        h2 { color: #1e40af; font-size: 16px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .stats { display: flex; gap: 15px; margin: 15px 0; }
        .stat-box { padding: 10px; border: 1px solid #ddd; border-radius: 5px; text-align: center; flex: 1; }
        .stat-value { font-size: 24px; font-weight: bold; color: #1e40af; }
        .stat-label { font-size: 10px; color: #64748b; }
        .badge { padding: 2px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-red { background: #fee2e2; color: #dc2626; }
        .badge-gray { background: #f1f5f9; color: #64748b; }
        .badge-yellow { background: #fef3c7; color: #d97706; }
        .footer { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $project->name }}</h1>
    <p style="color:#64748b;font-size:11px">Project Code: {{ $project->code }} | Report Type: {{ $project->report_type }} | Generated: {{ now()->format('Y-m-d H:i') }}</p>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Sites</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $stats['inactive'] }}</div>
            <div class="stat-label">Inactive</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $stats['planned'] }}</div>
            <div class="stat-label">Planned</div>
        </div>
        @isset($stats['up_today'])
        <div class="stat-box">
            <div class="stat-value">{{ $stats['up_today'] }}</div>
            <div class="stat-label">UP Today</div>
        </div>
        @endisset
    </div>

    <h2>Site List</h2>
    <table>
        <thead>
            <tr>
                <th>Location</th>
                <th>Municipality</th>
                <th>Province</th>
                <th>Region</th>
                <th>Status</th>
                <th>Daily Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sites as $site)
            <tr>
                <td>{{ $site->location_name }}</td>
                <td>{{ $site->municipality }}</td>
                <td>{{ $site->province }}</td>
                <td>{{ $site->region }}</td>
                <td>
                    <span class="badge @switch($site->status) @case('active') badge-green @break @case('inactive') badge-red @break @default badge-gray @endswitch">
                        {{ $site->status }}
                    </span>
                </td>
                <td>
                    @if($site->latestDailyStatus)
                        <span class="badge @if($site->latestDailyStatus->status === 'UP') badge-green @else badge-red @endif">
                            {{ $site->latestDailyStatus->status }}
                        </span>
                    @else
                        <span class="badge badge-gray">NO DATA</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Free Public Internet Access Program (FPIAP) — FreeWiFi Device Operations — Confidential
    </div>
</body>
</html>
