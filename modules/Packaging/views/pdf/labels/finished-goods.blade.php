<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Finished Goods Labels</title>
    @include('packaging::pdf.labels._styles', ['widthMm' => $widthMm, 'heightMm' => $heightMm])
</head>
<body>
@foreach($labels as $label)
    @include('packaging::pdf.labels._label', ['label' => $label, 'template' => $template, 'widthMm' => $widthMm, 'heightMm' => $heightMm])
    @if(!$loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>
