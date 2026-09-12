<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fleet Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Fleet — Equipment Inventory', 'scope' => $scope, 'userName' => $userName])

    @include('reports.sections.fleet')

    @include('reports.partials.footer')
</body>
</html>
