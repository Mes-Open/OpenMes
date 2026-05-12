@extends('layouts.app')

@section('title', __('System Settings'))

@section('content')
<div class="max-w-2xl mx-auto">

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

    <form method="POST" action="{{ route('settings.update-system') }}" class="space-y-6">
        @csrf

        {{-- Language --}}
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

        {{-- {{ __('Production Planning') }} --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-4">{{ __('Production Planning') }}</h2>

            <div class="mb-4">
                <label class="form-label">{{ __('Production Period Split') }}</label>
                <p class="text-xs text-gray-500 mb-2">
                    {{ __('Determines how work orders are grouped for planning.') }}
                </p>
                <div class="grid grid-cols-3 gap-3">
                    @foreach(['none' => ['label' => __('None'), 'desc' => __('No period grouping')], 'weekly' => ['label' => __('Weekly'), 'desc' => __('Group by ISO week (1-53)')], 'monthly' => ['label' => __('Monthly'), 'desc' => __('Group by month (1-12)')]] as $value => $opt)
                        <label class="relative flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors
                            {{ ($settings['production_period'] ?? 'none') === $value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" name="production_period" value="{{ $value }}"
                                   class="sr-only"
                                   {{ ($settings['production_period'] ?? 'none') === $value ? 'checked' : '' }}>
                            <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                            <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('production_period')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- {{ __('Workflow Mode') }} --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-1">{{ __('Workflow Mode') }}</h2>
            <p class="text-xs text-gray-500 mb-4">
                {{ __('Defines how work order completion is tracked.') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    <label class="relative flex flex-col gap-1 border rounded-lg p-3 cursor-pointer transition-colors workflow-mode-card
                        {{ ($settings['workflow_mode'] ?? 'status') === $value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                        <input type="radio" name="workflow_mode" value="{{ $value }}"
                               class="sr-only workflow-mode-radio"
                               {{ ($settings['workflow_mode'] ?? 'status') === $value ? 'checked' : '' }}>
                        <span class="font-medium text-sm text-gray-800">{{ $opt['label'] }}</span>
                        <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                    </label>
                @endforeach
            </div>
            @error('workflow_mode')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- {{ __('Authentication') }} --}}
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

        {{-- {{ __('Production Rules') }} --}}
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
                        <p class="text-xs text-gray-500">Allow operators to record more units than the planned quantity.</p>
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
                        <p class="text-xs text-gray-500">Require production steps to be completed in defined order.</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-touch btn-primary">{{ __('Save') }}</button>
        </div>
    </form>

    {{-- {{ __('Sample Data') }} --}}
    <div class="card mt-6 border-amber-200 bg-amber-50">
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

<script>
function initRadioHighlight(name) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                r.closest('label').classList.remove('border-blue-500', 'bg-blue-50');
                r.closest('label').classList.add('border-gray-200');
            });
            if (radio.checked) {
                radio.closest('label').classList.add('border-blue-500', 'bg-blue-50');
                radio.closest('label').classList.remove('border-gray-200');
            }
        });
    });
}
initRadioHighlight('production_period');
initRadioHighlight('workflow_mode');
</script>
@endsection
