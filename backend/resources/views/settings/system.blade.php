@extends('layouts.app')

@section('title', __('System Settings'))

@section('content')
<div class="max-w-3xl mx-auto" x-data="{ tab: '{{ request('tab', 'general') }}' }">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('System Settings') }}</h1>
            <p class="text-gray-500 text-sm mt-0.5">{{ __('Global application configuration') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            <button type="button" @click="tab = 'general'"
                    :class="tab === 'general' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                {{ __('General') }}
            </button>
            <button type="button" @click="tab = 'production'"
                    :class="tab === 'production' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                {{ __('Production') }}
            </button>
            <button type="button" @click="tab = 'schedule'"
                    :class="tab === 'schedule' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                {{ __('Schedule') }}
            </button>
            <button type="button" @click="tab = 'security'"
                    :class="tab === 'security' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                {{ __('Security') }}
            </button>
            <button type="button" @click="tab = 'data'"
                    :class="tab === 'data' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition-colors">
                {{ __('Data') }}
            </button>
        </nav>
    </div>

    <form method="POST" action="{{ route('settings.update-system') }}" class="space-y-6">
        @csrf

        {{-- ═══ TAB: General ═══ --}}
        <div x-show="tab === 'general'" x-cloak>
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Language') }}</h2>
                <div class="mb-2">
                    <label class="form-label">{{ __('Select language') }}</label>
                    <select name="language" class="form-input w-full max-w-xs">
                        @foreach($availableLocales as $code => $name)
                            <option value="{{ $code }}" {{ ($settings['language'] ?? 'en') === $code ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-2">
                        Want to add a new language? Create a JSON file in <code>lang/</code> directory.
                        See <code>lang/en.json</code> as reference.
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══ TAB: Production ═══ --}}
        <div x-show="tab === 'production'" x-cloak class="space-y-6">

            {{-- Production Planning --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Production Planning') }}</h2>

                <div class="mb-4" x-data="{ val: '{{ $settings['production_period'] ?? 'none' }}' }">
                    <span class="form-label">{{ __('Production Period Split') }}</span>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ __('Determines how work orders are grouped for planning.') }}
                    </p>
                    <input type="hidden" name="production_period" :value="val">
                    <div class="grid grid-cols-3 gap-3">
                        @foreach(['none' => ['label' => __('None'), 'desc' => __('No period grouping')], 'weekly' => ['label' => __('Weekly'), 'desc' => __('Group by ISO week (1-53)')], 'monthly' => ['label' => __('Monthly'), 'desc' => __('Group by month (1-12)')]] as $value => $opt)
                            <div @click="val = '{{ $value }}'"
                                 :class="val === '{{ $value }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                 class="flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                                <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                                <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @error('production_period')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Workflow Mode --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Workflow Mode') }}</h2>
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('Defines how work order completion is tracked.') }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-data="{ val: '{{ $settings['workflow_mode'] ?? 'status' }}' }">
                    <input type="hidden" name="workflow_mode" :value="val">
                    @foreach([
                        'status' => [
                            'label' => __('Status'),
                            'desc'  => __('Work order status is changed manually. Board statuses are visual labels.'),
                        ],
                        'board_status' => [
                            'label' => __('Board Status'),
                            'desc'  => __('Moving to a Done status automatically closes the work order.'),
                        ],
                    ] as $value => $opt)
                        <div @click="val = '{{ $value }}'"
                             :class="val === '{{ $value }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                             class="flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                            <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                            <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                        </div>
                    @endforeach
                </div>
                @error('workflow_mode')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Production Rules --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Production Rules') }}</h2>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="pt-0.5">
                            <input type="hidden" name="allow_overproduction" value="0">
                            <input type="checkbox" name="allow_overproduction" value="1"
                                   class="rounded border-gray-300 text-blue-600"
                                   {{ ($settings['allow_overproduction'] ?? false) ? 'checked' : '' }}>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('Allow overproduction') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Allow operators to record more units than the planned quantity.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="pt-0.5">
                            <input type="hidden" name="force_sequential_steps" value="0">
                            <input type="checkbox" name="force_sequential_steps" value="1"
                                   class="rounded border-gray-300 text-blue-600"
                                   {{ ($settings['force_sequential_steps'] ?? true) ? 'checked' : '' }}>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('Force sequential steps') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Require production steps to be completed in defined order.') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Production Tracking Mode --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Production Tracking Mode') }}</h2>
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('How operators register production progress on the shop floor.') }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" x-data="{ val: '{{ $settings['production_tracking_mode'] ?? 'per_operation' }}' }">
                    <input type="hidden" name="production_tracking_mode" :value="val">
                    @foreach([
                        'per_operation' => [
                            'label' => __('Per Operation'),
                            'desc'  => __('Operator clicks Start/Complete on each step at each workstation. Full traceability.'),
                        ],
                        'cumulative' => [
                            'label' => __('Cumulative'),
                            'desc'  => __('Operator enters total produced quantity at the end. No step tracking.'),
                        ],
                        'hybrid' => [
                            'label' => __('Hybrid'),
                            'desc'  => __('Key steps tracked per-operation, quantity entry also available. Best of both.'),
                        ],
                    ] as $value => $opt)
                        <div @click="val = '{{ $value }}'"
                             :class="val === '{{ $value }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                             class="flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                            <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                            <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                        </div>
                    @endforeach
                </div>
                @error('production_tracking_mode')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ═══ TAB: Schedule ═══ --}}
        <div x-show="tab === 'schedule'" x-cloak>
            <div class="card space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Schedule / Planner') }}</h2>
                    <p class="text-xs text-gray-500 mb-4">
                        {{ __('Configure how the production schedule planner displays data.') }}
                    </p>
                </div>

                <div>
                    <label class="form-label">{{ __('View mode') }}</label>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ __('Default time scale for the schedule view.') }}
                    </p>
                    <div class="grid grid-cols-3 gap-3" x-data="{ val: '{{ $settings['schedule_view_mode'] ?? 'weekly' }}' }">
                        <input type="hidden" name="schedule_view_mode" :value="val">
                        @foreach(['weekly' => ['label' => __('Weekly'), 'desc' => __('Plan by week')], 'daily' => ['label' => __('Daily'), 'desc' => __('Plan by day')], 'monthly' => ['label' => __('Monthly'), 'desc' => __('Plan by month')]] as $value => $opt)
                            <div @click="val = '{{ $value }}'"
                                 :class="val === '{{ $value }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                 class="flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                                <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                                <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @error('schedule_view_mode')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Shifts per day') }}</label>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ __('Number of production shifts in a 24-hour period.') }}
                    </p>
                    <div class="grid grid-cols-4 gap-3" x-data="{ val: '{{ $settings['schedule_shifts_per_day'] ?? 1 }}' }">
                        <input type="hidden" name="schedule_shifts_per_day" :value="val">
                        @foreach([1, 2, 3, 4] as $value)
                            <div @click="val = '{{ $value }}'"
                                 :class="val == '{{ $value }}' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                 class="flex flex-col items-center gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                                <span class="font-medium text-sm text-gray-800">{{ $value }}</span>
                                <span class="text-xs text-gray-500">{{ (int)(24 / $value) }}h</span>
                            </div>
                        @endforeach
                    </div>
                    @error('schedule_shifts_per_day')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="schedule_horizon_weeks">{{ __('Planning horizon') }}</label>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ __('How many weeks ahead the planner displays.') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="number" name="schedule_horizon_weeks" id="schedule_horizon_weeks"
                               class="form-input w-24"
                               min="1" max="52"
                               value="{{ $settings['schedule_horizon_weeks'] ?? 6 }}">
                        <span class="text-sm text-gray-600">{{ __('weeks') }}</span>
                    </div>
                    @error('schedule_horizon_weeks')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="pt-0.5">
                            <input type="hidden" name="schedule_show_weekends" value="0">
                            <input type="checkbox" name="schedule_show_weekends" value="1"
                                   class="rounded border-gray-300 text-blue-600"
                                   {{ ($settings['schedule_show_weekends'] ?? true) ? 'checked' : '' }}>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('Show weekends') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Display Saturday and Sunday columns in the schedule view.') }}</p>
                        </div>
                    </label>
                </div>

                <div>
                    <label class="form-label">{{ __('Realtime updates') }}</label>
                    <p class="text-xs text-gray-500 mb-2">
                        {{ __('How the planner receives live updates from other users.') }}
                    </p>
                    @php $reverbAvailable = config('broadcasting.default') === 'reverb' || class_exists(\Laravel\Reverb\ServerManager::class); @endphp
                    <div class="grid grid-cols-2 gap-3" x-data="{ val: '{{ $settings['realtime_mode'] ?? 'polling' }}' }">
                        <input type="hidden" name="realtime_mode" :value="val">
                        <div @click="val = 'polling'"
                             :class="val === 'polling' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                             class="flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors">
                            <span class="font-medium text-sm text-gray-800">{{ __('Polling') }}</span>
                            <span class="text-xs text-gray-500">{{ __('Checks for changes every few seconds (default)') }}</span>
                        </div>
                        <div @if($reverbAvailable) @click="val = 'websocket'" @endif
                             :class="val === 'websocket' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 {{ $reverbAvailable ? 'hover:border-gray-300' : '' }}'"
                             class="flex flex-col gap-1 border rounded-lg p-3 transition-colors {{ $reverbAvailable ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}">
                            <span class="font-medium text-sm text-gray-800">{{ __('WebSocket') }}</span>
                            <span class="text-xs text-gray-500">{{ __('Instant updates via Laravel Reverb (requires Reverb server)') }}</span>
                            @if(!$reverbAvailable)
                                <span class="text-xs text-red-500 font-medium mt-1">{{ __('Reverb is not installed or configured') }}</span>
                            @endif
                        </div>
                    </div>
                    @error('realtime_mode')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══ TAB: Security ═══ --}}
        <div x-show="tab === 'security'" x-cloak class="space-y-6">

            {{-- Authentication --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Authentication') }}</h2>
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('Additional login methods for operators.') }}
                </p>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="pt-0.5">
                            <input type="hidden" name="pin_login_enabled" value="0">
                            <input type="checkbox" name="pin_login_enabled" value="1"
                                   class="rounded border-gray-300 text-blue-600"
                                   {{ ($settings['pin_login_enabled'] ?? false) ? 'checked' : '' }}>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ __('Enable PIN login') }}</p>
                            <p class="text-xs text-gray-500">
                                Allow users to set a 4–6 digit numeric PIN for quick sign-in.
                                Each user must first configure their PIN in Settings (requires current password).
                                PIN login does not replace password login — it is an alternative method.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- CORS --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('CORS (Cross-Origin Requests)') }}</h2>
                <p class="text-xs text-gray-500 mb-4">
                    {{ __('Control which external domains can make API requests to this application.') }}
                </p>

                <div>
                    <label class="form-label" for="cors_allowed_origins">{{ __('Allowed CORS Origins') }}</label>
                    <textarea name="cors_allowed_origins" id="cors_allowed_origins"
                              rows="3"
                              class="form-input w-full"
                              placeholder="https://example.com, https://app.example.com">{{ old('cors_allowed_origins', $settings['cors_allowed_origins'] ?? '*') }}</textarea>
                    <p class="text-xs text-gray-500 mt-2">
                        {{ __('Comma-separated list of allowed origins (e.g. https://example.com, https://app.example.com). Use * to allow all origins (not recommended for production).') }}
                    </p>
                    @error('cors_allowed_origins')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- ═══ TAB: Data ═══ --}}
        {{-- Save button (visible on all tabs except Data) --}}
        <div x-show="tab !== 'data'" class="flex justify-end">
            <button type="submit" class="btn-touch btn-primary">{{ __('Save') }}</button>
        </div>
    </form>

    {{-- Data tab content (outside form since it has its own form) --}}
    <div x-show="tab === 'data'" x-cloak>
        <div class="card border-amber-200 bg-amber-50">
            <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Sample Data') }}</h2>
            <p class="text-sm text-gray-600 mb-4">
                {{ __('Load a pre-built demo dataset: lines, workstations, products, templates and work orders. Safe to run multiple times.') }}
            </p>
            <form method="POST" action="{{ route('settings.sample-data') }}"
                  x-data="{ confirm: false }">
                @csrf
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" x-model="confirm" class="rounded border-gray-300 text-amber-500">
                        {{ __('I understand this will add demo data to the system') }}
                    </label>
                    <button type="submit"
                            :disabled="!confirm"
                            class="btn-touch px-4 py-2 text-sm font-medium rounded-lg border
                                   border-amber-400 bg-amber-100 text-amber-800
                                   hover:bg-amber-200
                                   disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        {{ __('Load Sample Data') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
