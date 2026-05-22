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

        <div class="card border-l-4 {{ ($material->available_quantity ?? 0) < ($material->min_stock_level ?? 0) ? 'border-red-400' : 'border-blue-400' }}">
            <h3 class="text-lg font-semibold mb-4">{{ __('Stock breakdown') }}</h3>
            <dl class="space-y-2">
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">{{ __('On hand') }}</dt>
                    <dd class="font-mono">{{ number_format($material->stock_quantity, 3) }} {{ $material->unit_of_measure }}</dd>
                </div>
                <div class="flex justify-between text-sm">
                    <dt class="text-gray-500">{{ __('Reserved by active batches') }}</dt>
                    <dd class="font-mono text-amber-700">{{ number_format($material->reserved_quantity ?? 0, 3) }} {{ $material->unit_of_measure }}</dd>
                </div>
                <div class="flex justify-between text-sm pt-2 border-t border-gray-200">
                    <dt class="font-medium text-gray-700">{{ __('Available') }}</dt>
                    <dd class="font-mono font-bold {{ ($material->available_quantity ?? 0) <= 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ number_format($material->available_quantity ?? 0, 3) }} {{ $material->unit_of_measure }}
                    </dd>
                </div>
                @if($material->min_stock_level)
                    <div class="flex justify-between text-xs text-gray-400">
                        <dt>{{ __('Min stock level') }}</dt>
                        <dd class="font-mono">{{ number_format($material->min_stock_level, 3) }} {{ $material->unit_of_measure }}</dd>
                    </div>
                @endif
                @if($material->unit_price)
                    <div class="flex justify-between text-xs text-gray-400">
                        <dt>{{ __('Stock value') }}</dt>
                        <dd class="font-mono">{{ number_format($material->stock_quantity * $material->unit_price, 2) }} {{ $material->price_currency }}</dd>
                    </div>
                @endif
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

    @php
        $recentMovements = \App\Models\StockMovement::forMaterial($material->id)->limit(15)->get();
        $lots = \App\Models\MaterialLot::where('material_id', $material->id)
            ->orderByRaw('CASE WHEN status = \'available\' THEN 0 ELSE 1 END')
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->limit(20)->get();
    @endphp

    @if($lots->isNotEmpty())
        <div class="card mb-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('Lots') }} <span class="text-sm font-normal text-gray-400">({{ $lots->count() }})</span></h3>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Lot') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Supplier ref') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Received') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Available') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Expiry') }}</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($lots as $lot)
                        @php
                            $badge = match($lot->status) {
                                'available' => 'bg-green-100 text-green-800',
                                'quarantined' => 'bg-red-100 text-red-800',
                                'expired' => 'bg-amber-100 text-amber-800',
                                default => 'bg-gray-100 text-gray-600',
                            };
                            $expiringSoon = $lot->expiry_date && $lot->expiry_date->diffInDays(now(), false) >= -30 && $lot->expiry_date->isFuture();
                        @endphp
                        <tr class="{{ $lot->isExpired() ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-2 font-mono">{{ $lot->lot_number }}</td>
                            <td class="px-3 py-2 text-gray-500 font-mono text-xs">{{ $lot->supplier_lot_ref ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($lot->received_qty, 3) }}</td>
                            <td class="px-3 py-2 text-right font-mono {{ $lot->available_qty <= 0 ? 'text-gray-400' : 'font-bold' }}">
                                {{ number_format($lot->available_qty, 3) }}
                            </td>
                            <td class="px-3 py-2 text-xs {{ $expiringSoon ? 'text-amber-700 font-semibold' : 'text-gray-500' }}">
                                {{ $lot->expiry_date?->format('Y-m-d') ?? '—' }}
                                @if($expiringSoon)
                                    <span class="ml-1">⏰</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucfirst($lot->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($recentMovements->isNotEmpty())
        <div class="card mb-6">
            <h3 class="text-lg font-semibold mb-4">{{ __('Recent stock movements') }}</h3>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('When') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Type') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Delta') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Balance') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Source') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Reason') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($recentMovements as $mv)
                        @php
                            $typeColor = match($mv->movement_type) {
                                'receipt' => 'text-green-700',
                                'return' => 'text-blue-700',
                                'allocation' => 'text-amber-700',
                                'consume' => 'text-gray-600',
                                'scrap' => 'text-red-700',
                                'adjustment' => 'text-purple-700',
                                default => 'text-gray-700',
                            };
                        @endphp
                        <tr>
                            <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $mv->performed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-3 py-2"><span class="font-medium {{ $typeColor }}">{{ $mv->movement_type }}</span></td>
                            <td class="px-3 py-2 text-right font-mono {{ $mv->quantity > 0 ? 'text-green-700' : ($mv->quantity < 0 ? 'text-red-700' : 'text-gray-500') }}">
                                {{ $mv->quantity > 0 ? '+' : '' }}{{ number_format($mv->quantity, 3) }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($mv->balance_after, 3) }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">
                                @if($mv->source_type)
                                    {{ $mv->source_type }} #{{ $mv->source_id }}
                                @else —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600 truncate max-w-xs" title="{{ $mv->reason }}">
                                {{ Str::limit($mv->reason ?? '', 60) }}
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $mv->performedBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
