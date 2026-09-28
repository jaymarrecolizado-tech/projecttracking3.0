{{ count($breaching) }} site(s) below {{ $threshold }}/5 over the last {{ $windowDays }} days.

Anonymous survey responses. A rating needs {{ $minimumResponses }}+ responses per site, so nothing below that
is listed. Worst first.

@foreach($breaching as $row)
- {{ $row['site'] }}: {{ number_format($row['rating'], 1) }}/5 from {{ $row['responses'] }} response(s)
@endforeach

This is a rating, not an outage. A site can be UP and still be unusable — check what the users said before
dispatching anyone, and read the remarks in the satisfaction pack for what they actually complained about.

Satisfaction pack: {{ $reportsUrl }}
