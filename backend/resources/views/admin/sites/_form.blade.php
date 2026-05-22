@php
    /** @var \App\Models\Site|null $site */
    $site ??= null;
    $isEdit = $site !== null;
@endphp

{{-- Identification --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Identification') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $site?->name) }}"
                   class="form-input w-full" required maxlength="255"
                   placeholder="{{ __('e.g. Warsaw Plant') }}">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
            <input type="text" name="code" value="{{ old('code', $site?->code) }}"
                   class="form-input w-full" required maxlength="50"
                   placeholder="{{ __('e.g. SITE-WAW-01') }}">
            @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Company') }}</label>
            <select name="company_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(old('company_id', $site?->company_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            @error('company_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="form-input w-full" maxlength="2000">{{ old('description', $site?->description) }}</textarea>
            @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- Location --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Location') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $site?->address) }}"
                   class="form-input w-full" maxlength="500">
            @error('address') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('City') }}</label>
            <input type="text" name="city" value="{{ old('city', $site?->city) }}"
                   class="form-input w-full" maxlength="100">
            @error('city') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Country (ISO-2)') }}</label>
            <input type="text" name="country" value="{{ old('country', $site?->country) }}"
                   class="form-input w-full uppercase" maxlength="2"
                   placeholder="PL">
            @error('country') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Timezone') }}</label>
            <input type="text" name="timezone" value="{{ old('timezone', $site?->timezone) }}"
                   class="form-input w-full" maxlength="50"
                   placeholder="Europe/Warsaw">
            @error('timezone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- Status --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Status') }}</h2>
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1"
               {{ old('is_active', $site?->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="form-label mb-0">{{ __('Active') }}</span>
    </label>
</section>
