@extends('layouts.app')

@section('title', 'Map Columns')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'CSV Import', 'url' => route('admin.csv-import')],
    ['label' => 'Map Columns', 'url' => null],
]" />

<div class="max-w-7xl mx-auto"
     x-data="csvMapper({{ json_encode($headers) }}, {{ json_encode(session('prev_mapping', $existingMapping?->mapping_config['column_mappings'] ?? [])) }})">

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

    <form method="POST" action="{{ route('admin.csv-import.process') }}" id="mapping-form"
          @submit="handleSubmit($event)">
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
                            <button type="button" @click="autoMap()"
                                    class="text-xs text-blue-600 hover:text-blue-800 underline">
                                Auto-detect
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="clearAll()"
                                    class="text-xs text-red-500 hover:text-red-700 underline">
                                Clear all
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <template x-for="header in headers" :key="header">
                            <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                                {{-- CSV column name --}}
                                <div class="flex-shrink-0 w-40">
                                    <p class="text-sm font-mono font-medium text-gray-800 truncate" x-text="header" :title="header"></p>
                                    <p class="text-xs text-gray-400">CSV column</p>
                                </div>

                                {{-- Arrow --}}
                                <div class="flex-shrink-0 pt-2 text-gray-400">→</div>

                                {{-- Target field selector --}}
                                <div class="flex-1 min-w-0">
                                    <select
                                        :name="`mapping[${header}]`"
                                        class="form-input w-full text-sm"
                                        @change="setMapping(header, $event.target.value)"
                                    >
                                        <option value="_ignore"
                                                :selected="getMapping(header) === '_ignore'">— Ignore this column —</option>
                                        <optgroup label="System Fields">
                                            @foreach($systemFields as $key => $label)
                                                <option value="{{ $key }}"
                                                        :selected="getMapping(header) === '{{ $key }}'">
                                                    {{ $label }}
                                                    @if(in_array($key, ['order_no', 'quantity'])) (required) @endif
                                                </option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Custom Field">
                                            <option value="__custom__"
                                                    :selected="getMapping(header) === '__custom__'">Custom key…</option>
                                        </optgroup>
                                    </select>

                                    {{-- Custom key input --}}
                                    <div x-show="getMapping(header) === '__custom__'" x-cloak class="mt-2">
                                        <input
                                            type="text"
                                            :name="`mapping[${header}]`"
                                            class="form-input w-full text-sm"
                                            placeholder="e.g. batch_code, color, weight_kg"
                                            :value="customKeys[header] || ''"
                                            @input="customKeys[header] = $event.target.value"
                                        >
                                        <p class="text-xs text-gray-400 mt-1">Stored as <code class="text-purple-700">custom:your_key</code></p>
                                    </div>

                                    {{-- Badge --}}
                                    <div class="mt-1"
                                         x-show="getMapping(header) && getMapping(header) !== '_ignore' && getMapping(header) !== '__custom__'">
                                        <span x-show="isRequired(getMapping(header))"
                                              class="text-xs text-red-600 font-medium">required field</span>
                                    </div>
                                </div>

                                {{-- Sample value --}}
                                <div class="flex-shrink-0 w-32 hidden md:block">
                                    <p class="text-xs text-gray-400 mb-1">Sample</p>
                                    <p class="text-xs text-gray-600 font-mono truncate" x-text="getSample(header)" :title="getSample(header)"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Validation warning --}}
                    <div id="mapping-warning" x-show="!hasRequired()" x-cloak class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>Warning:</strong> <code>order_no</code> and <code>quantity</code> are required. Please map these columns before importing.
                        </p>
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
                                @click="loadProfile({{ json_encode($m->mapping_config['column_mappings'] ?? []) }})"
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
                            <span class="font-medium" x-text="countMapped()"></span>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    class="btn-touch btn-primary w-full"
                >
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
function csvMapper(headers, existingMappings) {
    // Build sample values from preview rows passed as PHP JSON
    const previewRows = @json($previewRows);
    const systemFields = @json(array_keys($systemFields));
    const requiredFields = ['order_no', 'quantity'];

    // Auto-detect: normalize header → try to match system field
    const autoDetectMap = {
        'order_no': ['order_no', 'order no', 'orderno', 'order number', 'order_number', 'wo_no', 'work_order', 'wo no'],
        'product_name': ['product_name', 'product name', 'productname', 'product', 'item', 'item name', 'description product'],
        'quantity': ['quantity', 'qty', 'planned_qty', 'planned qty', 'amount'],
        'line_code': ['line_code', 'line code', 'linecode', 'line', 'production_line'],
        'product_type_code': ['product_type_code', 'product type code', 'product_type', 'product type', 'type code', 'type'],
        'priority': ['priority', 'prio'],
        'due_date': ['due_date', 'due date', 'duedate', 'deadline', 'target date', 'delivery_date'],
        'description': ['description', 'desc', 'notes', 'comment', 'remarks'],
    };

    // Initialise mappings from existingMappings (loaded profile) or empty
    const initMappings = {};
    const initCustomKeys = {};

    headers.forEach(h => {
        const existing = existingMappings[h];
        if (existing && existing.startsWith('custom:')) {
            initMappings[h] = '__custom__';
            initCustomKeys[h] = existing.slice(7);
        } else {
            initMappings[h] = existing || '_ignore';
        }
    });

    return {
        headers,
        mappings: initMappings,
        customKeys: initCustomKeys,
        previewRows,
        mappingVersion: 0,

        setMapping(header, value) {
            this.mappings[header] = value;
            if (value !== '__custom__') {
                delete this.customKeys[header];
            }
            this.mappingVersion++;
        },

        getMapping(header) {
            void this.mappingVersion; // reactive dependency
            return this.mappings[header] || '_ignore';
        },

        getSample(header) {
            if (!previewRows.length) return '—';
            return previewRows[0][header] ?? '—';
        },

        isRequired(field) {
            return requiredFields.includes(field);
        },

        hasRequired() {
            void this.mappingVersion; // reactive dependency
            const mapped = Object.values(this.mappings);
            return requiredFields.every(f => mapped.includes(f));
        },

        countMapped() {
            void this.mappingVersion; // reactive dependency
            return Object.values(this.mappings).filter(v => v && v !== '_ignore').length;
        },

        autoMap() {
            headers.forEach(h => {
                const norm = h.toLowerCase().trim();
                for (const [field, aliases] of Object.entries(autoDetectMap)) {
                    if (aliases.includes(norm)) {
                        this.mappings[h] = field;
                        return;
                    }
                }
            });
            this.mappingVersion++;
        },

        clearAll() {
            headers.forEach(h => { this.mappings[h] = '_ignore'; });
            this.customKeys = {};
            this.mappingVersion++;
        },

        loadProfile(profileMappings) {
            headers.forEach(h => {
                const val = profileMappings[h];
                if (val && val.startsWith('custom:')) {
                    this.mappings[h] = '__custom__';
                    this.customKeys[h] = val.slice(7);
                } else {
                    this.mappings[h] = val || '_ignore';
                }
            });
            this.mappingVersion++;
        },

        handleSubmit(event) {
            if (!this.hasRequired()) {
                event.preventDefault();
                // Scroll the warning into view so the user knows what to fix
                const warning = document.querySelector('#mapping-warning');
                if (warning) warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            const form = event.target;

            // Remove old dynamic custom inputs
            form.querySelectorAll('input[data-custom-field]').forEach(el => el.remove());

            // For columns mapped to a custom key, inject a hidden input with the
            // "custom:key" value and disable the corresponding select/text inputs
            // so they are not double-submitted.
            headers.forEach(h => {
                if (this.mappings[h] === '__custom__') {
                    const key = (this.customKeys[h] || '').trim();
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `mapping[${h}]`;
                    hidden.value = key ? `custom:${key}` : '_ignore';
                    hidden.dataset.customField = '1';
                    form.appendChild(hidden);

                    const sel = form.querySelector(`select[name="mapping[${h}]"]`);
                    if (sel) sel.disabled = true;

                    const txt = form.querySelector(`input[name="mapping[${h}]"]:not([data-custom-field])`);
                    if (txt) txt.disabled = true;
                }
            });

            // Allow native form submission to proceed
        },
    };
}
</script>
@endsection
