{{-- Printable survey QR placards (Plan.md S8). Plain Blade, not Inertia: it
     must print clean. Expects $placards, $total, $limit, $filters, $surveyTitle. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survey QR Placards</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 24px; }
        .toolbar { max-width: 760px; margin: 0 auto 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 14px; }
        .toolbar a, .toolbar button {
            font-size: 14px; font-weight: 600; padding: 8px 16px; border-radius: 8px;
            border: none; cursor: pointer; text-decoration: none;
            background: #0E5E6F; color: #fff;
        }
        .toolbar button.ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .notice { max-width: 760px; margin: 0 auto 16px; padding: 10px 14px; border-radius: 8px;
                  background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px; }
        .sheet { display: flex; flex-wrap: wrap; gap: 12px; max-width: 760px; margin: 0 auto; }

        /* Placard: ~75x55mm at 96dpi ≈ 283x208px */
        .placard { width: 280px; border: 1px dashed #94a3b8; border-radius: 6px; background: #fff;
                   padding: 12px 14px; display: flex; gap: 12px; align-items: center; page-break-inside: avoid; }
        .placard .qr img { width: 96px; height: 96px; display: block; }
        .placard .info { min-width: 0; }
        .placard .name { font-size: 15px; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .placard .where { font-size: 11px; color: #334155; margin-top: 3px; }
        .placard .code { font-size: 10px; color: #64748b; font-family: monospace; margin-top: 2px; }
        .placard .ask { font-size: 12px; font-weight: 700; color: #0E5E6F; margin-top: 6px; }
        .placard .brand { font-size: 9px; color: #94a3b8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.08em; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar, .notice { display: none; }
            .placard { border-color: #000; border-width: 1px; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨 Print placards ({{ count($placards) }})</button>
        <a class="ghost" style="background:#fff;color:#334155;border:1px solid #cbd5e1;" href="{{ url('/sites') . ($filters ? '?' . http_build_query($filters) : '') }}">← Back to Sites</a>
    </div>

    @if($total > count($placards))
        <div class="notice">
            Showing {{ count($placards) }} of {{ $total }} sites in this scope (sheet limit {{ $limit }}).
            Narrow the filters on the Sites page to print the rest — nothing is dropped silently.
        </div>
    @endif

    @if(! $surveyTitle)
        <div class="notice">
            No survey is published, so these codes lead to a “survey not available” page.
            Run <code>php artisan db:seed --class=SiteSurveySeeder</code> before printing.
        </div>
    @endif

    <div class="sheet">
        @foreach ($placards as $placard)
            <div class="placard">
                <div class="qr"><img src="{{ $placard['qr'] }}" alt="Survey QR for {{ $placard['site']->location_name }}"></div>
                <div class="info">
                    <div class="name">{{ $placard['site']->location_name }}</div>
                    <div class="where">{{ trim(($placard['site']->barangay ?? '').', '.($placard['site']->municipality ?? '').', '.($placard['site']->province ?? ''), ', ') }}</div>
                    <div class="code">{{ $placard['site']->ap_site_code }}</div>
                    <div class="ask">Scan to rate this connection</div>
                    <div class="brand">FPIAP · FreeWiFi · anonymous</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(! count($placards))
        <p style="text-align:center;color:#64748b;margin-top:40px;">No sites in this scope — or no site has an AP code yet.</p>
    @endif
</body>
</html>
