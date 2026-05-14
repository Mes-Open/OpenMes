@extends('layouts.app')

@section('title', __('Schedule Maintenance Event'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Maintenance Events'), 'url' => route('admin.maintenance-events.index')],
    ['label' => __('Schedule Event'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Schedule Maintenance Event') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Plan a maintenance, inspection, or calibration') }}</p>
        </div>
        <a href="{{ route('admin.maintenance-events.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.maintenance-events.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Title') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}"
                           class="form-input w-full" placeholder="e.g. Quarterly Inspection — Lathe #3" required maxlength="200">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Event Type') }} <span class="text-red-500">*</span></label>
                    <select name="event_type" class="form-input w-full" required>
                        <option value="">{{ __('— Select type —') }}</option>
                        <option value="preventive" @selected(old('event_type') === 'preventive')>{{ __('Preventive') }}</option>
                        <option value="corrective" @selected(old('event_type') === 'corrective')>{{ __('Corrective') }}</option>
                        <option value="inspection" @selected(old('event_type') === 'inspection')>{{ __('Inspection') }}</option>
                        <option value="calibration" @selected(old('event_type') === 'calibration')>{{ __('Calibration') }}</option>
                    </select>
                    @error('event_type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Scheduled At') }} <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                           class="form-input w-full" required>
                    @error('scheduled_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Tool') }}</label>
                    <select name="tool_id" class="form-input w-full">
                        <option value="">{{ __('— Not a tool —') }}</option>
                        @foreach($tools as $tool)
                            <option value="{{ $tool->id }}" @selected(old('tool_id') == $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                    @error('tool_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Production Line') }}</label>
                    <select name="line_id" class="form-input w-full">
                        <option value="">{{ __('— Not a line —') }}</option>
                        @foreach($lines as $line)
                            <option value="{{ $line->id }}" @selected(old('line_id') == $line->id)>{{ $line->name }}</option>
                        @endforeach
                    </select>
                    @error('line_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Workstation') }}</label>
                    <select name="workstation_id" class="form-input w-full">
                        <option value="">{{ __('— Not a workstation —') }}</option>
                        @foreach($workstations as $ws)
                            <option value="{{ $ws->id }}" @selected(old('workstation_id') == $ws->id)>{{ $ws->name }}</option>
                        @endforeach
                    </select>
                    @error('workstation_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Cost Source') }}</label>
                    <select name="cost_source_id" class="form-input w-full">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach($costSources as $cs)
                            <option value="{{ $cs->id }}" @selected(old('cost_source_id') == $cs->id)>{{ $cs->name }}</option>
                        @endforeach
                    </select>
                    @error('cost_source_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Assigned To') }}</label>
                    <select name="assigned_to_id" class="form-input w-full">
                        <option value="">{{ __('— Unassigned —') }}</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_to_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __("Description") }}</label>
                    <textarea name="description" rows="3" class="form-input w-full" maxlength="4000">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.maintenance-events.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-touch btn-primary">{{ __('Schedule Event') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
