<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Period Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Operations — Period Health', 'scope' => $current['scope'].' (vs '.$previous_range.')', 'userName' => $userName])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$current['uptime_pct'].'% ('.($delta_uptime >= 0 ? '+' : '').$delta_uptime.' pts)', 'Uptime vs previous'],
        [$current['uptime_base'].' site-days ('.($delta_sitedays >= 0 ? '+' : '').$delta_sitedays.')', 'Observed volume'],
        [($current['daily']['down'] + $current['daily']['down_server']).' down ('.($delta_down >= 0 ? '+' : '').$delta_down.')', 'Down at period end'],
        [count($current['down_episodes']).' open', 'DOWN episodes'],
    ]])

    @include('reports.partials.trend-bars', ['trend' => $current['trend']])

    <h2>Previous period ({{ $previous_range }})</h2>
    <table class="grid">
        <thead><tr><th>Signal</th><th>Previous</th><th>Current</th></tr></thead>
        <tbody>
            <tr><td>Uptime</td><td>{{ $previous['uptime_pct'] }}% ({{ $previous['uptime_base'] }} obs.)</td><td>{{ $current['uptime_pct'] }}% ({{ $current['uptime_base'] }} obs.)</td></tr>
            <tr><td>UP / DOWN at period end</td><td>{{ $previous['daily']['up'] }} / {{ $previous['daily']['down'] + $previous['daily']['down_server'] }}</td><td>{{ $current['daily']['up'] }} / {{ $current['daily']['down'] + $current['daily']['down_server'] }}</td></tr>
            <tr><td>Reporting progress</td><td>{{ $previous['daily']['progress_pct'] }}%</td><td>{{ $current['daily']['progress_pct'] }}%</td></tr>
        </tbody>
    </table>

    @if($current['down_episodes']->isNotEmpty())
    <h2>Open DOWN episodes (longest first)</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Where</th><th>Status</th><th>Since</th><th>Duration</th></tr></thead>
        <tbody>
            @foreach($current['down_episodes'] as $episode)
            <tr>
                <td>{{ $episode['site'] }}</td>
                <td>{{ $episode['where'] }}</td>
                <td><span class="badge b-red">{{ $episode['status'] }}</span></td>
                <td>{{ $episode['started_at'] }}</td>
                <td>{{ $episode['duration_h'] }}h</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="muted">No open DOWN episodes in scope — every watched site is currently reporting UP or has no open event.</p>
    @endif

    @include('reports.partials.footer')
</body>
</html>
