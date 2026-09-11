{{-- CSS trend bars (DomPDF cannot run JS): one stacked row per day. Expects $trend rows with date/up/down/no_nms/down_server. --}}
<h2>Daily trend</h2>
<table class="grid">
    <thead>
        <tr><th style="width:18%">Day</th><th>UP / DOWN / other observed</th><th style="width:22%">Counts</th></tr>
    </thead>
    <tbody>
        @foreach($trend as $day)
        @php($dayTotal = max(1, $day['up'] + $day['down'] + $day['no_nms'] + $day['down_server']))
        <tr class="bar-row">
            <td>{{ $day['date'] }}</td>
            <td>
                <table style="width:100%;border-collapse:collapse"><tr>
                    <td class="bar-fill-up" style="width:{{ round($day['up'] / $dayTotal * 100) }}%"></td>
                    <td class="bar-fill-down" style="width:{{ round($day['down'] / $dayTotal * 100) }}%"></td>
                    <td class="bar-fill-other" style="width:{{ round(($day['no_nms'] + $day['down_server']) / $dayTotal * 100) }}%"></td>
                    <td class="bar-track" style="width:{{ round(max(0, 100 - ($day['up'] + $day['down'] + $day['no_nms'] + $day['down_server']) / $dayTotal * 100)) }}%"></td>
                </tr></table>
            </td>
            <td class="muted">{{ $day['up'] }}↑ · {{ $day['down'] }}↓ · {{ $day['no_nms'] + $day['down_server'] }} other</td>
        </tr>
        @endforeach
    </tbody>
</table>
