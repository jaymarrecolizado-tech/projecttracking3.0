<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Incidents Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Incidents — Alerts & Tickets', 'scope' => $scope.' · '.$from.' – '.$to, 'userName' => $userName])

    @include('reports.partials.kpi-strip', ['kpis' => [
        [$alerts_triggered, 'Alerts triggered'],
        [($mtta_h === null ? '—' : $mtta_h.'h'), 'MTTA (acknowledge)'],
        [($mttr_alerts_h === null ? '—' : $mttr_alerts_h.'h'), 'MTTR (alerts)'],
        [($mttr_tickets_h === null ? '—' : $mttr_tickets_h.'h'), 'MTTR (tickets)'],
    ]])

    <h2>Alerts by severity</h2>
    <table class="grid">
        <thead><tr><th>Severity</th><th>Triggered</th></tr></thead>
        <tbody>
            @forelse($by_severity as $severity => $count)
            <tr>
                <td><span class="badge @if($severity === 'critical') b-red @elseif($severity === 'warning') b-amber @else b-blue @endif">{{ $severity }}</span></td>
                <td>{{ $count }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="muted">No alerts triggered in this window.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ticket backlog ({{ $tickets_open }} open)</h2>
    <table class="grid">
        <thead><tr><th>Priority</th><th>Open</th></tr></thead>
        <tbody>
            @forelse($tickets_by_priority as $priority => $count)
            <tr>
                <td><span class="badge @if($priority === 'critical') b-red @elseif($priority === 'high') b-amber @else b-slate @endif">{{ $priority }}</span></td>
                <td>{{ $count }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="muted">No open tickets in scope.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(! empty($open_alerts))
    <h2>Open alerts (oldest first)</h2>
    <table class="grid">
        <thead><tr><th>Severity</th><th>Rule</th><th>Site</th><th>Triggered</th><th>Age</th></tr></thead>
        <tbody>
            @foreach($open_alerts as $alert)
            <tr>
                <td><span class="badge @if($alert['severity'] === 'critical') b-red @elseif($alert['severity'] === 'warning') b-amber @else b-blue @endif">{{ $alert['severity'] }}</span></td>
                <td>{{ $alert['rule'] }}</td>
                <td>{{ $alert['site'] }}</td>
                <td>{{ $alert['triggered_at'] }}</td>
                <td>{{ $alert['age_h'] }}h</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(! empty($open_tickets))
    <h2>Open tickets (oldest first)</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Title</th><th>Status</th><th>Priority</th><th>Age</th></tr></thead>
        <tbody>
            @foreach($open_tickets as $ticket)
            <tr>
                <td>{{ $ticket['site'] }}</td>
                <td>{{ $ticket['title'] }}</td>
                <td>{{ $ticket['status'] }}</td>
                <td><span class="badge @if($ticket['priority'] === 'critical') b-red @elseif($ticket['priority'] === 'high') b-amber @else b-slate @endif">{{ $ticket['priority'] }}</span></td>
                <td>{{ $ticket['age_h'] }}h</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="muted" style="font-size:9px">MTTA = acknowledge − trigger; MTTR = resolve − trigger (alerts) or resolve − opened (tickets). Unacknowledged/unresolved items are excluded from the averages.</p>

    @include('reports.partials.footer')
</body>
</html>
