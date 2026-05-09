@extends('layouts.app')

@section('title', 'BOM - ' . $processTemplate->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Product Types', 'url' => route('admin.product-types.index')],
    ['label' => $productType->name, 'url' => route('admin.product-types.show', $productType)],
    ['label' => 'Process Templates', 'url' => route('admin.product-types.process-templates.index', $productType)],
    ['label' => $processTemplate->name, 'url' => route('admin.product-types.process-templates.show', [$productType, $processTemplate])],
    ['label' => 'Bill of Materials', 'url' => null],
]" />

<div class="max-w-7xl mx-auto" x-data="{ showAddForm: false }">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.process-templates.show', [$productType, $processTemplate]) }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Template
        </a>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Bill of Materials</h1>
                <p class="text-sm text-gray-600 mt-1">{{ $processTemplate->name }} (v{{ $processTemplate->version }}) &bull; {{ $productType->name }}</p>
            </div>
            <button @click="showAddForm = !showAddForm" class="btn-touch btn-primary">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Material
            </button>
        </div>
    </div>

    <!-- Add Material Form -->
    <div x-show="showAddForm" x-cloak class="card mb-6 border-l-4 border-blue-500">
        <h3 class="text-lg font-semibold mb-4">Add Material to BOM</h3>
        <form method="POST" action="{{ route('admin.product-types.process-templates.bom.store', [$productType, $processTemplate]) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Material <span class="text-red-500">*</span></label>
                    <select name="material_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('material_id') border-red-500 @enderror">
                        <option value="">Select material...</option>
                        @foreach($materials as $material)
                            <option value="{{ $material->id }}" {{ old('material_id') == $material->id ? 'selected' : '' }}>
                                {{ $material->code }} - {{ $material->name }} ({{ $material->materialType->name }})
                            </option>
                        @endforeach
                    </select>
                    @error('material_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity per Unit <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity_per_unit" step="0.0001" min="0.0001" required
                        value="{{ old('quantity_per_unit') }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition @error('quantity_per_unit') border-red-500 @enderror">
                    @error('quantity_per_unit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Step (optional)</label>
                    <select name="template_step_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                        <option value="">All steps / general</option>
                        @foreach($steps as $step)
                            <option value="{{ $step->id }}" {{ old('template_step_id') == $step->id ? 'selected' : '' }}>
                                #{{ $step->step_number }} - {{ $step->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scrap %</label>
                    <input type="number" name="scrap_percentage" step="0.01" min="0" max="100"
                        value="{{ old('scrap_percentage', 0) }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumed At</label>
                    <select name="consumed_at" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition">
                        <option value="start" {{ old('consumed_at') === 'start' ? 'selected' : '' }}>Start of step</option>
                        <option value="during" {{ old('consumed_at') === 'during' ? 'selected' : '' }}>During step</option>
                        <option value="end" {{ old('consumed_at') === 'end' ? 'selected' : '' }}>End of step</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-slate-700 px-3 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition" placeholder="Optional notes">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="showAddForm = false" class="btn-touch btn-secondary">Cancel</button>
                <button type="submit" class="btn-touch btn-primary">Add to BOM</button>
            </div>
        </form>
    </div>

    <!-- BOM Items Table -->
    @if($bomItems->count() > 0)
        <div class="card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Material</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Step</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty/Unit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Scrap %</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Consumed</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($bomItems as $item)
                        <tr x-data="{ editing: false }">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item->material->name }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $item->material->code }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    @if($item->material->materialType->code === 'raw_material') bg-amber-100 text-amber-800
                                    @elseif($item->material->materialType->code === 'semi_finished') bg-blue-100 text-blue-800
                                    @elseif($item->material->materialType->code === 'packaging') bg-purple-100 text-purple-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $item->material->materialType->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($item->templateStep)
                                    #{{ $item->templateStep->step_number }} {{ $item->templateStep->name }}
                                @else
                                    <span class="text-gray-400">General</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ $item->quantity_per_unit }} {{ $item->material->unit_of_measure }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $item->scrap_percentage }}%</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($item->consumed_at) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($item->material->tracking_type) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.product-types.process-templates.bom.destroy', [$productType, $processTemplate, $item]) }}" onsubmit="return confirm('Remove this material from BOM?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
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
            <p class="text-gray-500 text-lg mb-4">No materials in BOM yet.</p>
            <button @click="showAddForm = true" class="btn-touch btn-primary">Add First Material</button>
        </div>
    @endif
</div>
@endsection
