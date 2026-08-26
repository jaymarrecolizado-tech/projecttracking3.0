Daily status reminder for {{ $reportDate }}

@foreach ($perProject as $project => $count)
- {{ $project }}: {{ $count }} site(s) unreported
@endforeach

Report now: {{ $reportUrl }}
