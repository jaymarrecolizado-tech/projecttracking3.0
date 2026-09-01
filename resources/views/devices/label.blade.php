<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Labels</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 24px; }
        .toolbar { max-width: 720px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center; }
        .toolbar a, .toolbar button {
            font-size: 14px; font-weight: 600; padding: 8px 16px; border-radius: 8px;
            border: none; cursor: pointer; text-decoration: none;
            background: #2563eb; color: #fff;
        }
        .toolbar button.ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .sheet { display: flex; flex-wrap: wrap; gap: 12px; max-width: 720px; margin: 0 auto; }

        /* Label: ~62x40mm at 96dpi ≈ 234x151px */
        .label { width: 240px; border: 1px dashed #94a3b8; border-radius: 6px; background: #fff;
                 padding: 10px 12px; display: flex; align-items: center; gap: 10px; page-break-inside: avoid; }
        .label .qr img { width: 84px; height: 84px; display: block; }
        .label .info { min-width: 0; }
        .label .tag { font-size: 17px; font-weight: 800; letter-spacing: 0.03em; color: #0f172a; word-break: break-all; }
        .label .model { font-size: 11px; color: #334155; margin-top: 3px; }
        .label .serial { font-size: 10px; color: #64748b; font-family: monospace; margin-top: 2px; word-break: break-all; }
        .label .brand { font-size: 9px; color: #94a3b8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.08em; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .label { border-color: #000; border-width: 1px; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="ghost" onclick="window.print()">🖨 Print labels ({{ count($labels) }})</button>
        <a href="{{ url('/devices') }}">← Back to Devices</a>
    </div>

    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label">
                <div class="qr"><img src="{{ $label['qr'] }}" alt="QR {{ $label['device']->asset_tag }}"></div>
                <div class="info">
                    <div class="tag">{{ $label['device']->asset_tag }}</div>
                    <div class="model">{{ $label['device']->deviceModel->manufacturer }} {{ $label['device']->deviceModel->model_name }}</div>
                    <div class="serial">S/N {{ $label['device']->serial_number }}</div>
                    <div class="brand">FPIAP · FreeWiFi · scan for device info</div>
                </div>
            </div>
        @endforeach
    </div>

    @if (!count($labels))
        <p style="text-align:center;color:#64748b;margin-top:40px;">No devices selected.</p>
    @endif
</body>
</html>
