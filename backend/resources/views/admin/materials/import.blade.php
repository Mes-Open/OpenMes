@extends('layouts.app')

@section('title', __('Import Materials'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Materials'), 'url' => route('admin.materials.index')],
    ['label' => __('Import'), 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('Import Materials') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Import materials from CSV, XLS or XLSX file (e.g. Subiekt GT export)') }}</p>
        </div>
        <a href="{{ route('admin.materials.index') }}" class="btn-touch btn-secondary">
            {{ __('Back to Materials') }}
        </a>
    </div>

    {{-- Import result banner --}}
    @if(session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="card mb-6 border-l-4 {{ empty($result['errors']) ? 'border-green-500' : 'border-yellow-500' }}">
            <div class="flex items-start gap-4">
                <div class="{{ empty($result['errors']) ? 'bg-green-100' : 'bg-yellow-100' }} rounded-full p-3 flex-shrink-0">
                    @if(empty($result['errors']))
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                        </svg>
                    @endif
                </div>
                <div class="flex-1">
                    <p class="font-bold text-gray-800 dark:text-gray-100 mb-1">{{ __('Import') }} {{ empty($result['errors']) ? __('Completed') : __('Completed with errors') }}</p>
                    <div class="flex gap-6 text-sm">
                        <span class="text-green-700 font-medium">{{ $result['created'] }} {{ __('created') }}</span>
                        <span class="text-blue-700 font-medium">{{ $result['updated'] }} {{ __('updated') }}</span>
                        @if($result['skipped'] > 0)
                            <span class="text-gray-600 font-medium">{{ $result['skipped'] }} {{ __('skipped') }}</span>
                        @endif
                        <span class="text-gray-600">{{ $result['total'] }} {{ __('total rows') }}</span>
                    </div>
                    @if(!empty($result['errors']))
                        <details class="mt-3">
                            <summary class="text-sm text-red-600 cursor-pointer">{{ __('Show errors') }} ({{ count($result['errors']) }})</summary>
                            <ul class="mt-2 text-xs text-red-700 space-y-1 bg-red-50 rounded p-3">
                                @foreach($result['errors'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="card mb-6 border-l-4 border-red-500">
            <p class="text-red-700 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Upload form --}}
        <div class="lg:col-span-2">
            <div class="card">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ __('Upload File') }}</h2>

                <form method="POST" action="{{ route('admin.materials.import.upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('File (CSV, XLS, XLSX)') }}</label>
                        <input type="file" name="import_file" accept=".csv,.xls,.xlsx,.txt" required
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Import Strategy') }}</label>
                        <select name="import_strategy" required
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                            <option value="update_or_create">{{ __('Create new & update existing') }}</option>
                            <option value="create_only">{{ __('Create new only (skip existing)') }}</option>
                            <option value="skip_existing">{{ __('Update existing only (skip new)') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Source System') }}
                            <span class="text-gray-400 font-normal">({{ __('optional') }})</span>
                        </label>
                        <select name="external_system"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                x-data x-on:change="$el.value === '_custom' && $refs.customSystem.focus()">
                            <option value="">-- {{ __('None') }} --</option>
                            <option value="subiekt_gt">Subiekt GT</option>
                            <option value="subiekt_nexo">Subiekt nexo</option>
                            <option value="optima">Comarch Optima</option>
                            <option value="wf_mag">WF-Mag</option>
                            <option value="enova">Enova365</option>
                            <option value="sap">SAP</option>
                            <option value="custom">{{ __('Other (custom)') }}</option>
                        </select>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn-touch btn-primary w-full sm:w-auto">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            {{ __('Upload & Map Columns') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Help sidebar --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Supported Formats') }}</h3>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        {{ __('CSV (comma or semicolon separated)') }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        {{ __('XLS (Excel 97-2003)') }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        {{ __('XLSX (Excel 2007+)') }}
                    </li>
                </ul>
            </div>

            <div class="card">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Subiekt GT Export') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    {{ __('To export materials from Subiekt GT:') }}
                </p>
                <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
                    <li>Go to Towary > Lista towarow</li>
                    <li>Select all or filter</li>
                    <li>Click Export > Excel/CSV</li>
                    <li>Include columns: Symbol, Nazwa, JM, Cena, EAN, Stan</li>
                </ol>
            </div>

            <div class="card">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3">{{ __('Matching Logic') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Existing materials are matched by:') }}
                </p>
                <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside mt-1">
                    <li>{{ __('External Code + Source System') }}</li>
                    <li>{{ __('EAN / Barcode') }}</li>
                    <li>{{ __('Internal Code') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
