<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Report Pack</title>
    @include('reports.partials.styles')
</head>
<body>
    @php($titles = ['ops_period' => 'Period health', 'fleet' => 'Fleet inventory', 'incidents' => 'Incidents', 'progress' => 'Progress'])
    @include('reports.partials.cover', ['title' => 'Operations Report Pack', 'scope' => $scope.' · '.$from.' – '.$to.' · Sections: '.implode(', ', array_map(fn ($s) => $titles[$s] ?? $s, $sections)), 'userName' => $userName])

    @foreach($sections as $section)
    @if(! $loop->first)
    <div style="page-break-before: always;"></div>
    @endif
    <h1 style="font-size:17px">{{ $titles[$section] ?? $section }}</h1>
    @if($section === 'ops_period')
    @include('reports.sections.ops', ['comparison' => $comparison])
    @elseif($section === 'fleet')
    @include('reports.sections.fleet', ['inventory' => $inventory])
    @elseif($section === 'incidents')
    @include('reports.sections.incidents', ['incidents' => $incidents])
    @elseif($section === 'progress')
    @include('reports.sections.progress', ['progress' => $progress])
    @endif
    @endforeach

    @include('reports.partials.footer')
</body>
</html>
