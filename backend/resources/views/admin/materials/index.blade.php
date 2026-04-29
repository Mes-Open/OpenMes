@extends('layouts.app')

@section('title', 'Materials')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Materials', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Materials</h1>
        <a href="{{ route('admin.materials.create') }}" class="btn-touch btn-primary">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Material
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('admin.materials.index') }}" class="card mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Code, name or external code..." class="input-field">
            </div>
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="material_type_id" class="input-field">
                    <option value="">All types</option>
                    @foreach($materialTypes as $type)
                        <option value="{{ $type->id }}" {{ request('material_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-touch btn-secondary">Filter</button>
            @if(request()->hasAny(['search', 'material_type_id']))
                <a href="{{ route('admin.materials.index') }}" class="btn-touch btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    @if($materials->count() > 0)
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">External</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BOM</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
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
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                @else
                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.materials.edit', $material) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.materials.toggle-active', $material) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm {{ $material->is_active ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800' }}">
                                            {{ $material->is_active ? 'Deactivate' : 'Activate' }}
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
            <p class="text-gray-500 text-lg mb-4">No materials found.</p>
            <a href="{{ route('admin.materials.create') }}" class="btn-touch btn-primary">Add First Material</a>
        </div>
    @endif
</div>
@endsection
