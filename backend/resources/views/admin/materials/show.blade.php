@extends('layouts.app')

@section('title', __('Material') . ' - ' . $material->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Materials'), 'url' => route('admin.materials.index')],
    ['label' => $material->name, 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-gray-800">{{ $material->name }}</h1>
                @if($material->is_active)
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">{{ __('Active') }}</span>
                @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">{{ __('Inactive') }}</span>
                @endif
            </div>
            <p class="text-sm text-gray-600 mt-1 font-mono">{{ $material->code }}</p>
        </div>
        <a href="{{ route('admin.materials.edit', $material) }}" class="btn-touch btn-secondary">{{ __('Edit') }}</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">{{ __('Details') }}</h3>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">{{ __('Type') }}</dt>
                    <dd class="text-sm font-medium">{{ $material->materialType->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">{{ __('Unit') }}</dt>
                    <dd class="text-sm font-medium">{{ $material->unit_of_measure }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">{{ __('Tracking') }}</dt>
                    <dd class="text-sm font-medium">{{ ucfirst($material->tracking_type) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">{{ __('Default Scrap %') }}</dt>
                    <dd class="text-sm font-medium">{{ $material->default_scrap_percentage }}%</dd>
                </div>
            </dl>
        </div>

        <div class="card">
            <h3 class="text-lg font-semibold mb-4">{{ __('External System') }}</h3>
            @if($material->external_code)
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ __('System') }}</dt>
                        <dd class="text-sm font-medium">{{ $material->external_system }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500">{{ __('External Code') }}</dt>
                        <dd class="text-sm font-mono">{{ $material->external_code }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-500">{{ __('No external system linked.') }}</p>
            @endif

            @if($material->sources->isNotEmpty())
                <h4 class="text-sm font-semibold mt-4 mb-2">{{ __('Additional Sources') }}</h4>
                @foreach($material->sources as $source)
                    <div class="p-2 bg-gray-50 rounded mb-2 text-sm">
                        <span class="font-medium">{{ $source->integrationConfig?->system_name ?? __('Unknown') }}</span>:
                        <span class="font-mono">{{ $source->external_code }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    @if($material->bomItems->isNotEmpty())
        <div class="card">
            <h3 class="text-lg font-semibold mb-4">{{ __('Used in BOM (:count templates)', ['count' => $material->bomItems->count()]) }}</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Template') }}</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Qty/Unit') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Scrap %') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($material->bomItems as $item)
                        <tr>
                            <td class="px-4 py-2 text-sm">{{ $item->processTemplate->name }}</td>
                            <td class="px-4 py-2 text-sm">{{ $item->processTemplate->productType->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ $item->quantity_per_unit }}</td>
                            <td class="px-4 py-2 text-sm text-right">{{ $item->scrap_percentage }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
