@php
    /** @var \App\Models\PersonnelClass|null $personnelClass */
    $pc = $personnelClass ?? null;
    $existingSkillIds = $pc?->required_skill_ids ?? [];
    $existingLevels   = $pc?->default_required_cert_level ?? [];
@endphp

<div
    x-data="{
        skillRows: @js(
            $skills->map(fn($s) => [
                'id'      => $s->id,
                'code'    => $s->code,
                'name'    => $s->name,
                'enabled' => in_array($s->id, $existingSkillIds, true),
                'level'   => $existingLevels[$s->id] ?? 'operator',
            ])
        )
    }"
>
    <div class="card mb-4">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">{{ __('Basic Information') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $pc?->code) }}"
                       class="form-input w-full" required maxlength="50">
                @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $pc?->name) }}"
                       class="form-input w-full" required maxlength="255">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="form-label">{{ __('Description') }}</label>
                <textarea name="description" rows="3" class="form-input w-full" maxlength="4000">{{ old('description', $pc?->description) }}</textarea>
                @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $pc?->is_active ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="form-label mb-0">{{ __('Active') }}</span>
                </label>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <h2 class="text-lg font-semibold text-gray-700 mb-1">{{ __('Required skills & minimum cert level') }}</h2>
        <p class="text-sm text-gray-500 mb-4">
            {{ __('Pick the skills required by this class and the minimum certification level for each.') }}
        </p>

        <template x-if="skillRows.length === 0">
            <p class="text-sm text-gray-500 italic">
                {{ __('No skills defined yet.') }}
                <a href="{{ route('admin.skills.create') }}" class="text-blue-600 hover:underline">{{ __('Add skills') }}</a>
                {{ __('first.') }}
            </p>
        </template>

        <div class="divide-y divide-gray-100">
            <template x-for="(row, idx) in skillRows" :key="row.id">
                <div class="flex items-center gap-3 py-2">
                    <label class="flex items-center gap-2 flex-1 cursor-pointer">
                        <input type="checkbox" x-model="row.enabled"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-800" x-text="row.name"></span>
                        <span class="text-xs text-gray-400 font-mono" x-text="row.code"></span>
                    </label>
                    <template x-if="row.enabled">
                        <div class="flex items-center gap-2">
                            <input type="hidden" :name="'required_skill_ids[]'" :value="row.id">
                            <select :name="'default_required_cert_level[' + row.id + ']'"
                                    x-model="row.level"
                                    class="form-input py-1 text-sm">
                                @foreach($levels as $lvl)
                                    <option value="{{ $lvl }}">{{ ucfirst($lvl) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        @error('required_skill_ids') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>
