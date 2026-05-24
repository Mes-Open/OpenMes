@extends('layouts.app')

@section('title', __('Material Lot') . ' — ' . $lot->lot_number)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Material Lots'), 'url' => route('admin.material-lots.index')],
    ['label' => $lot->lot_number, 'url' => null],
]" />

@php
    $statusColors = [
        'received'   => 'bg-blue-100 text-blue-800',
        'quarantine' => 'bg-amber-100 text-amber-800',
        'released'   => 'bg-green-100 text-green-800',
        'consumed'   => 'bg-gray-100 text-gray-600',
        'expired'    => 'bg-red-100 text-red-800',
        'rejected'   => 'bg-red-200 text-red-900',
    ];
    $statusColor = $statusColors[$lot->status] ?? 'bg-gray-100 text-gray-700';
    $totalConsumed = $lot->consumptions->sum('quantity_consumed');
@endphp

<div class="max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 font-mono">{{ $lot->lot_number }}</h1>
            <p class="text-gray-500 text-sm mt-1">
                {{ __('Received') }} {{ $lot->received_at?->format('Y-m-d H:i') ?? '—' }}
                @if($lot->material) — <span class="font-medium">{{ $lot->material->name }}</span>@endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">{{ ucfirst($lot->status) }}</span>
            <a href="{{ route('admin.material-lots.edit', $lot) }}" class="btn-touch btn-secondary">{{ __('Edit') }}</a>
            <a href="{{ route('admin.material-lots.index') }}" class="btn-touch btn-ghost">{{ __('← Back') }}</a>
        </div>
    </div>

    {{-- Info --}}
    <div class="card mb-6">
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">{{ __('Info') }}</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Material') }}</dt>
                <dd class="mt-1 text-gray-800">
                    @if($lot->material)
                        <span class="font-medium">{{ $lot->material->name }}</span>
                        <span class="text-xs text-gray-500 block font-mono">{{ $lot->material->code }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Quantity') }}</dt>
                <dd class="mt-1 font-mono">
                    {{ rtrim(rtrim(number_format((float) $lot->quantity_available, 4, '.', ''), '0'), '.') }}
                    /
                    {{ rtrim(rtrim(number_format((float) $lot->quantity_received, 4, '.', ''), '0'), '.') }}
                    <span class="text-xs text-gray-500">{{ $lot->unit_of_measure }}</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Expiry') }}</dt>
                <dd class="mt-1">
                    @if($lot->expiry_date)
                        <span class="{{ $lot->expiry_date->isPast() ? 'text-red-600 font-semibold' : 'text-gray-800' }}">
                            {{ $lot->expiry_date->format('Y-m-d') }}
                        </span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Manufacturing date') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $lot->manufacturing_date?->format('Y-m-d') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Supplier lot') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $lot->supplier_lot_no ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Supplier reference') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $lot->supplier_reference ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Inspection') }}</dt>
                <dd class="mt-1 text-gray-800">
                    @if($lot->inspection)
                        <a href="{{ route('inspections.show', $lot->inspection) }}" class="text-blue-700 hover:underline">
                            #{{ $lot->inspection->id }} ({{ $lot->inspection->status }})
                        </a>
                    @else
                        <span class="text-gray-400">{{ __('Not linked') }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Source') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $lot->source?->external_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 uppercase">{{ __('Created by') }}</dt>
                <dd class="mt-1 text-gray-800">{{ $lot->createdBy?->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Sublots --}}
    @if($lot->sublots->isNotEmpty())
        <div class="card mb-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">
                {{ __('Sublots') }} ({{ $lot->sublots->count() }})
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2 text-left">{{ __('Sublot') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Quantity') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @foreach($lot->sublots as $sub)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $sub->sublot_number }}</td>
                                <td class="px-3 py-2 text-right font-mono">
                                    {{ rtrim(rtrim(number_format((float) $sub->quantity, 4, '.', ''), '0'), '.') }}
                                    <span class="text-xs text-gray-500">{{ $sub->unit_of_measure }}</span>
                                </td>
                                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ ucfirst($sub->status) }}</span></td>
                                <td class="px-3 py-2 text-gray-600">{{ $sub->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Genealogy --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Genealogy') }}</h2>
            <span class="text-xs text-gray-500">
                {{ __('Total consumed:') }}
                <span class="font-mono font-medium">{{ rtrim(rtrim(number_format($totalConsumed, 4, '.', ''), '0'), '.') }} {{ $lot->unit_of_measure }}</span>
            </span>
        </div>

        {{-- Forward: where this lot went --}}
        <div class="mb-6">
            <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('Forward — consumed by') }}</h3>
            @if($lot->consumptions->isEmpty())
                <p class="text-sm text-gray-500 italic">{{ __('No consumption recorded yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase">
                                <th class="px-3 py-2 text-left">{{ __('When') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('Work order') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('Batch') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('Step') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Quantity') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('By') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($lot->consumptions as $c)
                                @php
                                    $step = $c->batchStep;
                                    $batch = $step?->batch;
                                    $wo = $batch?->workOrder;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-gray-600">{{ $c->consumed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $wo?->lot_number ?? ($wo ? '#' . $wo->id : '—') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $batch?->lot_number ?? ($batch ? '#' . $batch->id : '—') }}</td>
                                    <td class="px-3 py-2">{{ $step?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">
                                        {{ rtrim(rtrim(number_format((float) $c->quantity_consumed, 4, '.', ''), '0'), '.') }}
                                        <span class="text-xs text-gray-500">{{ $lot->unit_of_measure }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">{{ $c->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Backward: what fed into this lot --}}
        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ __('Backward — sourced from') }}</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Inspection') }}</dt>
                    <dd class="mt-1">
                        @if($lot->inspection)
                            <a href="{{ route('inspections.show', $lot->inspection) }}" class="text-blue-700 hover:underline">
                                #{{ $lot->inspection->id }} — {{ ucfirst($lot->inspection->status) }}
                            </a>
                        @else
                            <span class="text-gray-400">{{ __('No inbound inspection') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Supplier reference') }}</dt>
                    <dd class="mt-1 text-gray-800">{{ $lot->supplier_reference ?? $lot->supplier_lot_no ?? '—' }}</dd>
                </div>
            </dl>
            @if(! empty($lot->extra_data['source_batch_id']))
                <p class="mt-3 text-xs text-gray-500">
                    {{ __('Upstream source batch:') }}
                    <span class="font-mono">#{{ $lot->extra_data['source_batch_id'] }}</span>
                    — {{ __('see backward genealogy API for full chain.') }}
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
