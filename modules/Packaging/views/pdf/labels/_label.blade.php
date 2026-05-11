{{-- Single label block. Expects: $label (array from LabelGenerator), $template (LabelTemplate), $widthMm, $heightMm --}}
@php
    $fields = $label['fields'];
    $hasQr = $template->hasField('qr') && !empty($label['qr_png']);
    $hasBarcode = $template->hasField('barcode') && !empty($label['barcode_png']);
    $contentWidth = $hasQr ? ($widthMm - 22) : ($widthMm - 6);
@endphp
<div class="label">
    <table class="label-grid">
        <tr>
            <td class="label-content" style="width: {{ $contentWidth }}mm;">
                @if($template->hasField('wo_number') && !empty($fields['wo_number']))
                    <div class="line wo-number">{{ $fields['wo_number'] }}</div>
                @endif
                @if($template->hasField('product') && !empty($fields['product']))
                    <div class="line product">{{ $fields['product'] }}</div>
                @endif
                @if($template->hasField('quantity') && !empty($fields['quantity']))
                    <div class="line">Qty: <strong>{{ $fields['quantity'] }}</strong></div>
                @endif
                @if($template->hasField('lot') && !empty($fields['lot']))
                    <div class="line lot">{{ $fields['lot'] }}</div>
                @endif
                @if($template->hasField('prod_date') && !empty($fields['prod_date']))
                    <div class="line muted">{{ $fields['prod_date'] }}</div>
                @endif
            </td>
            @if($hasQr)
                <td class="qr-cell">
                    <img src="{{ $label['qr_png'] }}" class="qr" />
                </td>
            @endif
        </tr>
    </table>
    @if($hasBarcode)
        <div class="barcode-row">
            <img src="{{ $label['barcode_png'] }}" class="barcode" />
            <div class="barcode-value">{{ $label['barcode_value'] }}</div>
        </div>
    @endif
</div>
