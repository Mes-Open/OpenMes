@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="max-w-2xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">System Settings</h1>
            <p class="text-gray-500 text-sm mt-0.5">Global application configuration (Admin only)</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update-system') }}" class="space-y-6">
        @csrf

        {{-- Production Planning --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Production Planning</h2>

            <div class="mb-4">
                <label class="form-label">Production Period Split</label>
                <p class="text-xs text-gray-500 mb-2">
                    Determines how work orders are grouped for planning. Affects the import form (week / month number field) and work order list view.
                </p>
                <div class="grid grid-cols-3 gap-3">
                    @foreach(['none' => ['label' => 'None', 'desc' => 'No period grouping'], 'weekly' => ['label' => 'Weekly', 'desc' => 'Group by ISO week (1–53)'], 'monthly' => ['label' => 'Monthly', 'desc' => 'Group by month (1–12)']] as $value => $opt)
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

        {{-- Workflow Mode --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-1">Workflow Mode</h2>
            <p class="text-xs text-gray-500 mb-4">
                Defines how work order completion is tracked. In <strong>Board Status</strong> mode the operator must enter a produced quantity when moving a work order to a "Done" board status.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach([
                    'status' => [
                        'label' => 'Status',
                        'desc'  => 'Work order status is changed manually through the work order actions. Board statuses are purely visual labels.',
                    ],
                    'board_status' => [
                        'label' => 'Board Status',
                        'desc'  => 'Moving a work order to a "Done" board status automatically closes the work order and records produced quantity.',
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

        {{-- Production Rules --}}
        <div class="card">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Production Rules</h2>

            <div class="space-y-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input type="hidden" name="allow_overproduction" value="0">
                        <input type="checkbox" name="allow_overproduction" value="1"
                               class="rounded border-gray-300 text-blue-600"
                               {{ ($settings['allow_overproduction'] ?? false) ? 'checked' : '' }}>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">Allow overproduction</p>
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
                        <p class="text-sm font-medium text-gray-800">Force sequential steps</p>
                        <p class="text-xs text-gray-500">Require production steps to be completed in defined order.</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-touch btn-primary">Save Settings</button>
        </div>
    </form>
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
