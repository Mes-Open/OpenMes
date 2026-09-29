<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Carton Labels</title>
    {{--
        The outer-box label: carton number big and as a barcode, what and how
        many, then every unit serial inside in a dense grid.

        Every block sits at a fixed position sized from the label's dimensions,
        so the sticker is always exactly one page: the grid gets the height the
        head and barcode leave, and units beyond it are counted ("+N more")
        instead of spilling onto a second page.

        Geometry (mm, from the label's width W and height H, margin m):
          - carton number: top-left, one line; QR: top-right square
          - two meta lines under the number: order · product, quantity · pallet · date
          - barcode: a band across the width, under the head
          - units: a mono grid in the remaining height
    --}}
    @php
        $m = 3;
        $small = $heightMm < 40;
        $noPt = $heightMm >= 50 ? 15 : ($small ? 10 : 12);
        $metaPt = $small ? 6.5 : 8;
        $noH = $noPt * 0.3528 * 1.15;                    // one line of the number
        $metaTop = $m + $noH + 0.6;
        $metaH = 2 * $metaPt * 0.3528 * 1.35;            // two lines
        $qr = $template->hasField('qr') ? min(22, max(10, $heightMm * 0.3)) : 0;
        $textW = $widthMm - 2 * $m - ($qr ? $qr + 2 : 0);
        $headBottom = max($metaTop + $metaH + 1.2, $m + $qr);   // dompdf sets lines a touch taller than the estimate
        $barH = $template->hasField('barcode') ? ($small ? 5 : max(7, $heightMm * 0.13)) : 0;
        $barTop = $headBottom + 1;
        $unitsTop = $barTop + ($barH ? $barH + 1.5 : 0);
        $rowH = 3.3;                                     // 7pt mono at 1.35 line height
        $maxRows = max(0, (int) floor(($heightMm - $m - $unitsTop) / $rowH));
        $cols = max(1, (int) floor(($widthMm - 2 * $m) / 24));
    @endphp
    <style>
        @page { size: {{ $widthMm }}mm {{ $heightMm }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        .label { position: relative; width: {{ $widthMm }}mm; height: {{ $heightMm - 0.5 }}mm; overflow: hidden; }
        .page-break { page-break-before: always; }
        .abs { position: absolute; white-space: nowrap; overflow: hidden; }
        .no { left: {{ $m }}mm; top: {{ $m }}mm; width: {{ $textW }}mm; font-family: DejaVu Sans Mono, monospace; font-weight: bold; font-size: {{ $noPt }}pt; line-height: 1.15; }
        .meta { left: {{ $m }}mm; top: {{ $metaTop }}mm; width: {{ $textW }}mm; font-size: {{ $metaPt }}pt; color: #333; line-height: 1.35; }
        .qr { right: {{ $m }}mm; top: {{ $m }}mm; width: {{ $qr }}mm; height: {{ $qr }}mm; }
        .qr img { width: {{ $qr }}mm; height: {{ $qr }}mm; }
        .bar { left: {{ $m }}mm; top: {{ $barTop }}mm; width: {{ $widthMm - 2 * $m }}mm; height: {{ $barH }}mm; text-align: center; }
        .bar img { max-width: 100%; height: {{ $barH }}mm; }
        .units { position: absolute; left: {{ $m }}mm; top: {{ $unitsTop }}mm; width: {{ $widthMm - 2 * $m }}mm; border-collapse: collapse; border-top: 0.3mm solid #000; }
        .units td { font-family: DejaVu Sans Mono, monospace; font-size: 7pt; line-height: 1.35; padding: 0 1mm 0 0; white-space: nowrap; }
        .units td.more { color: #555; font-family: DejaVu Sans, sans-serif; }
    </style>
</head>
<body>
@foreach($labels as $label)
    @php
        $f = $label['fields'];
        $units = $label['units'] ?? [];
        $cap = $maxRows * $cols;
        $shown = count($units) > $cap ? array_slice($units, 0, max(0, $cap - 1)) : $units;
        $more = count($units) - count($shown);
        $rows = array_chunk($shown, $cols);
        if ($more > 0) { if ($rows === []) { $rows[] = []; } $rows[count($rows) - 1][] = '+'.$more; }
        $show = fn (string $key) => $template->hasField($key) ? ($f[$key] ?? null) : null;
        $line1 = implode(' · ', array_filter([$show('wo_number'), $show('product')]));
        $line2 = implode(' · ', array_filter([$show('location'), $show('prod_date')]));
    @endphp
    <div class="label">
        <div class="abs no">{{ $f['carton_no'] }}</div>
        <div class="abs meta">{{ $line1 }}<br>@if($show('quantity'))<strong>{{ $show('quantity') }}</strong>@if($line2 !== '') · @endif @endif{{ $line2 }}</div>
        @if($label['has_qr'])
            <div class="abs qr"><img src="{{ $label['qr_png'] }}" /></div>
        @endif
        @if($label['has_barcode'] && $barH)
            <div class="abs bar"><img src="{{ $label['barcode_png'] }}" /></div>
        @endif
        @if($rows !== [] && $maxRows > 0)
        <table class="units">
            @foreach($rows as $row)
                <tr>@foreach($row as $sn)<td class="{{ str_starts_with($sn, '+') ? 'more' : '' }}">{{ str_starts_with($sn, '+') ? '+'.__(':count more', ['count' => substr($sn, 1)]) : $sn }}</td>@endforeach</tr>
            @endforeach
        </table>
        @endif
    </div>
    @if(!$loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>
