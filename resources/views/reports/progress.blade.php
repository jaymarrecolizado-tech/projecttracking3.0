<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Progress Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Progress — Accomplishments vs Plan', 'scope' => $progress['scope'], 'userName' => $userName])

    @include('reports.sections.progress')

    @include('reports.partials.footer')
</body>
</html>
