@php
    /**
     * Shared form fields for create.blade.php and edit.blade.php.
     *
     * Expected vars:
     *   - $event        ?MaintenanceEvent  (null on create)
     *   - $tools        Collection<Tool>
     *   - $lines        Collection<Line>
     *   - $workstations Collection<Workstation>
     *   - $costSources  Collection<CostSource>
     *   - $users        Collection<User>
     *
     * Renders WHAT / WHERE / WHEN / WHO / COST sections, plus RESOLUTION when
     * $event exists and is in_progress or completed.
     */
    /** @var \App\Models\MaintenanceEvent|null $event */
    $event ??= null;
    $isEdit = $event !== null;

    $oldType   = old('event_type',     $event?->event_type);
    $oldStatus = $event?->status;
    $showResolution = $isEdit && in_array($oldStatus, ['in_progress', 'completed'], true);
@endphp

{{-- WHAT --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('What') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title"
                   value="{{ old('title', $event?->title) }}"
                   class="form-input w-full"
                   placeholder="{{ __('e.g. Quarterly Inspection — Lathe #3') }}"
                   required maxlength="200">
            @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Event type') }} <span class="text-red-500">*</span></label>
            <select name="event_type" class="form-input w-full" required>
                <option value="">{{ __('— Select type —') }}</option>
                <option value="planned"    @selected($oldType === 'planned')>{{ __('Planned') }}</option>
                <option value="corrective" @selected($oldType === 'corrective')>{{ __('Corrective') }}</option>
                <option value="inspection" @selected($oldType === 'inspection')>{{ __('Inspection') }}</option>
            </select>
            @error('event_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="form-input w-full" maxlength="4000"
                      placeholder="{{ __('Optional context, checklist, parts needed…') }}">{{ old('description', $event?->description) }}</textarea>
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
                            @selected(old('line_id', $event?->line_id) == $line->id)>{{ $line->name }}</option>
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
                            @selected(old('workstation_id', $event?->workstation_id) == $ws->id)>{{ $ws->name }}</option>
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
                            @selected(old('tool_id', $event?->tool_id) == $tool->id)>{{ $tool->name }}</option>
                @endforeach
            </select>
            @error('tool_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- WHEN --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('When') }}</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="form-label">{{ __('Scheduled at') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" name="scheduled_at"
                   value="{{ old('scheduled_at', $event?->scheduled_at?->format('Y-m-d\TH:i')) }}"
                   class="form-input w-full" required>
            @error('scheduled_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
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
                            @selected(old('assigned_to_id', $event?->assigned_to_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            @error('assigned_to_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- COST --}}
<section class="card mb-4">
    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('Cost') }}</h2>
    <p class="text-xs text-gray-500 mb-3">
        {{ __('Optional — fill these for completed events to track actual spending.') }}
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="form-label">{{ __('Cost source') }}</label>
            <select name="cost_source_id" class="form-input w-full">
                <option value="">{{ __('— None —') }}</option>
                @foreach($costSources as $cs)
                    <option value="{{ $cs->id }}"
                            @selected(old('cost_source_id', $event?->cost_source_id) == $cs->id)>{{ $cs->name }}</option>
                @endforeach
            </select>
            @if($costSources->isEmpty())
                <p class="text-xs text-gray-400 mt-1">{{ __('No cost sources configured.') }}</p>
            @endif
            @error('cost_source_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Actual cost') }}</label>
            <input type="number" step="0.01" min="0" name="actual_cost"
                   value="{{ old('actual_cost', $event?->actual_cost) }}"
                   class="form-input w-full" placeholder="0.00">
            @error('actual_cost') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="form-label">{{ __('Currency') }}</label>
            <input type="text" name="currency"
                   value="{{ old('currency', $event?->currency) }}"
                   class="form-input w-full" placeholder="EUR" maxlength="10">
            @error('currency') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

{{-- RESOLUTION (edit only, when in_progress or completed) --}}
@if($showResolution)
    <section class="card mb-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">{{ __('Resolution') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">{{ __('Started at') }}</label>
                <input type="datetime-local" name="started_at"
                       value="{{ old('started_at', $event?->started_at?->format('Y-m-d\TH:i')) }}"
                       class="form-input w-full">
                @error('started_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Completed at') }}</label>
                <input type="datetime-local" name="completed_at"
                       value="{{ old('completed_at', $event?->completed_at?->format('Y-m-d\TH:i')) }}"
                       class="form-input w-full">
                @error('completed_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="form-label">{{ __('Resolution notes') }}</label>
                <textarea name="resolution_notes" rows="3" class="form-input w-full" maxlength="2000"
                          placeholder="{{ __('What was done, parts replaced, observations…') }}">{{ old('resolution_notes', $event?->resolution_notes) }}</textarea>
                @error('resolution_notes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>
@endif
