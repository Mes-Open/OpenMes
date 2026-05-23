{{-- Single label block. Expects: $label (array from LabelGenerator with has_qr/has_barcode/content_width precomputed), $template, $widthMm, $heightMm --}}
<div class="label">
    <table class="label-grid">
        <tr>
            <td class="label-content" style="width: {{ $label['content_width'] }}mm;">
                @if($template->hasField('wo_number') && !empty($label['fields']['wo_number']))
                    <div class="line wo-number">{{ $label['fields']['wo_number'] }}</div>
                @endif
                @if($template->hasField('product') && !empty($label['fields']['product']))
                    <div class="line product">{{ $label['fields']['product'] }}</div>
                @endif
                @if($template->hasField('quantity') && !empty($label['fields']['quantity']))
                    <div class="line">Qty: <strong>{{ $label['fields']['quantity'] }}</strong></div>
                @endif
                @if($template->hasField('lot') && !empty($label['fields']['lot']))
                    <div class="line lot">{{ $label['fields']['lot'] }}</div>
                @endif
                @if($template->hasField('prod_date') && !empty($label['fields']['prod_date']))
                    <div class="line muted">{{ $label['fields']['prod_date'] }}</div>
                @endif
            </td>
            @if($label['has_qr'])
                <td class="qr-cell">
                    <img src="{{ $label['qr_png'] }}" class="qr" />
                </td>
            @endif
        </tr>
    </table>
    @if($label['has_barcode'])
        <div class="barcode-row">
            <img src="{{ $label['barcode_png'] }}" class="barcode" />
            <div class="barcode-value">{{ $label['barcode_value'] }}</div>
        </div>
    @endif
</div>
