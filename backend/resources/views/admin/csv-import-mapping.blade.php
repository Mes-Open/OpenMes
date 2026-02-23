@extends('layouts.app')

@section('title', 'Map Columns')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'CSV Import', 'url' => route('admin.csv-import')],
    ['label' => 'Map Columns', 'url' => null],
]" />

@php
    $prevMapping    = session('prev_mapping', $existingMapping?->mapping_config['column_mappings'] ?? []);
    $requiredFields = ['order_no', 'quantity'];
@endphp

<div class="max-w-7xl mx-auto">

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Map Columns</h1>
            <p class="text-gray-600 mt-1">
                Assign each CSV column to a system field or a custom key.
                <span class="font-medium text-blue-700">{{ $totalRows }} rows</span> to import ·
                Strategy: <span class="font-medium">{{ str_replace('_', ' ', $importStrategy) }}</span>
            </p>
        </div>
        <a href="{{ route('admin.csv-import') }}" class="btn-touch btn-secondary text-sm">
            ← Back
        </a>
    </div>

    {{-- Server-side mapping validation error --}}
    @if(session('mapping_error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
            </svg>
            <p class="text-sm text-red-700">{{ session('mapping_error') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.csv-import.process') }}" id="mapping-form">
        @csrf
        <input type="hidden" name="file_path" value="{{ $path }}">
        <input type="hidden" name="import_strategy" value="{{ $importStrategy }}">
        <input type="hidden" name="target_line_id" value="{{ $targetLineId ?? '' }}">
        <input type="hidden" name="import_week" value="{{ $importWeek ?? '' }}">
        <input type="hidden" name="import_month" value="{{ $importMonth ?? '' }}">
        <input type="hidden" name="production_year" value="{{ $productionYear ?? now()->year }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Column Mapping --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Column Mapping</h2>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">Quick-fill:</span>
                            <button type="button" onclick="autoMap()"
                                    class="text-xs text-blue-600 hover:text-blue-800 underline">
                                Auto-detect
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" onclick="clearAll()"
                                    class="text-xs text-red-500 hover:text-red-700 underline">
                                Clear all
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3" id="mapping-rows">
                        @foreach($headers as $h)
                            @php
                                $raw       = $prevMapping[$h] ?? '_ignore';
                                $isCustom  = str_starts_with($raw, 'custom:');
                                $customKey = $isCustom ? substr($raw, 7) : '';
                                $selValue  = $isCustom ? '__custom__' : $raw;
                            @endphp
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg" data-header="{{ $h }}">

                                {{-- CSV column name --}}
                                <div class="flex-shrink-0 w-40">
                                    <p class="text-sm font-mono font-medium text-gray-800 truncate" title="{{ $h }}">{{ $h }}</p>
                                    <p class="text-xs text-gray-400">CSV column</p>
                                </div>

                                {{-- Arrow --}}
                                <div class="flex-shrink-0 pt-2 text-gray-400">→</div>

                                {{-- Target field selector --}}
                                <div class="flex-1 min-w-0">
                                    <select
                                        name="mapping[{{ $h }}]"
                                        class="form-input w-full text-sm mapping-select"
                                        onchange="onSelectChange(this)"
                                    >
                                        <option value="_ignore" {{ $selValue === '_ignore' ? 'selected' : '' }}>— Ignore this column —</option>
                                        <optgroup label="System Fields">
                                            @foreach($systemFields as $key => $label)
                                                <option value="{{ $key }}" {{ $selValue === $key ? 'selected' : '' }}>
                                                    {{ $label }}@if(in_array($key, $requiredFields)) (required)@endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Custom Field">
                                            <option value="__custom__" {{ $selValue === '__custom__' ? 'selected' : '' }}>Custom key…</option>
                                        </optgroup>
                                    </select>

                                    {{-- Custom key input --}}
                                    <div class="custom-key-area mt-2" style="{{ $selValue === '__custom__' ? '' : 'display:none' }}">
                                        <input
                                            type="text"
                                            class="form-input w-full text-sm custom-key-input"
                                            placeholder="e.g. batch_code, color, weight_kg"
                                            value="{{ $customKey }}"
                                        >
                                        <p class="text-xs text-gray-400 mt-1">Stored as <code class="text-purple-700">custom:your_key</code></p>
                                    </div>

                                    {{-- Required badge (shown for required system fields) --}}
                                    @if(in_array($selValue, $requiredFields))
                                        <div class="mt-1">
                                            <span class="text-xs text-red-600 font-medium">required field</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Sample value --}}
                                <div class="flex-shrink-0 w-32 hidden md:block">
                                    <p class="text-xs text-gray-400 mb-1">Sample</p>
                                    <p class="text-xs text-gray-600 font-mono truncate" title="{{ $previewRows[0][$h] ?? '' }}">
                                        {{ $previewRows[0][$h] ?? '—' }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Data Preview --}}
                <div class="card overflow-hidden">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">
                        Data Preview
                        <span class="text-sm font-normal text-gray-500">(first {{ count($previewRows) }} rows)</span>
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-gray-100">
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-left font-mono text-gray-700 whitespace-nowrap">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($previewRows as $row)
                                    <tr class="hover:bg-gray-50">
                                        @foreach($headers as $h)
                                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap max-w-[140px] truncate" title="{{ $row[$h] ?? '' }}">
                                                {{ $row[$h] ?? '' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-4">

                {{-- Load Mapping --}}
                @if($savedMappings->isNotEmpty())
                <div class="card">
                    <h3 class="text-base font-bold text-gray-800 mb-3">Load Saved Profile</h3>
                    <div class="space-y-2">
                        @foreach($savedMappings as $m)
                            <button
                                type="button"
                                onclick="loadProfile({{ json_encode($m->mapping_config['column_mappings'] ?? []) }})"
                                class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 hover:border-blue-400 hover:bg-blue-50 transition-colors"
                            >
                                <p class="text-sm font-medium text-gray-800">{{ $m->name }}{{ $m->is_default ? ' ✓' : '' }}</p>
                                @php $cols = count($m->mapping_config['column_mappings'] ?? []); @endphp
                                <p class="text-xs text-gray-500">{{ $cols }} column{{ $cols !== 1 ? 's' : '' }} mapped</p>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Save Mapping --}}
                <div class="card">
                    <h3 class="text-base font-bold text-gray-800 mb-3">Save Mapping Profile</h3>
                    <div x-data="{ saveMapping: false }">
                        <label class="flex items-center gap-2 cursor-pointer mb-3">
                            <input type="checkbox" x-model="saveMapping" class="rounded border-gray-300">
                            <span class="text-sm text-gray-700">Save this mapping for later</span>
                        </label>
                        <div x-show="saveMapping" x-cloak>
                            <input
                                type="text"
                                name="save_mapping_name"
                                class="form-input w-full text-sm"
                                placeholder="Profile name (e.g. ERP Export)"
                                maxlength="100"
                            >
                        </div>
                    </div>
                </div>

                {{-- Import summary --}}
                <div class="card">
                    <h3 class="text-base font-bold text-gray-800 mb-3">Import Summary</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total rows:</span>
                            <span class="font-medium">{{ $totalRows }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Strategy:</span>
                            <span class="font-medium capitalize">{{ str_replace('_', ' ', $importStrategy) }}</span>
                        </div>
                        @if(!empty($targetLineId))
                            @php $tLine = \App\Models\Line::find($targetLineId); @endphp
                            <div class="flex justify-between">
                                <span class="text-gray-600">Target line:</span>
                                <span class="font-medium text-blue-700">{{ $tLine?->name ?? '—' }}</span>
                            </div>
                        @endif
                        @if(!empty($importWeek))
                            <div class="flex justify-between">
                                <span class="text-gray-600">Week:</span>
                                <span class="font-medium">W{{ $importWeek }} / {{ $productionYear ?? now()->year }}</span>
                            </div>
                        @endif
                        @if(!empty($importMonth))
                            @php $monthName = \Carbon\Carbon::create(null, $importMonth)->format('F'); @endphp
                            <div class="flex justify-between">
                                <span class="text-gray-600">Month:</span>
                                <span class="font-medium">{{ $monthName }} {{ $productionYear ?? now()->year }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">Columns:</span>
                            <span class="font-medium">{{ count($headers) }}</span>
                        </div>
                        <div class="border-t pt-2 flex justify-between">
                            <span class="text-gray-600">Mapped:</span>
                            <span class="font-medium" id="mapped-count">0</span>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-touch btn-primary w-full">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Run Import ({{ $totalRows }} rows)
                </button>
            </div>

        </div>
    </form>
</div>

<style>[x-cloak]{display:none!important}</style>

<script>
const autoDetectMap = {
    'order_no':           ['order_no', 'order no', 'orderno', 'order number', 'order_number', 'wo_no', 'work_order', 'wo no'],
    'product_name':       ['product_name', 'product name', 'productname', 'product', 'item', 'item name', 'description product'],
    'quantity':           ['quantity', 'qty', 'planned_qty', 'planned qty', 'amount'],
    'line_code':          ['line_code', 'line code', 'linecode', 'line', 'production_line'],
    'product_type_code':  ['product_type_code', 'product type code', 'product_type', 'product type', 'type code', 'type'],
    'priority':           ['priority', 'prio'],
    'due_date':           ['due_date', 'due date', 'duedate', 'deadline', 'target date', 'delivery_date'],
    'description':        ['description', 'desc', 'notes', 'comment', 'remarks'],
};

// Show/hide the custom key text input when the select changes, and refresh counter.
function onSelectChange(sel) {
    const row = sel.closest('[data-header]');
    const customArea = row.querySelector('.custom-key-area');
    if (customArea) {
        customArea.style.display = sel.value === '__custom__' ? '' : 'none';
    }
    updateMappedCount();
}

// Update the "Mapped: N" sidebar counter.
function updateMappedCount() {
    const count = Array.from(document.querySelectorAll('.mapping-select'))
        .filter(s => s.value && s.value !== '_ignore').length;
    const el = document.getElementById('mapped-count');
    if (el) el.textContent = count;
}

// Set a select to a given value and sync the UI.
function applyMapping(row, value, customKey) {
    const sel = row.querySelector('.mapping-select');
    if (!sel) return;
    if (value && value.startsWith('custom:')) {
        sel.value = '__custom__';
        const txt = row.querySelector('.custom-key-input');
        if (txt) txt.value = value.slice(7);
    } else {
        sel.value = value || '_ignore';
        const txt = row.querySelector('.custom-key-input');
        if (txt) txt.value = '';
    }
    onSelectChange(sel);
}

// Auto-detect: match header name against known aliases.
function autoMap() {
    document.querySelectorAll('[data-header]').forEach(row => {
        const header = row.dataset.header;
        const norm   = header.toLowerCase().trim();
        for (const [field, aliases] of Object.entries(autoDetectMap)) {
            if (aliases.includes(norm)) {
                applyMapping(row, field, '');
                return;
            }
        }
    });
}

// Reset all selects to "Ignore".
function clearAll() {
    document.querySelectorAll('[data-header]').forEach(row => {
        applyMapping(row, '_ignore', '');
    });
}

// Load a saved mapping profile (object: { header: value }).
function loadProfile(profileMappings) {
    document.querySelectorAll('[data-header]').forEach(row => {
        const header = row.dataset.header;
        applyMapping(row, profileMappings[header] || '_ignore', '');
    });
}

// On submit: convert any __custom__ selects into the expected "custom:key" value,
// then let the form submit natively. Server validates required fields.
document.getElementById('mapping-form').addEventListener('submit', function (event) {
    const form = event.target;

    // Remove previously injected hidden inputs
    form.querySelectorAll('input[data-custom-field]').forEach(el => el.remove());

    Array.from(form.querySelectorAll('.mapping-select')).forEach(sel => {
        if (sel.value !== '__custom__') return;

        const row = sel.closest('[data-header]');
        const txt = row ? row.querySelector('.custom-key-input') : null;
        const key = (txt ? txt.value : '').trim();

        // Inject a hidden input with the resolved "custom:key" value
        const hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = sel.name;
        hidden.value = key ? `custom:${key}` : '_ignore';
        hidden.dataset.customField = '1';
        form.appendChild(hidden);

        // Disable the original select (and text) so they are not double-submitted
        sel.disabled = true;
        if (txt) txt.disabled = true;
    });
    // Form submits naturally; the server validates that order_no and quantity are mapped.
});

// Initialise counter on load.
document.addEventListener('DOMContentLoaded', updateMappedCount);
</script>
@endsection
