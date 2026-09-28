{{-- Satisfaction section body. Expects $satisfaction (satisfactionData) + $bullets. --}}
    @include('reports.partials.summary', ['bullets' => $bullets])
    @include('reports.partials.kpi-strip', ['kpis' => [
        [($satisfaction['scope_mean'] === null ? '—' : $satisfaction['scope_mean']), 'Mean rating / 5'],
        [$satisfaction['responses'], 'Responses'],
        [$satisfaction['rated_sites'], 'Rated sites'],
        [$satisfaction['unrated_sites'], 'Below minimum N'],
    ]])

    <p class="muted" style="font-size:9px">
        Anonymous ratings from people who answered the survey at these sites. A rating is shown only from
        {{ $satisfaction['min_responses'] }} responses up — below that the mean is noise, so the site is counted
        but not scored. This is a second dimension beside uptime, never folded into it: a site can report UP all
        month and still be unusable.
    </p>

    <h2>Rating distribution (first rating question)</h2>
    @php($total = max(1, array_sum($satisfaction['distribution'])))
    <table class="grid">
        <thead><tr><th style="width:18%">Score</th><th style="width:22%">Responses</th><th>Share</th></tr></thead>
        <tbody>
            @foreach($satisfaction['distribution'] as $score => $count)
            <tr class="bar-row">
                <td>{{ $score }} / 5</td>
                <td>{{ $count }}</td>
                <td>
                    <table style="width:100%;border-collapse:collapse"><tr>
                        <td class="bar-fill-teal" style="width:{{ round($count / $total * 100) }}%"></td>
                        <td class="bar-track"></td>
                    </tr></table>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if(! empty($satisfaction['by_question']))
    <h2>By question</h2>
    <table class="grid">
        <thead><tr><th style="width:34%">Question</th><th style="width:16%">Mean / 5</th><th>Bar</th></tr></thead>
        <tbody>
            @foreach($satisfaction['by_question'] as $question => $mean)
            <tr class="bar-row">
                <td>{{ ucfirst(str_replace('_', ' ', (string) $question)) }}</td>
                <td>{{ $mean }}</td>
                <td>
                    <table style="width:100%;border-collapse:collapse"><tr>
                        <td class="bar-fill-teal" style="width:{{ round(((float) $mean) / 5 * 100) }}%"></td>
                        <td class="bar-track"></td>
                    </tr></table>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h2>Lowest-rated sites (worst first)</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Where</th><th>Responses</th><th>Rating / 5</th></tr></thead>
        <tbody>
            @forelse($satisfaction['low'] as $row)
            <tr>
                <td>{{ $row['site'] }}</td>
                <td>{{ $row['where'] }}</td>
                <td>{{ $row['responses'] }}</td>
                <td>{{ $row['overall'] }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="muted">No site has reached the minimum response count in this scope.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(! empty($satisfaction['providers']))
    <h2>By provider &amp; transport</h2>
    <table class="grid">
        <thead><tr><th>Provider</th><th>Transport</th><th>Responses</th><th>Rating / 5</th></tr></thead>
        <tbody>
            @foreach($satisfaction['providers'] as $row)
            <tr>
                <td>{{ $row['cms_provider'] ?? '—' }}</td>
                <td>{{ $row['last_mile_tech'] ?? '—' }}</td>
                <td>{{ $row['responses'] }}</td>
                <td>{{ $row['meets_minimum'] ? $row['overall'] : 'too few' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted" style="font-size:9px">Provider names are free text as recorded on the site, so the same provider can appear twice; the provider registry (Plan#4) is what collapses that.</p>
    @endif

    @if(! empty($satisfaction['comments']))
    <h2>Recent remarks</h2>
    <table class="grid">
        <thead><tr><th>Site</th><th>Rating</th><th>Date</th><th>Remark</th></tr></thead>
        <tbody>
            @foreach($satisfaction['comments'] as $row)
            <tr>
                <td>{{ $row['site'] }}</td>
                <td>{{ $row['rating'] ?? '—' }}</td>
                <td>{{ $row['submitted_at'] }}</td>
                <td>{{ $row['comments'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
