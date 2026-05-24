@php
    /**
     * Shared form fields for create.blade.php and edit.blade.php.
     *
     * Expected vars:
     *   - $segment           ?ProcessSegment (null on create)
     *   - $workstationTypes  Collection<WorkstationType>
     *   - $skills            Collection<Skill>
     *   - $segmentTypes      array<int,string>
     */
    /** @var \App\Models\ProcessSegment|null $segment */
    $segment ??= null;
    $isEdit  = $segment !== null;

    $existingSkillIds = old('required_skill_ids', $segment?->required_skill_ids ?? []);
    if (! is_array($existingSkillIds)) {
        $existingSkillIds = [];
    }

    // Pretty-print parameters as JSON for the textarea.
    if (old('parameters_raw') !== null) {
        $parametersRaw = old('parameters_raw');
    } elseif ($segment && ! empty($segment->parameters)) {
        $parametersRaw = json_encode($segment->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $parametersRaw = '';
    }
@endphp

{{-- WHAT --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('What') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
            <input type="text" name="code"
                   value="{{ old('code', $segment?->code) }}"
                   class="form-input w-full font-mono"
                   placeholder="PSG-0001" required maxlength="50">
            @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Segment type') }} <span class="text-red-500">*</span></label>
            <select name="segment_type" class="form-input w-full" required>
                @foreach($segmentTypes as $t)
                    <option value="{{ $t }}" @selected(old('segment_type', $segment?->segment_type) === $t)>
                        {{ ucfirst($t) }}
                    </option>
                @endforeach
            </select>
            @error('segment_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name"
                   value="{{ old('name', $segment?->name) }}"
                   class="form-input w-full"
                   placeholder="{{ __('e.g. Injection Molding 60s cycle') }}"
                   required maxlength="255">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" rows="2" class="form-input w-full" maxlength="4000"
                      placeholder="{{ __('Short summary of what this operation accomplishes…') }}">{{ old('description', $segment?->description) }}</textarea>
            @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- EXECUTION DEFAULTS --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('Execution defaults') }}</h2>
    <p class="text-xs text-gray-500 mb-3">
        {{ __('Standard execution context — used as defaults when a template step references this segment.') }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="form-label">{{ __('Workstation type') }}</label>
            <select name="workstation_type_id" class="form-input w-full">
                <option value="">{{ __('— Any —') }}</option>
                @foreach($workstationTypes as $wt)
                    <option value="{{ $wt->id }}"
                            @selected(old('workstation_type_id', $segment?->workstation_type_id) == $wt->id)>{{ $wt->name }}</option>
                @endforeach
            </select>
            @error('workstation_type_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Estimated duration (min)') }}</label>
            <input type="number" name="estimated_duration_minutes" min="0" max="100000"
                   value="{{ old('estimated_duration_minutes', $segment?->estimated_duration_minutes) }}"
                   class="form-input w-full" placeholder="60">
            @error('estimated_duration_minutes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Required operators') }}</label>
            <input type="number" name="required_operators" min="1" max="50"
                   value="{{ old('required_operators', $segment?->required_operators ?? 1) }}"
                   class="form-input w-full" required>
            @error('required_operators') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- STANDARDS --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Standards') }}</h2>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label class="form-label">{{ __('Standard instruction') }}</label>
            <textarea name="standard_instruction" rows="4" class="form-input w-full" maxlength="8000"
                      placeholder="{{ __('Default work instruction shared across all uses of this segment.') }}">{{ old('standard_instruction', $segment?->standard_instruction) }}</textarea>
            @error('standard_instruction') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Required skills') }}</label>
            @if($skills->isEmpty())
                <p class="text-xs text-gray-400">{{ __('No skills configured yet.') }}</p>
            @else
                <select name="required_skill_ids[]" multiple class="form-input w-full" size="{{ min($skills->count(), 6) }}">
                    @foreach($skills as $skill)
                        <option value="{{ $skill->id }}"
                                @selected(in_array($skill->id, $existingSkillIds))>
                            {{ $skill->code }} — {{ $skill->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">{{ __('Hold Ctrl/Cmd to select multiple. Operators must have ALL of these skills to execute the segment.') }}</p>
            @endif
            @error('required_skill_ids') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            @error('required_skill_ids.*') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Parameters (JSON)') }}</label>
            <textarea name="parameters_raw" rows="5"
                      class="form-input w-full font-mono text-xs"
                      placeholder='{"temperature_c": 220, "pressure_bar": 5}'>{{ $parametersRaw }}</textarea>
            <p class="text-xs text-gray-500 mt-1">
                {{ __('Optional JSON object — e.g. temperature, pressure, sample size, torque. Leave empty for none.') }}
            </p>
            @error('parameters_raw') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- STATUS --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Status') }}</h2>
    <label class="inline-flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1"
               class="h-5 w-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
               @checked(old('is_active', $segment?->is_active ?? true))>
        <span class="text-sm text-gray-700">{{ __('Active (available for use in process templates)') }}</span>
    </label>
</section>
