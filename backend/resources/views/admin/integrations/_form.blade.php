<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="system_type" class="block text-sm font-medium text-gray-700 mb-1">System Type <span class="text-red-500">*</span></label>
        <select name="system_type" id="system_type" required class="input-field @error('system_type') border-red-500 @enderror">
            <option value="">Select...</option>
            @foreach(['subiekt_gt' => 'Subiekt GT', 'subiekt_nexo' => 'Subiekt nexo', 'wms' => 'WMS', 'erp_custom' => 'Custom ERP'] as $value => $label)
                <option value="{{ $value }}" {{ old('system_type', $integration->system_type ?? '') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('system_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="system_name" class="block text-sm font-medium text-gray-700 mb-1">Display Name <span class="text-red-500">*</span></label>
        <input type="text" name="system_name" id="system_name" value="{{ old('system_name', $integration->system_name ?? '') }}" required
            class="input-field @error('system_name') border-red-500 @enderror" placeholder="e.g. Subiekt GT Main Warehouse">
        @error('system_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6">
    <label class="inline-flex items-center">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-blue-600"
            {{ old('is_active', $integration->is_active ?? true) ? 'checked' : '' }}>
        <span class="ml-2 text-sm text-gray-700">Active</span>
    </label>
</div>
