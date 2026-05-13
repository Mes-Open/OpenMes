@extends('layouts.app')

@section('title', __('Map Material Columns'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Materials'), 'url' => route('admin.materials.index')],
    ['label' => __('Import'), 'url' => route('admin.materials.import')],
    ['label' => __('Map Columns'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('Map Columns') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                {{ __('Assign each column to a material field.') }}
                <span class="font-medium text-blue-700">{{ $totalRows }} {{ __('rows') }}</span> {{ __('to import') }}
                @if($externalSystem)
                    · {{ __('Source') }}: <span class="font-medium">{{ $externalSystem }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.materials.import') }}" class="btn-touch btn-secondary text-sm">{{ __('Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.materials.import.process') }}" id="mapping-form">
        @csrf
        <input type="hidden" name="file_path" value="{{ $path }}">
        <input type="hidden" name="import_strategy" value="{{ $importStrategy }}">
        <input type="hidden" name="external_system" value="{{ $externalSystem }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Column Mapping --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ __('Column Mapping') }}</h2>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="autoMap()"
                                    class="text-xs text-blue-600 hover:text-blue-800 underline">{{ __('Auto-detect') }}</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" onclick="clearAll()"
                                    class="text-xs text-red-500 hover:text-red-700 underline">{{ __('Clear all') }}</button>
                        </div>
                    </div>

                    <div class="space-y-3" id="mapping-rows">
                        @foreach($headers as $h)
                            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg">
                                <div class="w-1/3 min-w-0">
                                    <span class="text-sm font-mono font-medium text-gray-800 dark:text-gray-200 truncate block" title="{{ $h }}">{{ $h }}</span>
                                    @if(isset($previewRows[0][$h]))
                                        <span class="text-xs text-gray-400 truncate block">e.g. {{ Str::limit($previewRows[0][$h], 40) }}</span>
                                    @endif
                                </div>
                                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                                <div class="flex-1">
                                    <select name="mapping[{{ $h }}]"
                                            class="mapping-select w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                            data-header="{{ strtolower($h) }}">
                                        @foreach($systemFields as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Data preview --}}
                <div class="card">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Data Preview (first :count rows)', ['count' => count($previewRows)]) }}</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr>
                                    @foreach($headers as $h)
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase bg-gray-50 dark:bg-slate-700 whitespace-nowrap">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                @foreach($previewRows as $row)
                                    <tr>
                                        @foreach($headers as $h)
                                            <td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-300 max-w-[200px] truncate">{{ $row[$h] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="card">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Required Fields') }}</h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li><span class="text-red-500">*</span> <strong>{{ __('Name') }}</strong> — {{ __('material name') }}</li>
                        <li><span class="text-red-500">*</span> <strong>{{ __('Code') }}</strong> {{ __('or') }} <strong>{{ __('External Code') }}</strong> — {{ __('for identification') }}</li>
                    </ul>
                </div>

                <div class="card">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Strategy') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        @if($importStrategy === 'update_or_create')
                            <strong>{{ __('Create & Update') }}</strong> — {{ __('new materials will be created, existing ones updated with new data.') }}
                        @elseif($importStrategy === 'create_only')
                            <strong>{{ __('Create Only') }}</strong> — {{ __('existing materials will be skipped.') }}
                        @else
                            <strong>{{ __('Update Only') }}</strong> — {{ __('only existing materials will be updated, new ones skipped.') }}
                        @endif
                    </p>
                </div>

                <div class="sticky top-4">
                    <button type="submit" class="btn-touch btn-primary w-full text-center">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        {{ __('Import :count Materials', ['count' => $totalRows]) }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const autoMapRules = {
        'symbol': 'external_code',
        'kod': 'external_code',
        'code': 'code',
        'indeks': 'external_code',
        'nazwa': 'name',
        'name': 'name',
        'opis': 'description',
        'description': 'description',
        'jm': 'unit_of_measure',
        'j.m.': 'unit_of_measure',
        'unit': 'unit_of_measure',
        'jednostka': 'unit_of_measure',
        'ean': 'ean',
        'kod kreskowy': 'ean',
        'barcode': 'ean',
        'stan': 'stock_quantity',
        'ilosc': 'stock_quantity',
        'ilość': 'stock_quantity',
        'stock': 'stock_quantity',
        'quantity': 'stock_quantity',
        'stan magazynowy': 'stock_quantity',
        'cena': 'unit_price',
        'cena netto': 'unit_price',
        'price': 'unit_price',
        'dostawca': 'supplier_name',
        'supplier': 'supplier_name',
        'kontrahent': 'supplier_name',
        'typ': 'material_type',
        'type': 'material_type',
        'kategoria': 'material_type',
        'category': 'material_type',
        'grupa': 'material_type',
        'waluta': 'price_currency',
        'currency': 'price_currency',
        'min': 'min_stock_level',
        'minimum': 'min_stock_level',
        'min stock': 'min_stock_level',
    };

    function autoMap() {
        document.querySelectorAll('.mapping-select').forEach(sel => {
            const header = sel.dataset.header.toLowerCase().trim();
            for (const [pattern, target] of Object.entries(autoMapRules)) {
                if (header === pattern || header.includes(pattern)) {
                    // Check if option exists
                    const opt = sel.querySelector(`option[value="${target}"]`);
                    if (opt) {
                        sel.value = target;
                        break;
                    }
                }
            }
        });
    }

    function clearAll() {
        document.querySelectorAll('.mapping-select').forEach(sel => {
            sel.value = '_ignore';
        });
    }

    // Auto-detect on load
    document.addEventListener('DOMContentLoaded', autoMap);
</script>
@endsection
