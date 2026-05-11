@php
    $fields = $template->fields_config ?? \Modules\Packaging\Models\LabelTemplate::defaultFieldsFor($template->type ?? \Modules\Packaging\Models\LabelTemplate::TYPE_WORK_ORDER);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="form-label">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $template->name) }}"
               class="form-input w-full" required maxlength="255">
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Type <span class="text-red-500">*</span></label>
        <select name="type" class="form-input w-full" required {{ isset($template->id) ? '' : '' }}>
            @foreach(\Modules\Packaging\Models\LabelTemplate::TYPES as $value => $label)
                <option value="{{ $value }}" {{ old('type', $template->type) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Label size <span class="text-red-500">*</span></label>
        <select name="size" class="form-input w-full" required>
            @foreach(\Modules\Packaging\Models\LabelTemplate::SIZES as $value => $label)
                <option value="{{ $value }}" {{ old('size', $template->size) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('size') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="form-label">Barcode format <span class="text-red-500">*</span></label>
        <select name="barcode_format" class="form-input w-full" required>
            @foreach(\Modules\Packaging\Models\LabelTemplate::BARCODE_FORMATS as $value => $label)
                <option value="{{ $value }}" {{ old('barcode_format', $template->barcode_format) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="form-label">Fields to include on label</label>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Toggle which fields appear on this template.</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
            @foreach(\Modules\Packaging\Models\LabelTemplate::AVAILABLE_FIELDS as $key => $label)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="fields[{{ $key }}]" value="1"
                           {{ old("fields.$key", $fields[$key] ?? false) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="md:col-span-2 flex items-center gap-6">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_default" value="1" {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="form-label mb-0">Default for this type</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="form-label mb-0">Active</span>
        </label>
    </div>
</div>
