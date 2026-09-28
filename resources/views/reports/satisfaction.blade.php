<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Satisfaction Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'User Satisfaction', 'scope' => $satisfaction['scope'], 'userName' => $userName])

    @include('reports.sections.satisfaction')

    @include('reports.partials.footer')
</body>
</html>
