<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Series Report — {{ $batch->lot_number ?? 'Batch #' . $batch->batch_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 20px; }
        h1 { font-size: 18px; margin-bottom: 5px; }
        h2 { font-size: 14px; margin: 15px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #2563eb; color: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { padding: 4px 8px; text-align: left; border: 1px solid #d1d5db; font-size: 10px; }
        th { background: #f3f4f6; font-weight: bold; }
        .pass { color: #16a34a; font-weight: bold; }
        .fail { color: #dc2626; font-weight: bold; }
        .meta { color: #6b7280; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .header-row td { background: #f9fafb; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
    @include('admin.reports._batch-report-content')
</body>
</html>
