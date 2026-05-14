<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $lotSequence->name ?? '') }}" required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('name') border-red-500 @enderror" placeholder="e.g. Filter LOT">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="product_type_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Product Type') }}</label>
        <select name="product_type_id" id="product_type_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('product_type_id') border-red-500 @enderror">
            <option value="">{{ __('Default (all product types)') }}</option>
            @foreach($productTypes as $pt)
                <option value="{{ $pt->id }}" {{ old('product_type_id', $lotSequence->product_type_id ?? '') == $pt->id ? 'selected' : '' }}>
                    {{ $pt->name }} ({{ $pt->code }})
                </option>
            @endforeach
        </select>
        @error('product_type_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="prefix" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Prefix') }} <span class="text-red-500">*</span></label>
        <input type="text" name="prefix" id="prefix" value="{{ old('prefix', $lotSequence->prefix ?? '') }}" required
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('prefix') border-red-500 @enderror" placeholder="e.g. FLT, PP, UST">
        @error('prefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Suffix') }}</label>
        <input type="text" name="suffix" id="suffix" value="{{ old('suffix', $lotSequence->suffix ?? '') }}"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="Optional suffix">
    </div>

    <div>
        <label for="pad_size" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Number Padding') }}</label>
        <input type="number" name="pad_size" id="pad_size" min="1" max="10"
            value="{{ old('pad_size', $lotSequence->pad_size ?? 4) }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
        <p class="mt-1 text-xs text-gray-500">{{ __('Padding width. 4 = 0001, 6 = 000001') }}</p>
    </div>

    <div class="flex items-center pt-6">
        <label class="inline-flex items-center">
            <input type="hidden" name="year_prefix" value="0">
            <input type="checkbox" name="year_prefix" value="1" class="rounded border-gray-300 text-blue-600"
                {{ old('year_prefix', $lotSequence->year_prefix ?? true) ? 'checked' : '' }}>
            <span class="ml-2 text-sm text-gray-700">{{ __('Include year in LOT (e.g. FLT-2026-0001)') }}</span>
        </label>
    </div>
</div>

@php
    $previewPrefix = old('prefix', $lotSequence->prefix ?? 'LOT');
    $previewYear = old('year_prefix', $lotSequence->year_prefix ?? true) ? now()->format('Y').'-' : '';
    $previewPad = str_pad(old('next_number', $lotSequence->next_number ?? 1), old('pad_size', $lotSequence->pad_size ?? 4), '0', STR_PAD_LEFT);
    $previewSuffix = old('suffix', $lotSequence->suffix ?? '') ? '-'.old('suffix', $lotSequence->suffix ?? '') : '';
@endphp
<div class="mt-6 p-4 bg-blue-50 rounded-lg">
    <p class="text-sm text-blue-800">
        <span class="font-medium">{{ __('Preview:') }}</span>
        <span class="font-mono">{{ $previewPrefix }}-{{ $previewYear }}{{ $previewPad }}{{ $previewSuffix }}</span>
    </p>
</div>
