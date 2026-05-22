@php
    /** @var \App\Models\Area|null $area */
    $area ??= null;
    $site ??= null;
@endphp

<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Identification') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Site') }} <span class="text-red-500">*</span></label>
            <select name="site_id" class="form-input w-full" required @if($site) disabled @endif>
                <option value="">{{ __('— Select site —') }}</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}"
                            @selected(old('site_id', $area?->site_id ?? $site?->id) == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            @if($site)
                <input type="hidden" name="site_id" value="{{ $site->id }}">
            @endif
            @error('site_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $area?->name) }}"
                   class="form-input w-full" required maxlength="255"
                   placeholder="{{ __('e.g. Assembly Hall A') }}">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
            <input type="text" name="code" value="{{ old('code', $area?->code) }}"
                   class="form-input w-full" required maxlength="50"
                   placeholder="{{ __('e.g. AREA-01') }}">
            @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description', $area?->description) }}</textarea>
            @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Status') }}</h2>
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1"
               {{ old('is_active', $area?->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="form-label mb-0">{{ __('Active') }}</span>
    </label>
</section>
