<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Serial Unit Labels</title>
    {{--
        A unit label has one job: the serial number, readable by eye and by
        scanner, on a small sticker. Every block sits at a fixed position, sized
        from the label's dimensions, so nothing can wrap into or over anything
        else the way a flowing layout does on a 40 mm label.

        Geometry (all in mm, from the label's width W and height H, 3 mm margin):
          - serial number: top-left, one line, mono, never wrapped
          - QR: top-right square, the serial itself
          - small lines under the serial: process serial, order · product, and
            the production date on a line of its own when the height allows
            (on a short label it joins the order line instead of being cut off)
          - barcode: bottom band across the width the QR leaves free, with the
            serial in small print under it
    --}}
    @php
        // Geometry comes from the template, not from any one label, so the
        // stylesheet is written once for the whole print run.
        $m = 3;                                    // margin
        $qr = $template->hasField('qr') ? min(18, $heightMm * 0.42) : 0;
        $textW = $widthMm - 2 * $m - ($qr ? $qr + 2 : 0);
        $barH = max(7, $heightMm * 0.24);
        $barTop = $heightMm - $m - $barH - 3.2;   // 3.2 mm for the human-readable line
        $metaTop = $m + 10.2;
        $dateTop = $m + 13.4;
        $dateFits = $dateTop + 2.6 <= $barTop;   // its own line only where it clears the barcode
    @endphp
    <style>
        @page { size: {{ $widthMm }}mm {{ $heightMm }}mm; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
        .label { position: relative; width: {{ $widthMm }}mm; height: {{ $heightMm - 0.5 }}mm; overflow: hidden; }
        .page-break { page-break-before: always; }
        .abs { position: absolute; white-space: nowrap; overflow: hidden; }
        .serial { left: {{ $m }}mm; top: {{ $m }}mm; width: {{ $textW }}mm; font-family: DejaVu Sans Mono, monospace; font-weight: bold; letter-spacing: 0.3pt; line-height: 1.1; }
        .psn { left: {{ $m }}mm; top: {{ $m + 6.2 }}mm; width: {{ $textW }}mm; font-family: DejaVu Sans Mono, monospace; font-size: 7.5pt; line-height: 1.2; }
        .meta { left: {{ $m }}mm; top: {{ $metaTop }}mm; width: {{ $textW }}mm; font-size: 6.5pt; color: #444; line-height: 1.2; }
        .date { left: {{ $m }}mm; top: {{ $dateTop }}mm; width: {{ $textW }}mm; font-size: 6.5pt; color: #444; line-height: 1.2; }
        .qr { right: {{ $m }}mm; top: {{ $m }}mm; width: {{ $qr }}mm; height: {{ $qr }}mm; }
        .qr img { width: {{ $qr }}mm; height: {{ $qr }}mm; }
        .barcode { left: {{ $m }}mm; top: {{ $barTop }}mm; width: {{ $widthMm - 2 * $m }}mm; height: {{ $barH }}mm; text-align: center; }
        .barcode img { max-width: 100%; height: {{ $barH }}mm; }
        .hr { left: {{ $m }}mm; top: {{ $barTop + $barH + 0.3 }}mm; width: {{ $widthMm - 2 * $m }}mm; text-align: center; font-family: DejaVu Sans Mono, monospace; font-size: 6.5pt; letter-spacing: 0.6pt; }
    </style>
</head>
<body>
@foreach($labels as $label)
    @php
        $f = $label['fields'];
        $show = fn (string $key) => $template->hasField($key) ? ($f[$key] ?? null) : null;
        $meta = implode(' · ', array_filter([$show('wo_number'), $show('product'), $dateFits ? null : $show('prod_date')]));
        // The serial must fit on one line whatever its length: size it to the width.
        $serialPt = max(8, min(13, $textW / max(12, mb_strlen((string) ($f['serial_no'] ?? ''))) * 2.6));
    @endphp
    <div class="label">
        <div class="abs serial" style="font-size: {{ $serialPt }}pt;">{{ $f['serial_no'] }}</div>
        @if($template->hasField('psn') && !empty($f['psn']))
            <div class="abs psn">PSN {{ $f['psn'] }}</div>
        @endif
        @if($meta !== '')
            <div class="abs meta">{{ $meta }}</div>
        @endif
        @if($dateFits && !empty($show('prod_date')))
            <div class="abs date">{{ $f['prod_date'] }}</div>
        @endif
        @if($label['has_qr'])
            <div class="abs qr"><img src="{{ $label['qr_png'] }}" /></div>
        @endif
        @if($label['has_barcode'])
            <div class="abs barcode"><img src="{{ $label['barcode_png'] }}" /></div>
            <div class="abs hr">{{ $label['barcode_value'] }}</div>
        @endif
    </div>
    @if(!$loop->last)<div class="page-break"></div>@endif
@endforeach
</body>
</html>
