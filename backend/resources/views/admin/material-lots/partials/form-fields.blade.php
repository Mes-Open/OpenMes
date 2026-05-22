@php
    /** @var \App\Models\MaterialLot $lot */
    $isNew = ! $lot->exists;
@endphp

<div class="card space-y-6">
    <div>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('What') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="lot_number">{{ __('Lot number') }} <span class="text-red-500">*</span></label>
                <input type="text" id="lot_number" name="lot_number" required maxlength="100"
                       value="{{ old('lot_number', $lot->lot_number) }}"
                       class="form-input w-full"
                       placeholder="BOLT-M10-2026-W21-001">
                @error('lot_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="material_id">{{ __('Material') }} <span class="text-red-500">*</span></label>
                <select id="material_id" name="material_id" required class="form-input w-full">
                    <option value="">— {{ __('Select') }} —</option>
                    @foreach($materials as $m)
                        <option value="{{ $m->id }}" @selected(old('material_id', $lot->material_id) == $m->id)>
                            {{ $m->code }} — {{ $m->name }}
                        </option>
                    @endforeach
                </select>
                @error('material_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="source_id">{{ __('Material Source') }}</label>
                <select id="source_id" name="source_id" class="form-input w-full">
                    <option value="">{{ __('None') }}</option>
                    @foreach($sources as $src)
                        <option value="{{ $src->id }}" @selected(old('source_id', $lot->source_id) == $src->id)>
                            {{ $src->external_name ?? $src->external_code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="status">{{ __('Status') }} <span class="text-red-500">*</span></label>
                <select id="status" name="status" required class="form-input w-full">
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected(old('status', $lot->status ?? 'received') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                @error('status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('Quantities') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="quantity_received">{{ __('Received') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.0001" min="0" id="quantity_received" name="quantity_received" required
                       value="{{ old('quantity_received', $lot->quantity_received) }}" class="form-input w-full">
                @error('quantity_received')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="quantity_available">{{ __('Available') }}</label>
                <input type="number" step="0.0001" min="0" id="quantity_available" name="quantity_available"
                       value="{{ old('quantity_available', $lot->quantity_available) }}" class="form-input w-full"
                       placeholder="{{ __('Defaults to received') }}">
                @error('quantity_available')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="unit_of_measure">{{ __('Unit') }} <span class="text-red-500">*</span></label>
                <input type="text" id="unit_of_measure" name="unit_of_measure" required maxlength="20"
                       value="{{ old('unit_of_measure', $lot->unit_of_measure ?? 'pcs') }}" class="form-input w-full">
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('When') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label" for="received_at">{{ __('Received at') }} <span class="text-red-500">*</span></label>
                <input type="datetime-local" id="received_at" name="received_at" required
                       value="{{ old('received_at', $lot->received_at?->format('Y-m-d\TH:i')) }}" class="form-input w-full">
                @error('received_at')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="form-label" for="manufacturing_date">{{ __('Manufacturing date') }}</label>
                <input type="date" id="manufacturing_date" name="manufacturing_date"
                       value="{{ old('manufacturing_date', $lot->manufacturing_date?->format('Y-m-d')) }}" class="form-input w-full">
            </div>
            <div>
                <label class="form-label" for="expiry_date">{{ __('Expiry date') }}</label>
                <input type="date" id="expiry_date" name="expiry_date"
                       value="{{ old('expiry_date', $lot->expiry_date?->format('Y-m-d')) }}" class="form-input w-full">
                @error('expiry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">{{ __('Where from') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label" for="supplier_lot_no">{{ __('Supplier lot no.') }}</label>
                <input type="text" id="supplier_lot_no" name="supplier_lot_no" maxlength="100"
                       value="{{ old('supplier_lot_no', $lot->supplier_lot_no) }}" class="form-input w-full">
            </div>
            <div>
                <label class="form-label" for="supplier_reference">{{ __('Supplier reference') }}</label>
                <input type="text" id="supplier_reference" name="supplier_reference" maxlength="255"
                       value="{{ old('supplier_reference', $lot->supplier_reference) }}"
                       class="form-input w-full"
                       placeholder="{{ __('PO number, invoice…') }}">
            </div>
        </div>
    </div>
</div>
