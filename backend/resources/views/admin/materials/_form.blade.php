<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
        <input type="text" name="code" id="code" value="{{ old('code', $material->code ?? '') }}" required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('code') border-red-500 @enderror" placeholder="e.g. PP-GRANULAT">
        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $material->name ?? '') }}" required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('name') border-red-500 @enderror">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="material_type_id" class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
        <select name="material_type_id" id="material_type_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('material_type_id') border-red-500 @enderror">
            <option value="">Select type...</option>
            @foreach($materialTypes as $type)
                <option value="{{ $type->id }}" {{ old('material_type_id', $material->material_type_id ?? '') == $type->id ? 'selected' : '' }}>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
        @error('material_type_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="unit_of_measure" class="block text-sm font-medium text-gray-700 mb-1">Unit of Measure</label>
        <input type="text" name="unit_of_measure" id="unit_of_measure" value="{{ old('unit_of_measure', $material->unit_of_measure ?? 'pcs') }}"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="pcs, kg, m, l...">
    </div>

    <div>
        <label for="tracking_type" class="block text-sm font-medium text-gray-700 mb-1">Tracking Type</label>
        <select name="tracking_type" id="tracking_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
            @foreach(['none' => 'None', 'batch' => 'Batch (LOT)', 'serial' => 'Serial'] as $value => $label)
                <option value="{{ $value }}" {{ old('tracking_type', $material->tracking_type ?? 'none') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="default_scrap_percentage" class="block text-sm font-medium text-gray-700 mb-1">Default Scrap %</label>
        <input type="number" name="default_scrap_percentage" id="default_scrap_percentage" step="0.01" min="0" max="100"
            value="{{ old('default_scrap_percentage', $material->default_scrap_percentage ?? 0) }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
    </div>
</div>

<div class="mt-6">
    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
    <textarea name="description" id="description" rows="3" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">{{ old('description', $material->description ?? '') }}</textarea>
</div>

<!-- External System -->
<div class="mt-6 pt-6 border-t">
    <h3 class="text-lg font-semibold text-gray-800 mb-4">External System</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="external_system" class="block text-sm font-medium text-gray-700 mb-1">System</label>
            <select name="external_system" id="external_system" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                <option value="">None</option>
                @foreach(['subiekt_gt' => 'Subiekt GT', 'subiekt_nexo' => 'Subiekt nexo', 'wms' => 'WMS', 'erp_custom' => 'Custom ERP'] as $value => $label)
                    <option value="{{ $value }}" {{ old('external_system', $material->external_system ?? '') === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="external_code" class="block text-sm font-medium text-gray-700 mb-1">External Code</label>
            <input type="text" name="external_code" id="external_code" value="{{ old('external_code', $material->external_code ?? '') }}"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="Code in external system">
        </div>
    </div>
</div>

<div class="mt-6">
    <label class="inline-flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-blue-600"
            {{ old('is_active', $material->is_active ?? true) ? 'checked' : '' }}>
        <span class="ml-2 text-sm text-gray-700">Active</span>
    </label>
</div>
