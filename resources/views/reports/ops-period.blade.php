<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operations Period Report</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.cover', ['title' => 'Operations — Period Health', 'scope' => $comparison['current']['scope'].' (vs '.$comparison['previous_range'].')', 'userName' => $userName])

    @include('reports.sections.ops')

    @include('reports.partials.footer')
</body>
</html>
