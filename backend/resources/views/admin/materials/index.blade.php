@extends('layouts.app')

@section('title', __('Materials'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Materials'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Materials') }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.materials.import') }}" class="btn-touch btn-secondary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                {{ __('Import') }}
            </a>
            <a href="{{ route('admin.materials.create') }}" class="btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ __('Add Material') }}
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.materials.index') }}" class="card mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Code, name or external code...') }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                <select name="material_type_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                    <option value="">{{ __('All types') }}</option>
                    @foreach($materialTypes as $type)
                        <option value="{{ $type->id }}" {{ request('material_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-touch btn-secondary">{{ __('Filter') }}</button>
            @if(request()->hasAny(['search', 'material_type_id']))
                <a href="{{ route('admin.materials.index') }}" class="btn-touch btn-ghost">{{ __('Clear') }}</a>
            @endif
        </div>
    </form>

    @if($materials->count() > 0)
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Code') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Unit') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tracking') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Stock') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('External') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('BOM') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($materials as $material)
                        <tr class="{{ !$material->is_active ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3 font-mono text-sm">{{ $material->code }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $material->name }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    @if($material->materialType->code === 'raw_material') bg-amber-100 text-amber-800
                                    @elseif($material->materialType->code === 'semi_finished') bg-blue-100 text-blue-800
                                    @elseif($material->materialType->code === 'packaging') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $material->materialType->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $material->unit_of_measure }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($material->tracking_type) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">
                                @if($material->stock_quantity > 0)
                                    <span class="{{ $material->min_stock_level && $material->stock_quantity <= $material->min_stock_level ? 'text-red-600 font-bold' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ number_format($material->stock_quantity, $material->stock_quantity == intval($material->stock_quantity) ? 0 : 2, '.', ' ') }}
                                    </span>
                                    <span class="text-gray-400 text-xs">{{ $material->unit_of_measure }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if($material->external_code)
                                    <span class="font-mono text-xs text-gray-500" title="{{ $material->external_system }}">{{ $material->external_code }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-center">{{ $material->bom_items_count }}</td>
                            <td class="px-4 py-3">
                                @if($material->is_active)
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.materials.edit', $material) }}" class="text-blue-600 hover:text-blue-800 text-sm">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('admin.materials.toggle-active', $material) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm {{ $material->is_active ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' }}">
                                            {{ $material->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card text-center py-12">
            <p class="text-gray-500 text-lg mb-4">{{ __('No materials found.') }}</p>
            <a href="{{ route('admin.materials.create') }}" class="btn-touch btn-primary">{{ __('Add First Material') }}</a>
        </div>
    @endif
</div>
@endsection
