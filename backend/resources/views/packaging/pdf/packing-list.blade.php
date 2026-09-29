<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ __('Packing list') }} {{ $list['pallet']['pallet_no'] }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; margin: 0; }
        h1 { font-size: 18pt; margin: 0 0 2mm; letter-spacing: -0.3pt; }
        .muted { color: #666; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        .head td { vertical-align: top; padding: 0; }
        .facts { width: 100%; border-collapse: collapse; }
        .facts td { padding: 1mm 4mm 1mm 0; vertical-align: top; }
        .facts .k { color: #666; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5pt; width: 32mm; }
        .qr { text-align: right; }
        .qr img { width: 26mm; height: 26mm; }
        .totals { margin: 0 0 5mm; padding: 3mm 4mm; background: #f3f1ec; border-radius: 2mm; }
        .totals b { font-size: 12pt; }
        h2 { font-size: 11pt; margin: 5mm 0 2mm; }
        table.units { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.units th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #666; border-bottom: 1px solid #999; padding: 1.5mm 2mm; }
        table.units td { padding: 1.4mm 2mm; border-bottom: 1px solid #e3e0d8; vertical-align: top; }
        table.units tr { page-break-inside: avoid; }
        .carton { margin-top: 4mm; }
        .carton .title { font-size: 10.5pt; font-weight: bold; }
        .status-scrapped { color: #b3261e; }
        .foot { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
        .sig { width: 100%; margin-top: 10mm; border-collapse: collapse; }
        .sig td { width: 33%; padding-top: 10mm; border-top: 1px solid #999; text-align: center; font-size: 8pt; color: #666; }
    </style>
</head>
<body>
@php($p = $list['pallet'])
@php($unitStatus = fn (string $s) => match ($s) {
    'in_production' => __('Unit in production'),
    'completed' => __('Unit completed'),
    'shipped' => __('Unit shipped'),
    'scrapped' => __('Unit scrapped'),
    'blocked' => __('Unit blocked'),
    default => $s,
})
<table class="head">
    <tr>
        <td>
            <h1>{{ __('Packing list') }} · <span class="mono">{{ $p['pallet_no'] }}</span></h1>
            <div class="muted">{{ __('Generated :at', ['at' => $generatedAt]) }}</div>
            <table class="facts" style="margin-top:4mm">
                <tr><td class="k">{{ __('Work order') }}</td><td class="mono">{{ $p['order_no'] ?? '—' }}</td><td class="k">{{ __('Product') }}</td><td>{{ $p['product'] ?? '—' }}@if($p['product_code']) <span class="muted mono">{{ $p['product_code'] }}</span>@endif</td></tr>
                <tr><td class="k">{{ __('Customer order') }}</td><td class="mono">{{ $p['customer_order_no'] ?? '—' }}</td><td class="k">{{ __('Status') }}</td><td>{{ __(ucfirst($p['status'])) }} · {{ __('Quality') }}: {{ __(ucfirst($p['quality_status'] ?? 'pending')) }}</td></tr>
                <tr><td class="k">{{ __('Destination') }}</td><td>{{ $p['destination'] ?? '—' }}</td><td class="k">{{ __('ERP reference') }}</td><td class="mono">{{ $p['erp_reference'] ?? '—' }}</td></tr>
                <tr><td class="k">{{ __('Created') }}</td><td>{{ $p['created_at'] ?? '—' }}</td><td class="k">{{ __('Shipped') }}</td><td>{{ $p['shipped_at'] ?? '—' }}</td></tr>
            </table>
        </td>
        <td class="qr" style="width:30mm"><img src="{{ $qrPng }}" alt="{{ $p['pallet_no'] }}"><div class="mono" style="font-size:8pt">{{ $p['pallet_no'] }}</div></td>
    </tr>
</table>

<div class="totals">
    <b>{{ $list['totals']['units'] }}</b> {{ __('pcs') }} &nbsp;·&nbsp; <b>{{ $list['totals']['cartons'] }}</b> {{ __('cartons') }}
    @if($list['totals']['scrapped'] > 0) &nbsp;·&nbsp; <span class="status-scrapped">{{ $list['totals']['scrapped'] }} {{ __('scrapped (not shipped)') }}</span>@endif
    @if($list['pallet']['qty'] !== $list['totals']['units']) &nbsp;·&nbsp; <span class="muted">{{ __('pallet count') }}: {{ $list['pallet']['qty'] }}</span>@endif
</div>

@foreach($list['cartons'] as $carton)
    <div class="carton">
        <div class="title mono">{{ $carton['carton_no'] }} <span class="muted" style="font-weight:normal;font-family:DejaVu Sans">· {{ count($carton['units']) }} {{ __('pcs') }} · {{ __(ucfirst($carton['status'])) }}@if($carton['closed_at']) · {{ $carton['closed_at'] }}@endif</span></div>
        <table class="units">
            <thead><tr><th style="width:8mm">#</th><th>{{ __('Serial number') }}</th><th>{{ __('Process serial (PSN)') }}</th><th>{{ __('Packed') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse($carton['units'] as $i => $u)
                <tr class="{{ $u['status'] === 'scrapped' ? 'status-scrapped' : '' }}"><td class="muted">{{ $i + 1 }}</td><td class="mono">{{ $u['serial_no'] }}</td><td class="mono">{{ $u['psn'] ?? '—' }}</td><td>{{ $u['packed_at'] ?? '—' }}</td><td>{{ $unitStatus($u['status']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('Empty carton') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endforeach

@if(count($list['loose_units']) > 0)
    <div class="carton">
        <div class="title">{{ __('Units packed loose on the pallet') }} <span class="muted" style="font-weight:normal">· {{ count($list['loose_units']) }}</span></div>
        <table class="units">
            <thead><tr><th style="width:8mm">#</th><th>{{ __('Serial number') }}</th><th>{{ __('Process serial (PSN)') }}</th><th>{{ __('Packed') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @foreach($list['loose_units'] as $i => $u)
                <tr class="{{ $u['status'] === 'scrapped' ? 'status-scrapped' : '' }}"><td class="muted">{{ $i + 1 }}</td><td class="mono">{{ $u['serial_no'] }}</td><td class="mono">{{ $u['psn'] ?? '—' }}</td><td>{{ $u['packed_at'] ?? '—' }}</td><td>{{ $unitStatus($u['status']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(count($list['cartons']) === 0 && count($list['loose_units']) === 0)
    <p class="muted">{{ __('No serialised units are recorded on this pallet.') }}</p>
@endif

<table class="sig">
    <tr><td>{{ __('Packed by') }}</td><td>{{ __('Checked by') }}</td><td>{{ __('Received by') }}</td></tr>
</table>
<div class="foot">{{ __('Packing list') }} {{ $p['pallet_no'] }} · OpenMES</div>
</body>
</html>
