@php
    $fields = $template->fields_config ?? \App\Models\LabelTemplate::defaultFieldsFor($template->type ?? \App\Models\LabelTemplate::TYPE_WORK_ORDER);
@endphp

{{-- ── Basic info ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Template Details') }}</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $template->name) }}"
                   class="form-input w-full" required maxlength="255">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Type') }} <span class="text-red-500">*</span></label>
            <select name="type" class="form-input w-full" required>
                @foreach(\App\Models\LabelTemplate::TYPES as $value => $label)
                    <option value="{{ $value }}" {{ old('type', $template->type) === $value ? 'selected' : '' }}>{{ __($label) }}</option>
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
            <select name="size" class="form-input w-full" required>
                @foreach(\App\Models\LabelTemplate::SIZES as $value => $label)
                    <option value="{{ $value }}" {{ old('size', $template->size) === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
            </select>
            @error('size') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Barcode format') }} <span class="text-red-500">*</span></label>
            <select name="barcode_format" class="form-input w-full" required>
                @foreach(\App\Models\LabelTemplate::BARCODE_FORMATS as $value => $label)
                    <option value="{{ $value }}" {{ old('barcode_format', $template->barcode_format) === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- ── Fields ── --}}
<div class="card">
    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Fields to include on label') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ __('Toggle which fields appear on this template.') }}</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach(\App\Models\LabelTemplate::AVAILABLE_FIELDS as $key => $label)
            <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                <input type="checkbox" name="fields[{{ $key }}]" value="1"
                       {{ old("fields.$key", $fields[$key] ?? false) ? 'checked' : '' }}
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
