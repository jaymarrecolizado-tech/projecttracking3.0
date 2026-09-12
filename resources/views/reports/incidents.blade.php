<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Incidents Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Incidents — Alerts & Tickets', 'scope' => $incidents['scope'].' · '.$incidents['from'].' – '.$incidents['to'], 'userName' => $userName])

    @include('reports.sections.incidents')

    @include('reports.partials.footer')
</body>
</html>
