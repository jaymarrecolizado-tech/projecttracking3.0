{{-- Progress section body. Expects $progress (progressData). --}}
    @include('reports.partials.kpi-strip', ['kpis' => [
        [$progress['overall_pct'].'%', 'Weighted accomplishment'],
        [count($progress['projects']), 'Projects tracked'],
        [count($progress['overdue']), 'Overdue items'],
    ]])

    @foreach($progress['projects'] as $project)
    <h2>{{ $project['project'] }} — {{ $project['weighted_pct'] }}%</h2>
    <table class="grid">
        <thead><tr><th>Milestone</th><th>Weight</th><th>Avg %</th><th style="width:30%">Progress</th><th>Sites reporting</th></tr></thead>
        <tbody>
            @foreach($project['milestones'] as $milestone)
            <tr>
                <td>{{ $milestone['name'] }}</td>
                <td>{{ $milestone['weight'] }}%</td>
                <td>{{ $milestone['avg_pct'] }}%</td>
                <td>
                    <table style="width:100%;border-collapse:collapse"><tr>
                        <td class="bar-fill-teal" style="width:{{ min(100, $milestone['avg_pct']) }}%"></td>
                        <td class="bar-track" style="width:{{ max(0, 100 - $milestone['avg_pct']) }}%"></td>
                    </tr></table>
                </td>
                <td>{{ $milestone['sites'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    @if(! empty($progress['overdue']))
    <h2>Overdue ({{ count($progress['overdue']) }})</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Milestone</th><th>Status</th><th>%</th><th>Target</th><th>Late by</th></tr></thead>
        <tbody>
            @foreach($progress['overdue'] as $row)
            <tr>
                <td>{{ $row['site'] }}</td>
                <td>{{ $row['milestone'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['pct'] }}%</td>
                <td>{{ $row['target_date'] }}</td>
                <td>{{ $row['days_overdue'] }}d</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="muted">Nothing overdue in scope.</p>
    @endif
