{{-- Period-health section body. Expects $comparison (opsPeriodComparison) + $bullets. --}}
    @include('reports.partials.summary', ['bullets' => $bullets])
    @include('reports.partials.kpi-strip', ['kpis' => [
        [$comparison['current']['uptime_pct'].'% ('.($comparison['delta_uptime'] >= 0 ? '+' : '').$comparison['delta_uptime'].' pts)', 'Uptime vs previous'],
        [$comparison['current']['uptime_base'].' site-days ('.($comparison['delta_sitedays'] >= 0 ? '+' : '').$comparison['delta_sitedays'].')', 'Observed volume'],
        [($comparison['current']['daily']['down'] + $comparison['current']['daily']['down_server']).' down ('.($comparison['delta_down'] >= 0 ? '+' : '').$comparison['delta_down'].')', 'Down at period end'],
        [count($comparison['current']['down_episodes']).' open', 'DOWN episodes'],
    ]])

    @include('reports.partials.trend-bars', ['trend' => $comparison['current']['trend']])

    <h2>Previous period ({{ $comparison['previous_range'] }})</h2>
    <table class="grid">
        <thead><tr><th>Signal</th><th>Previous</th><th>Current</th></tr></thead>
        <tbody>
            <tr><td>Uptime</td><td>{{ $comparison['previous']['uptime_pct'] }}% ({{ $comparison['previous']['uptime_base'] }} obs.)</td><td>{{ $comparison['current']['uptime_pct'] }}% ({{ $comparison['current']['uptime_base'] }} obs.)</td></tr>
            <tr><td>UP / DOWN at period end</td><td>{{ $comparison['previous']['daily']['up'] }} / {{ $comparison['previous']['daily']['down'] + $comparison['previous']['daily']['down_server'] }}</td><td>{{ $comparison['current']['daily']['up'] }} / {{ $comparison['current']['daily']['down'] + $comparison['current']['daily']['down_server'] }}</td></tr>
            <tr><td>Reporting progress</td><td>{{ $comparison['previous']['daily']['progress_pct'] }}%</td><td>{{ $comparison['current']['daily']['progress_pct'] }}%</td></tr>
        </tbody>
    </table>

    @if($comparison['current']['down_episodes']->isNotEmpty())
    <h2>Open DOWN episodes (longest first)</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Where</th><th>Status</th><th>Since</th><th>Duration</th></tr></thead>
        <tbody>
            @foreach($comparison['current']['down_episodes'] as $episode)
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
