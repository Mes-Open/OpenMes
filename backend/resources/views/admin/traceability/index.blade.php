@extends('layouts.app')

@section('title', __('Traceability'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Traceability'), 'url' => null],
]" />

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Traceability') }}</h1>
        <p class="text-gray-600 mt-2">{{ __('Trace a finished LOT, material lot, supplier LOT or serial number through its full genealogy.') }}</p>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.traceability.index') }}" class="card mb-6">
        <label class="form-label">{{ __('Search') }}</label>
        <div class="flex gap-3">
            <input type="text" name="q" value="{{ $term }}" autofocus
                   placeholder="{{ __('Finished LOT, material lot, supplier LOT or serial number…') }}"
                   class="form-input flex-1">
            <button type="submit" class="btn-touch btn-primary">{{ __('Trace') }}</button>
        </div>
    </form>

    @if($term !== '' && !$result)
        <div class="card text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <p class="mt-3 text-gray-500">{{ __('No finished LOT, material lot or serial number matches') }} <strong>{{ $term }}</strong>.</p>
        </div>
    @endif

    {{-- ════════════ FINISHED BATCH GENEALOGY (backward) ════════════ --}}
    @if($result && $result['type'] === 'batch')
        @php $b = $result['data']['batch']; @endphp
        <div class="card mb-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <span class="text-xs font-semibold uppercase text-gray-400">{{ __('Finished LOT') }}</span>
                    <h2 class="text-2xl font-bold text-gray-800 font-mono">{{ $b->lot_number }}</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ __('Work Order') }}: <span class="font-medium">{{ $b->workOrder?->order_no ?? '—' }}</span>
                        &middot; {{ __('Product') }}: <span class="font-medium">{{ $b->workOrder?->productType?->name ?? '—' }}</span>
                        &middot; {{ __('Batch') }} #{{ $b->batch_number }}
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ __('Backward trace') }}</span>
            </div>
        </div>

        @php $distinct = $result['data']['distinct_input_lots']; @endphp
        <div class="card mb-4">
            <h3 class="text-lg font-bold text-gray-800 mb-3">{{ __('Ingredient lots') }} ({{ $distinct->count() }})</h3>
            @if($distinct->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No material lots were recorded as consumed for this batch.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead><tr class="text-left text-xs uppercase text-gray-500">
                            <th class="px-3 py-2">{{ __('Material') }}</th>
                            <th class="px-3 py-2">{{ __('LOT') }}</th>
                            <th class="px-3 py-2">{{ __('Supplier LOT') }}</th>
                            <th class="px-3 py-2">{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($distinct as $lot)
                            <tr>
                                <td class="px-3 py-2">{{ $lot->material?->name ?? '—' }} <span class="text-xs text-gray-400 font-mono">{{ $lot->material?->code }}</span></td>
                                <td class="px-3 py-2 font-mono">{{ $lot->lot_number }}</td>
                                <td class="px-3 py-2 font-mono text-gray-600">{{ $lot->supplier_lot_no ?? '—' }}</td>
                                <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ $lot->status }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Per-step consumption timeline --}}
        <div class="card">
            <h3 class="text-lg font-bold text-gray-800 mb-3">{{ __('Process history') }}</h3>
            <div class="space-y-3">
                @foreach($b->steps as $step)
                    @php $stepConsumptions = $result['data']['consumptions_by_step'][$step->id] ?? collect(); @endphp
                    <div class="border-l-2 {{ $step->status === 'DONE' ? 'border-green-400' : 'border-gray-200' }} pl-4 py-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-800">{{ __('Step') }} {{ $step->step_number }}: {{ $step->name }}</span>
                            @if($step->workstation)<span class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-700">{{ $step->workstation->name }}</span>@endif
                            @if($step->completedBy)<span class="text-xs text-gray-500">{{ __('by') }} {{ $step->completedBy->name }}</span>@endif
                            @if($step->completed_at)<span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($step->completed_at)->format('Y-m-d H:i') }}</span>@endif
                        </div>
                        @if($stepConsumptions->isNotEmpty())
                            <ul class="mt-1 ml-1 text-sm text-gray-600 space-y-0.5">
                                @foreach($stepConsumptions as $c)
                                    <li class="flex items-center gap-2">
                                        <span class="w-1 h-1 rounded-full bg-gray-400"></span>
                                        <span class="font-mono">{{ $c->materialLot?->lot_number }}</span>
                                        <span class="text-gray-400">{{ $c->materialLot?->material?->name }}</span>
                                        <span class="text-gray-500">— {{ number_format((float) $c->quantity_consumed, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($b->outputLots->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ __('Output lots') }}</h4>
                    @foreach($b->outputLots as $out)
                        <a href="{{ route('admin.traceability.index', ['q' => $out->lot_number]) }}" class="inline-block mr-2 mb-2 px-2 py-1 rounded bg-purple-50 text-purple-700 text-xs font-mono hover:bg-purple-100">{{ $out->lot_number }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ════════════ MATERIAL LOT (forward + backward) ════════════ --}}
    @if($result && $result['type'] === 'material_lot')
        @php $fwd = $result['forward']; $bwd = $result['backward']; @endphp
        <div class="card mb-4">
            <span class="text-xs font-semibold uppercase text-gray-400">{{ __('Material lot') }}</span>
            <h2 class="text-2xl font-bold text-gray-800 font-mono">{{ $fwd['lot']['lot_number'] }}</h2>
            @if($bwd['supplier_lot_no'])
                <p class="text-sm text-gray-600 mt-1">{{ __('Supplier LOT') }}: <span class="font-mono">{{ $bwd['supplier_lot_no'] }}</span></p>
            @endif
        </div>

        {{-- Forward: impact analysis --}}
        <div class="card mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-bold text-gray-800">{{ __('Forward trace') }} — {{ __('where did this lot go?') }}</h3>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ $fwd['work_orders']->count() }} {{ __('work orders') }}</span>
            </div>
            @if($fwd['work_orders']->isEmpty())
                <p class="text-sm text-gray-500">{{ __('This lot has not been consumed yet.') }}</p>
            @else
                <ul class="space-y-2">
                    @foreach($fwd['work_orders'] as $wo)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="font-mono font-semibold text-gray-800">{{ $wo->order_no }}</span>
                            <span class="text-gray-500">{{ $wo->productType?->name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $wo->status }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-xs text-gray-400 mt-3">{{ __('Total consumed') }}: {{ number_format($fwd['total_consumed'], 2) }}</p>
            @endif
        </div>

        {{-- Backward: ingredients --}}
        <div class="card">
            <h3 class="text-lg font-bold text-gray-800 mb-3">{{ __('Backward trace') }} — {{ __('what fed into this lot?') }}</h3>
            @if($bwd['source_batch_id'])
                <p class="text-sm text-gray-600 mb-2">{{ __('Produced by batch') }} #{{ $bwd['source_batch']['batch_number'] ?? $bwd['source_batch_id'] }}
                    @if(($bwd['source_batch']['lot_number'] ?? null))
                        (<a href="{{ route('admin.traceability.index', ['q' => $bwd['source_batch']['lot_number']]) }}" class="text-blue-600 hover:underline font-mono">{{ $bwd['source_batch']['lot_number'] }}</a>)
                    @endif
                </p>
                @include('admin.traceability._ingredient-tree', ['node' => $bwd])
            @else
                <div class="text-sm text-gray-600">
                    <p>{{ __('Inbound raw lot (terminal).') }}</p>
                    @if($bwd['supplier_reference'])<p class="mt-1">{{ __('Supplier reference') }}: <span class="font-mono">{{ $bwd['supplier_reference'] }}</span></p>@endif
                    @if($bwd['inspection_id'])<p class="mt-1">{{ __('Inbound inspection') }} #{{ $bwd['inspection_id'] }}</p>@endif
                </div>
            @endif
        </div>
    @endif

    {{-- ════════════ SERIAL UNIT (per-unit history) ════════════ --}}
    @if($result && $result['type'] === 'serial')
        @php $u = $result['data']; @endphp
        <div class="card mb-4">
            <span class="text-xs font-semibold uppercase text-gray-400">{{ __('Serial unit') }}</span>
            <h2 class="text-2xl font-bold text-gray-800 font-mono">{{ $u->serial_no }}</h2>
            <p class="text-sm text-gray-600 mt-1">
                {{ __('Product') }}: <span class="font-medium">{{ $u->workOrder?->productType?->name ?? $u->material?->name ?? '—' }}</span>
                @if($u->workOrder) &middot; {{ __('Work Order') }}: <span class="font-medium">{{ $u->workOrder->order_no }}</span>@endif
                &middot; <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ $u->status }}</span>
            </p>
        </div>

        <div class="card">
            <h3 class="text-lg font-bold text-gray-800 mb-3">{{ __('Process history') }} ({{ $u->history->count() }})</h3>
            @if($u->history->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No processing steps recorded for this unit yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach($u->history as $h)
                        <div class="border-l-2 {{ $h->result === 'fail' ? 'border-red-400' : 'border-green-400' }} pl-4 py-1">
                            <div class="flex items-center gap-2 flex-wrap text-sm">
                                <span class="font-semibold text-gray-800">{{ $h->workstation?->name ?? __('Unknown') }}</span>
                                @if($h->batchStep)<span class="text-xs text-gray-500">{{ $h->batchStep->name }}</span>@endif
                                @if($h->operator)<span class="text-xs text-gray-500">{{ __('by') }} {{ $h->operator->name }}</span>@endif
                                <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($h->processed_at)->format('Y-m-d H:i:s') }}</span>
                                @if($h->result)
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $h->result === 'pass' ? 'bg-green-100 text-green-700' : ($h->result === 'fail' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $h->result }}</span>
                                @endif
                            </div>
                            @if(!empty($h->parameters))
                                <div class="mt-1 ml-1 flex flex-wrap gap-2">
                                    @foreach($h->parameters as $pk => $pv)
                                        <span class="text-xs bg-gray-50 border border-gray-200 rounded px-2 py-0.5 text-gray-600"><span class="text-gray-400">{{ $pk }}:</span> {{ is_scalar($pv) ? $pv : json_encode($pv) }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
