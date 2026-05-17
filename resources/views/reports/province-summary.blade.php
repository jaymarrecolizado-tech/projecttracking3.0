<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Province Report - {{ $province }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; }
        h1 { color: #1e40af; font-size: 20px; margin-bottom: 5px; }
        h2 { color: #1e40af; font-size: 16px; margin: 20px 0 10px; border-bottom: 2px solid #dbeafe; padding-bottom: 5px; }
        h3 { font-size: 14px; color: #334155; margin: 15px 0 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #1e40af; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .footer { margin-top: 30px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>Province Report: {{ $province }}</h1>
    <p style="color:#64748b;font-size:11px">Total Sites: {{ $sites->count() }} | Generated: {{ now()->format('Y-m-d H:i') }}</p>

    @foreach($grouped as $municipality => $municipalitySites)
    <h3>{{ $municipality }} ({{ $municipalitySites->count() }} sites)</h3>
    <table>
        <thead>
            <tr>
                <th>Location</th>
                <th>Barangay</th>
                <th>Project</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($municipalitySites as $site)
            <tr>
                <td>{{ $site->location_name }}</td>
                <td>{{ $site->barangay }}</td>
                <td>{{ $site->project?->code }}</td>
                <td>{{ $site->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    <div class="footer">
        DICT-MRIS Multi-Project Reporting & Information System — Confidential
    </div>
</body>
</html>
