<style>
    @page {
        size: {{ $widthMm }}mm {{ $heightMm }}mm;
        margin: 0;
    }
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
        font-family: DejaVu Sans, sans-serif;
        font-size: 9pt;
        color: #000;
    }
    .label {
        /* DomPDF ignores box-sizing:border-box, so width/height are content-box:
           subtract the 3mm padding on each side. An exact page-sized block + the
           padding would otherwise overflow and spill a blank trailing page. */
        width: {{ $widthMm - 6 }}mm;
        height: {{ $heightMm - 6 }}mm;
        padding: 3mm;
        position: relative;
        overflow: hidden;
    }
    /* Page break is emitted explicitly by a .page-break div between labels,
       so we never rely on :last-child (poorly supported by DomPDF). */
    .page-break { page-break-before: always; }
    .label-grid {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .label-grid td { vertical-align: top; padding: 0; }
    .label-content { padding-right: 2mm; word-wrap: break-word; }
    .qr-cell { width: 20mm; text-align: right; vertical-align: top; }
    .qr { width: 18mm; height: 18mm; }
    .line { line-height: 1.25; margin-bottom: 0.4mm; }
    .wo-number { font-weight: bold; font-size: 12pt; }
    .product { font-size: 10pt; font-weight: 600; }
    .lot { font-size: 9pt; font-family: DejaVu Sans Mono, monospace; }
    .muted { color: #555; font-size: 8pt; }
    .barcode-row {
        position: absolute;
        left: 3mm;
        right: 3mm;
        bottom: 2mm;
        text-align: center;
    }
    .barcode {
        max-width: 100%;
        height: {{ max(8, $heightMm * 0.22) }}mm;
    }
    .barcode-value {
        font-family: DejaVu Sans Mono, monospace;
        font-size: 8pt;
        letter-spacing: 0.5px;
    }
</style>
