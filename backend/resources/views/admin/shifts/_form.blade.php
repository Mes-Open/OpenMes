<div class="mb-4">
    <label class="form-label">Name <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $shift->name ?? '') }}"
           class="form-input w-full @error('name') border-red-500 @enderror"
           required maxlength="50" placeholder="e.g. Morning Shift">
    @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="mb-4">
    <label class="form-label">Code <span class="text-red-500">*</span></label>
    <input type="text" name="code" value="{{ old('code', $shift->code ?? '') }}"
           class="form-input w-full @error('code') border-red-500 @enderror"
           required maxlength="10" placeholder="e.g. Z1">
    <p class="text-xs text-gray-400 mt-1">Short code displayed as column header in Workstation view (e.g. Z1, Z2, Z3).</p>
    @error('code')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <div>
        <label class="form-label">Start Time <span class="text-red-500">*</span></label>
        <input type="time" name="start_time" value="{{ old('start_time', $shift->start_time ? substr($shift->start_time, 0, 5) : '') }}"
               class="form-input w-full @error('start_time') border-red-500 @enderror" required>
        @error('start_time')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="form-label">End Time <span class="text-red-500">*</span></label>
        <input type="time" name="end_time" value="{{ old('end_time', $shift->end_time ? substr($shift->end_time, 0, 5) : '') }}"
               class="form-input w-full @error('end_time') border-red-500 @enderror" required>
        @error('end_time')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mb-4">
    <label class="form-label">Sort Order</label>
    <input type="number" name="sort_order" value="{{ old('sort_order', $shift->sort_order ?? 0) }}"
           class="form-input w-24" min="0">
    <p class="text-xs text-gray-400 mt-1">Lower numbers appear first as columns.</p>
</div>

<div class="mb-6">
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1"
               {{ old('is_active', $shift->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
    </label>
</div>
