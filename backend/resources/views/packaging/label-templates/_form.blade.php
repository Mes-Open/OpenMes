<div x-data="labelPreview({{ json_encode($previewInitial) }})"
     class="space-y-6">

{{-- ── Basic info ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Template Details') }}</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" x-model="data.name"
                   class="form-input w-full" required maxlength="255">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Type') }} <span class="text-red-500">*</span></label>
            <select name="type" x-model="data.type" class="form-input w-full" required>
                @foreach(\App\Models\LabelTemplate::TYPES as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- ── Layout ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Layout') }}</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Label size') }} <span class="text-red-500">*</span></label>
            <select name="size" x-model="data.size" class="form-input w-full" required>
                @foreach(\App\Models\LabelTemplate::SIZES as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
            @error('size') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div x-show="data.code_type === 'barcode'" x-cloak>
            <label class="form-label">{{ __('Barcode format') }} <span class="text-red-500">*</span></label>
            <select name="barcode_format" x-model="data.barcode_format" class="form-input w-full">
                @foreach(\App\Models\LabelTemplate::BARCODE_FORMATS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        {{-- Hidden so the field is still submitted when code_type != barcode --}}
        <template x-if="data.code_type !== 'barcode'">
            <input type="hidden" name="barcode_format" :value="data.barcode_format">
        </template>
    </div>
</div>

{{-- ── Code (barcode XOR qr) ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Code on label') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ __('Pick one machine-readable code. Mixing barcode and QR on the same label leads to scanning mistakes.') }}</p>

    {{-- Hidden inputs synced from code_type --}}
    <input type="hidden" name="fields[barcode]" :value="data.code_type === 'barcode' ? 1 : 0">
    <input type="hidden" name="fields[qr]"      :value="data.code_type === 'qr'      ? 1 : 0">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach([
            'none'    => ['title' => __('No code'),       'desc' => __('Text only - for visual labels or stickers.')],
            'barcode' => ['title' => __('Barcode (1D)'),  'desc' => __('Linear barcode (CODE 128 / EAN-13).')],
            'qr'      => ['title' => __('QR code'),       'desc' => __('2D QR code, scans from any angle.')],
        ] as $value => $opt)
            <div @click="data.code_type = '{{ $value }}'"
                 :class="data.code_type === '{{ $value }}' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300'"
                 class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer transition-all">
                <span class="mt-0.5 w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center"
                      :class="data.code_type === '{{ $value }}' ? 'border-blue-600' : 'border-gray-300'">
                    <span x-show="data.code_type === '{{ $value }}'" class="w-2 h-2 rounded-full bg-blue-600"></span>
                </span>
                <div class="min-w-0">
                    <p class="font-medium text-sm text-gray-800">{{ $opt['title'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $opt['desc'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- ── Other fields ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Other fields') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ __('Toggle which text fields appear on this template.') }}</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($otherFields as $key => $label)
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                <input type="hidden" name="fields[{{ $key }}]" value="0">
                <input type="checkbox" name="fields[{{ $key }}]" value="1"
                       x-model="data.fields['{{ $key }}']"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">{{ __($label) }}</span>
            </label>
        @endforeach
    </div>
</div>

{{-- ── Visibility ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Visibility') }}</h2>

    <div class="space-y-3">
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="is_default" value="1" {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <div>
                <span class="block text-sm font-medium text-gray-800">{{ __('Default for this type') }}</span>
                <span class="block text-xs text-gray-500">{{ __('Used automatically when no template is selected.') }}</span>
            </div>
        </label>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <div>
                <span class="block text-sm font-medium text-gray-800">{{ __('Active') }}</span>
                <span class="block text-xs text-gray-500">{{ __('Inactive templates are hidden from print menus.') }}</span>
            </div>
        </label>
    </div>
</div>

{{-- ── Preview ── --}}
<div class="card">
    <div class="flex items-center justify-between mb-1">
        <h2 class="text-lg font-bold text-gray-800">{{ __('Preview') }}</h2>
        <span class="text-xs font-mono text-gray-500"
              x-text="`${dims().w} × ${dims().h} mm`"></span>
    </div>
    <p class="text-sm text-gray-500 mb-4">{{ __('Live preview with sample data. Real codes are rendered as PNG when printed.') }}</p>

    <div class="flex justify-center items-center rounded-xl py-10 px-4"
         style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">

        {{-- Label paper --}}
        <div class="bg-white rounded-md relative flex flex-col overflow-hidden text-black"
             :style="`
                width:${dims().w * 4}px;
                height:${dims().h * 4}px;
                padding:${Math.max(6, dims().w * 0.05)}px ${Math.max(8, dims().w * 0.06)}px;
                box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 8px 24px -8px rgba(15,23,42,.18), 0 0 0 1px rgba(15,23,42,.06);
             `">

            {{-- Top header: logo + WO badge --}}
            <div class="flex items-center justify-between gap-2 mb-1 shrink-0"
                 x-show="data.fields.logo || data.fields.wo_number">
                <template x-if="data.fields.logo">
                    <div class="flex items-center gap-1.5">
                        <div class="w-5 h-5 rounded bg-black flex items-center justify-center text-white text-[8px] font-black">M</div>
                        <span class="text-[9px] font-bold tracking-wider text-black">OPENMES</span>
                    </div>
                </template>
                <template x-if="!data.fields.logo">
                    <span></span>
                </template>
                <template x-if="data.fields.wo_number">
                    <span class="font-mono text-[10px] font-bold text-black">
                        WO-2026-0142
                    </span>
                </template>
            </div>

            {{-- Body: text on left, QR on right --}}
            <div class="flex items-stretch gap-3 flex-1 min-h-0">
                {{-- Text column --}}
                <div class="flex-1 min-w-0 flex flex-col justify-center gap-0.5">
                    <template x-if="data.fields.product">
                        <div class="font-bold leading-tight text-black truncate"
                             :style="`font-size:${Math.max(11, dims().h * 0.16)}px`">
                            MDF Board 18mm white
                        </div>
                    </template>

                    <template x-if="data.fields.quantity">
                        <div class="flex items-baseline gap-1 mt-0.5 text-black">
                            <span class="text-[9px] uppercase tracking-wider text-gray-500 font-semibold">Qty</span>
                            <span class="font-bold"
                                  :style="`font-size:${Math.max(12, dims().h * 0.18)}px`">150</span>
                            <span class="text-[9px] text-gray-600">szt.</span>
                        </div>
                    </template>

                    <div class="flex items-center gap-2 mt-1 text-black"
                         x-show="data.fields.lot || data.fields.prod_date">
                        <template x-if="data.fields.lot">
                            <span class="font-mono text-[10px]">LOT&nbsp;A23-2026</span>
                        </template>
                        <template x-if="data.fields.prod_date">
                            <span class="font-mono text-[10px] text-gray-600">{{ now()->format('Y-m-d') }}</span>
                        </template>
                    </div>
                </div>

                {{-- QR code column --}}
                <template x-if="data.code_type === 'qr'">
                    <div class="shrink-0 flex items-center">
                        <svg :width="Math.max(40, dims().h * 0.62)" :height="Math.max(40, dims().h * 0.62)"
                             viewBox="0 0 33 33" xmlns="http://www.w3.org/2000/svg"
                             shape-rendering="crispEdges" class="block">
                            <rect width="33" height="33" fill="#fff"/>
                            {{-- 3 finder patterns --}}
                            <g fill="#000">
                                <path d="M0,0h7v7h-7zM1,1v5h5v-5zM2,2h3v3h-3z M26,0h7v7h-7zM27,1v5h5v-5zM28,2h3v3h-3z M0,26h7v7h-7zM1,27v5h5v-5zM2,28h3v3h-3z"/>
                            </g>
                            {{-- Timing patterns --}}
                            <g fill="#000">
                                <rect x="8"  y="6" width="1" height="1"/><rect x="10" y="6" width="1" height="1"/>
                                <rect x="12" y="6" width="1" height="1"/><rect x="14" y="6" width="1" height="1"/>
                                <rect x="16" y="6" width="1" height="1"/><rect x="18" y="6" width="1" height="1"/>
                                <rect x="20" y="6" width="1" height="1"/><rect x="22" y="6" width="1" height="1"/>
                                <rect x="24" y="6" width="1" height="1"/>
                                <rect x="6" y="8"  width="1" height="1"/><rect x="6" y="10" width="1" height="1"/>
                                <rect x="6" y="12" width="1" height="1"/><rect x="6" y="14" width="1" height="1"/>
                                <rect x="6" y="16" width="1" height="1"/><rect x="6" y="18" width="1" height="1"/>
                                <rect x="6" y="20" width="1" height="1"/><rect x="6" y="22" width="1" height="1"/>
                                <rect x="6" y="24" width="1" height="1"/>
                            </g>
                            {{-- Alignment pattern (bottom-right) --}}
                            <g fill="#000">
                                <rect x="24" y="24" width="5" height="5"/>
                                <rect x="25" y="25" width="3" height="3" fill="#fff"/>
                                <rect x="26" y="26" width="1" height="1"/>
                            </g>
                            {{-- Data modules --}}
                            <g fill="#000">
                                <rect x="9"  y="8"  width="1" height="1"/><rect x="11" y="8" width="2" height="1"/>
                                <rect x="14" y="8"  width="1" height="2"/><rect x="16" y="9" width="1" height="1"/>
                                <rect x="18" y="8"  width="2" height="2"/><rect x="21" y="8" width="1" height="3"/>
                                <rect x="23" y="9"  width="2" height="1"/><rect x="8" y="10" width="1" height="2"/>
                                <rect x="10" y="11" width="2" height="1"/><rect x="13" y="11" width="1" height="2"/>
                                <rect x="15" y="10" width="1" height="3"/><rect x="17" y="11" width="2" height="1"/>
                                <rect x="20" y="10" width="1" height="2"/><rect x="22" y="11" width="1" height="2"/>
                                <rect x="24" y="10" width="2" height="1"/><rect x="9"  y="13" width="2" height="1"/>
                                <rect x="12" y="13" width="1" height="2"/><rect x="14" y="14" width="1" height="1"/>
                                <rect x="16" y="13" width="2" height="2"/><rect x="19" y="13" width="1" height="2"/>
                                <rect x="21" y="14" width="2" height="1"/><rect x="24" y="13" width="1" height="2"/>
                                <rect x="26" y="13" width="2" height="1"/><rect x="29" y="13" width="2" height="1"/>
                                <rect x="31" y="14" width="1" height="2"/><rect x="8"  y="15" width="1" height="2"/>
                                <rect x="10" y="15" width="1" height="2"/><rect x="13" y="16" width="2" height="1"/>
                                <rect x="16" y="16" width="1" height="2"/><rect x="18" y="17" width="2" height="1"/>
                                <rect x="21" y="16" width="1" height="2"/><rect x="23" y="17" width="2" height="1"/>
                                <rect x="26" y="16" width="2" height="1"/><rect x="29" y="16" width="1" height="2"/>
                                <rect x="9"  y="18" width="2" height="1"/><rect x="12" y="18" width="1" height="2"/>
                                <rect x="14" y="19" width="2" height="1"/><rect x="17" y="18" width="1" height="2"/>
                                <rect x="19" y="19" width="2" height="1"/><rect x="22" y="19" width="1" height="2"/>
                                <rect x="8"  y="20" width="1" height="1"/><rect x="11" y="20" width="2" height="1"/>
                                <rect x="13" y="21" width="1" height="2"/><rect x="15" y="21" width="2" height="1"/>
                                <rect x="18" y="20" width="1" height="2"/><rect x="20" y="21" width="2" height="1"/>
                                <rect x="23" y="20" width="1" height="2"/>
                                <rect x="9"  y="23" width="2" height="2"/><rect x="12" y="24" width="2" height="1"/>
                                <rect x="15" y="23" width="1" height="2"/><rect x="17" y="24" width="2" height="1"/>
                                <rect x="20" y="23" width="1" height="2"/><rect x="22" y="23" width="2" height="1"/>
                                <rect x="8"  y="26" width="2" height="1"/><rect x="11" y="27" width="1" height="2"/>
                                <rect x="13" y="26" width="1" height="3"/><rect x="15" y="27" width="2" height="1"/>
                                <rect x="18" y="26" width="1" height="2"/><rect x="20" y="27" width="2" height="1"/>
                                <rect x="22" y="27" width="1" height="2"/>
                                <rect x="9"  y="30" width="2" height="1"/><rect x="12" y="30" width="1" height="2"/>
                                <rect x="14" y="31" width="2" height="1"/><rect x="17" y="30" width="1" height="2"/>
                                <rect x="19" y="31" width="2" height="1"/><rect x="22" y="30" width="1" height="2"/>
                            </g>
                        </svg>
                    </div>
                </template>
            </div>

            {{-- Footer: barcode (centered, ~60% width) --}}
            <template x-if="data.code_type === 'barcode'">
                <div class="shrink-0 flex flex-col items-center mt-1">
                    <svg :width="dims().w * 3.2"
                         :height="dims().w * 0.64"
                         viewBox="0 0 200 40"
                         preserveAspectRatio="xMidYMid meet"
                         xmlns="http://www.w3.org/2000/svg"
                         shape-rendering="crispEdges"
                         class="block">
                        <rect width="200" height="40" fill="#fff"/>
                        <g fill="#000">
                            {{-- CODE-128-style integer bars. Total 200. --}}
                            <rect x="2"   y="0" width="2" height="40"/>
                            <rect x="6"   y="0" width="1" height="40"/>
                            <rect x="9"   y="0" width="3" height="40"/>
                            <rect x="14"  y="0" width="1" height="40"/>
                            <rect x="17"  y="0" width="2" height="40"/>
                            <rect x="21"  y="0" width="2" height="40"/>
                            <rect x="25"  y="0" width="1" height="40"/>
                            <rect x="28"  y="0" width="3" height="40"/>
                            <rect x="33"  y="0" width="2" height="40"/>
                            <rect x="37"  y="0" width="1" height="40"/>
                            <rect x="40"  y="0" width="1" height="40"/>
                            <rect x="43"  y="0" width="3" height="40"/>
                            <rect x="48"  y="0" width="1" height="40"/>
                            <rect x="51"  y="0" width="2" height="40"/>
                            <rect x="55"  y="0" width="1" height="40"/>
                            <rect x="58"  y="0" width="2" height="40"/>
                            <rect x="62"  y="0" width="3" height="40"/>
                            <rect x="67"  y="0" width="1" height="40"/>
                            <rect x="70"  y="0" width="2" height="40"/>
                            <rect x="74"  y="0" width="1" height="40"/>
                            <rect x="77"  y="0" width="3" height="40"/>
                            <rect x="82"  y="0" width="1" height="40"/>
                            <rect x="85"  y="0" width="2" height="40"/>
                            <rect x="89"  y="0" width="2" height="40"/>
                            <rect x="93"  y="0" width="1" height="40"/>
                            <rect x="96"  y="0" width="3" height="40"/>
                            <rect x="101" y="0" width="2" height="40"/>
                            <rect x="105" y="0" width="1" height="40"/>
                            <rect x="108" y="0" width="1" height="40"/>
                            <rect x="111" y="0" width="3" height="40"/>
                            <rect x="116" y="0" width="2" height="40"/>
                            <rect x="120" y="0" width="1" height="40"/>
                            <rect x="123" y="0" width="2" height="40"/>
                            <rect x="127" y="0" width="3" height="40"/>
                            <rect x="132" y="0" width="1" height="40"/>
                            <rect x="135" y="0" width="2" height="40"/>
                            <rect x="139" y="0" width="1" height="40"/>
                            <rect x="142" y="0" width="3" height="40"/>
                            <rect x="147" y="0" width="2" height="40"/>
                            <rect x="151" y="0" width="1" height="40"/>
                            <rect x="154" y="0" width="2" height="40"/>
                            <rect x="158" y="0" width="2" height="40"/>
                            <rect x="162" y="0" width="1" height="40"/>
                            <rect x="165" y="0" width="3" height="40"/>
                            <rect x="170" y="0" width="1" height="40"/>
                            <rect x="173" y="0" width="2" height="40"/>
                            <rect x="177" y="0" width="1" height="40"/>
                            <rect x="180" y="0" width="3" height="40"/>
                            <rect x="185" y="0" width="2" height="40"/>
                            <rect x="189" y="0" width="1" height="40"/>
                            <rect x="192" y="0" width="2" height="40"/>
                            <rect x="196" y="0" width="2" height="40"/>
                        </g>
                    </svg>
                    <div class="text-center font-mono mt-0.5 tracking-widest text-black"
                         :style="`font-size:${Math.max(8, dims().h * 0.10)}px`"
                         x-text="data.barcode_format === 'ean13' ? '5 901234 123457' : 'WO-2026-0142'"></div>
                </div>
            </template>
        </div>
    </div>

    {{-- Legend below the label --}}
    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 mt-4 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
            <span x-text="data.code_type === 'barcode' ? data.barcode_format.toUpperCase() : (data.code_type === 'qr' ? 'QR Code' : @json(__('Text only')))"></span>
        </span>
        <span class="text-gray-300">·</span>
        <span x-text="`${activeFieldsCount()} ${@json(__('fields'))}`"></span>
    </div>
</div>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function labelPreview(initial) {
    return {
        data: initial,
        dims() {
            const parts = (this.data.size || '100x50').split('x');
            return {
                w: parseInt(parts[0]) || 100,
                h: parseInt(parts[1]) || 50,
            };
        },
        activeFieldsCount() {
            let n = 0;
            for (const k of ['wo_number','product','quantity','logo','lot','prod_date']) {
                if (this.data.fields[k]) n++;
            }
            if (this.data.code_type !== 'none') n++;
            return n;
        },
    };
}
</script>
@endpush
