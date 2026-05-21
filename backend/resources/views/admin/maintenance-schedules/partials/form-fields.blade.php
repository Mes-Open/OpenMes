@php
    /**
     * Shared form fields for create/edit a MaintenanceSchedule.
     *
     * Expected vars:
     *   - $schedule     ?MaintenanceSchedule  (null on create)
     *   - $tools        Collection<Tool>
     *   - $lines        Collection<Line>
     *   - $workstations Collection<Workstation>
     *   - $costSources  Collection<CostSource>
     *   - $users        Collection<User>
     *   - $frequencies  array<string>
     */
    /** @var \App\Models\MaintenanceSchedule|null $schedule */
    $schedule ??= null;
    $isEdit   = $schedule !== null;
@endphp

{{-- WHAT --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('What') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Schedule name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name"
                   value="{{ old('name', $schedule?->name) }}"
                   class="form-input w-full"
                   placeholder="{{ __('e.g. Weekly Lathe Lubrication') }}"
                   required maxlength="200">
            @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Event type') }} <span class="text-red-500">*</span></label>
            <select name="event_type" class="form-input w-full" required>
                @foreach(['planned','corrective','inspection'] as $t)
                    <option value="{{ $t }}" @selected(old('event_type', $schedule?->event_type ?? 'planned') === $t)>
                        {{ __(ucfirst($t)) }}
                    </option>
                @endforeach
            </select>
            @error('event_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Active') }}</label>
            <div class="flex items-center h-10">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $schedule?->is_active ?? true))
                       class="h-5 w-5 text-blue-600 border-gray-300 rounded">
                <span class="ml-2 text-sm text-gray-600">{{ __('Generate events automatically') }}</span>
            </div>
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="form-input w-full" maxlength="2000"
                      placeholder="{{ __('Checklist, parts needed, safety notes…') }}">{{ old('description', $schedule?->description) }}</textarea>
            @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- WHERE --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('Where') }}</h2>
    <p class="text-xs text-gray-500 mb-3">
        {{ __('Select at least one target — Line, Workstation, or Tool.') }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="form-label">{{ __('Production line') }}</label>
            <select name="line_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}"
                            @selected(old('line_id', $schedule?->line_id) == $line->id)>{{ $line->name }}</option>
                @endforeach
            </select>
            @error('line_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Workstation') }}</label>
            <select name="workstation_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($workstations as $ws)
                    <option value="{{ $ws->id }}"
                            @selected(old('workstation_id', $schedule?->workstation_id) == $ws->id)>{{ $ws->name }}</option>
                @endforeach
            </select>
            @error('workstation_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Tool') }}</label>
            <select name="tool_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($tools as $tool)
                    <option value="{{ $tool->id }}"
                            @selected(old('tool_id', $schedule?->tool_id) == $tool->id)>{{ $tool->name }}</option>
                @endforeach
            </select>
            @error('tool_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- WHEN — recurrence + next due --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('When') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="form-label">{{ __('Frequency') }} <span class="text-red-500">*</span></label>
            <select name="frequency" class="form-input w-full" required>
                @foreach($frequencies as $f)
                    <option value="{{ $f }}" @selected(old('frequency', $schedule?->frequency) === $f)>
                        {{ __(ucfirst(str_replace('_', ' ', $f))) }}
                    </option>
                @endforeach
            </select>
            @error('frequency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Every (N)') }} <span class="text-red-500">*</span></label>
            <input type="number" min="1" name="interval_value"
                   value="{{ old('interval_value', $schedule?->interval_value ?? 1) }}"
                   class="form-input w-full" required>
            <p class="text-xs text-gray-400 mt-1">{{ __('e.g. 2 weeks, 500 hours') }}</p>
            @error('interval_value') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Preferred time') }}</label>
            <input type="time" name="preferred_time"
                   value="{{ old('preferred_time', $schedule?->preferred_time ? \Carbon\Carbon::parse($schedule->preferred_time)->format('H:i') : '') }}"
                   class="form-input w-full">
            <p class="text-xs text-gray-400 mt-1">{{ __('Time of day for the generated event') }}</p>
            @error('preferred_time') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Lead time (days)') }}</label>
            <input type="number" min="0" max="30" name="lead_time_days"
                   value="{{ old('lead_time_days', $schedule?->lead_time_days ?? 0) }}"
                   class="form-input w-full">
            <p class="text-xs text-gray-400 mt-1">{{ __('Generate this many days before due') }}</p>
            @error('lead_time_days') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Next due at') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="next_due_at"
                   value="{{ old('next_due_at', $schedule?->next_due_at?->format('Y-m-d\TH:i')) }}"
                   class="form-input w-full" required>
            @error('next_due_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- WHO --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Who') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Assigned to') }}</label>
            <select name="assigned_to_id" class="form-input w-full">
                <option value="">{{ __('— Unassigned —') }}</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}"
                            @selected(old('assigned_to_id', $schedule?->assigned_to_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            @error('assigned_to_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Cost source') }}</label>
            <select name="cost_source_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($costSources as $cs)
                    <option value="{{ $cs->id }}"
                            @selected(old('cost_source_id', $schedule?->cost_source_id) == $cs->id)>{{ $cs->name }}</option>
                @endforeach
            </select>
            @error('cost_source_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
