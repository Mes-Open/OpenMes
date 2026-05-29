@php $m = $connection->modbusConnection ?? null; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $connection->name) }}" class="form-input w-full" required>
        @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="form-label">{{ __('Description') }}</label>
        <input type="text" name="description" value="{{ old('description', $connection->description) }}" class="form-input w-full">
    </div>
    <div>
        <label class="form-label">{{ __('Host') }} <span class="text-red-500">*</span></label>
        <input type="text" name="host" value="{{ old('host', $m?->host) }}" class="form-input w-full" placeholder="192.168.1.50" required>
        @error('host')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="form-label">{{ __('Port') }}</label>
        <input type="number" name="port" value="{{ old('port', $m?->port ?? 502) }}" class="form-input w-full" required>
    </div>
    <div>
        <label class="form-label">{{ __('Unit ID') }}</label>
        <input type="number" name="unit_id" value="{{ old('unit_id', $m?->unit_id ?? 1) }}" class="form-input w-full" required>
    </div>
    <div>
        <label class="form-label">{{ __('Poll interval (ms)') }}</label>
        <input type="number" name="poll_interval_ms" value="{{ old('poll_interval_ms', $m?->poll_interval_ms ?? 1000) }}" class="form-input w-full" required>
    </div>
    <div>
        <label class="form-label">{{ __('Timeout (seconds)') }}</label>
        <input type="number" name="timeout_seconds" value="{{ old('timeout_seconds', $m?->timeout_seconds ?? 3) }}" class="form-input w-full" required>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="form-label">{{ __('Byte order') }}</label>
            <select name="byte_order" class="form-input w-full">
                <option value="big" @selected(old('byte_order', $m?->byte_order)==='big')>Big-endian</option>
                <option value="little" @selected(old('byte_order', $m?->byte_order)==='little')>Little-endian</option>
            </select>
        </div>
        <div>
            <label class="form-label">{{ __('Word order') }}</label>
            <select name="word_order" class="form-input w-full">
                <option value="big" @selected(old('word_order', $m?->word_order)==='big')>Big</option>
                <option value="little" @selected(old('word_order', $m?->word_order)==='little')>Little</option>
            </select>
        </div>
    </div>
    <div class="sm:col-span-2">
        <label class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-blue-600" {{ old('is_active', $connection->is_active ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">{{ __('Active') }}</span>
        </label>
    </div>
</div>
