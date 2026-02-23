@extends('layouts.app')

@section('title', 'Work Order Queue')

@section('content')
<div class="max-w-7xl mx-auto" x-data="{ view: localStorage.getItem('queueView') || 'table' }" x-init="$watch('view', v => localStorage.setItem('queueView', v))">

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Work Order Queue</h1>
            <p class="text-gray-600 mt-2">Line: {{ $line->name }}</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- View toggle --}}
            <div class="flex items-center bg-gray-100 rounded-lg p-1 gap-1">
                <button type="button" @click="view = 'table'"
                        :class="view === 'table' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18"/>
                    </svg>
                    Table
                </button>
                <button type="button" @click="view = 'cards'"
                        :class="view === 'cards' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Cards
                </button>
            </div>
            <a href="{{ route('operator.select-line') }}" class="btn-touch btn-secondary text-sm">
                Change Line
            </a>
        </div>
    </div>

    <!-- ===================== ACTIVE WORK ORDERS ===================== -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-3">
            Active Work Orders
            <span class="text-sm font-normal text-gray-500 ml-2">({{ $activeWorkOrders->count() }})</span>
        </h2>

        @if($activeWorkOrders->isEmpty())
            <div class="card text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No active work orders</h3>
                <p class="mt-1 text-sm text-gray-500">There are no work orders currently in progress on this line.</p>
            </div>
        @else

            {{-- ── TABLE VIEW ── --}}
            <div x-show="view === 'table'" x-cloak class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Order No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                @if($lineStatuses->isNotEmpty())
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    Board Status
                                    <span class="ml-1 text-gray-400 font-normal normal-case text-xs" title="Tap row to cycle">↻</span>
                                </th>
                                @endif
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty (done / planned)</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Batches</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($activeWorkOrders as $workOrder)
                                @php
                                    $ls = $workOrder->lineStatus;
                                    $rowStyle = 'border-left: 4px solid transparent';
                                    if ($ls && strlen($ls->color) === 7) {
                                        $r = hexdec(substr($ls->color, 1, 2));
                                        $g = hexdec(substr($ls->color, 3, 2));
                                        $b = hexdec(substr($ls->color, 5, 2));
                                        $rowStyle = "background-color:rgba($r,$g,$b,0.12);border-left:4px solid {$ls->color}";
                                    }
                                @endphp
                                <tr class="wo-row cursor-pointer transition-all"
                                    style="{{ $rowStyle }}"
                                    data-cycle="{{ $lineStatuses->isNotEmpty() ? '1' : '0' }}"
                                    data-status-id="{{ $workOrder->line_status_id ?? '' }}"
                                    data-status-url="{{ route('operator.work-order.line-status', $workOrder) }}"
                                    data-detail-url="{{ route('operator.work-order.detail', $workOrder) }}">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-800 whitespace-nowrap">
                                        {{ $workOrder->order_no }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @include('components.wo-status-badge', ['status' => $workOrder->status])
                                    </td>
                                    @if($lineStatuses->isNotEmpty())
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($ls)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold text-white"
                                                  style="background-color: {{ $ls->color }}">
                                                {{ $ls->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-sm">—</span>
                                        @endif
                                    </td>
                                    @endif
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $workOrder->productType?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                                        <span class="font-medium text-gray-800">
                                            {{ number_format($workOrder->produced_qty, 0) }} / {{ number_format($workOrder->planned_qty, 0) }}
                                        </span>
                                        @if($workOrder->planned_qty > 0)
                                            @php $pct = ($workOrder->produced_qty / $workOrder->planned_qty) * 100; @endphp
                                            <span class="text-xs text-gray-400 ml-1">({{ number_format($pct, 0) }}%)</span>
                                            <div class="mt-1 w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full bg-blue-500 rounded-full" style="width: {{ min($pct, 100) }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-center">
                                        {{ $workOrder->batches->count() }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-center">
                                        {{ $workOrder->priority ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                        {{ $workOrder->due_date ? \Carbon\Carbon::parse($workOrder->due_date)->format('d M') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right" data-detail-link="1">
                                        <svg class="w-6 h-6 text-blue-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── CARD VIEW ── --}}
            <div x-show="view === 'cards'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($activeWorkOrders as $workOrder)
                        <div class="card hover:shadow-xl transition-all">
                            <div class="flex items-center justify-between mb-3">
                                <a href="{{ route('operator.work-order.detail', $workOrder) }}"
                                   class="text-lg font-bold text-gray-800 hover:text-blue-700">{{ $workOrder->order_no }}</a>
                                @include('components.wo-status-badge', ['status' => $workOrder->status])
                            </div>
                            @if($lineStatuses->isNotEmpty())
                            <div class="mb-3" onclick="event.stopPropagation()">
                                <p class="text-xs text-gray-500 mb-1">Board Status</p>
                                <form method="POST" action="{{ route('operator.work-order.line-status', $workOrder) }}">
                                    @csrf
                                    <select name="line_status_id"
                                            onchange="this.form.submit()"
                                            class="w-full text-sm rounded-lg border border-gray-200 font-medium py-1.5 px-3 cursor-pointer">
                                        <option value="">— none —</option>
                                        @foreach($lineStatuses as $ls)
                                            <option value="{{ $ls->id }}"
                                                    {{ $workOrder->line_status_id == $ls->id ? 'selected' : '' }}>
                                                {{ $ls->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            @endif
                            <a href="{{ route('operator.work-order.detail', $workOrder) }}" class="block">
                                <div class="mb-3">
                                    <p class="text-sm text-gray-600">Product</p>
                                    <p class="font-medium text-gray-800">{{ $workOrder->productType?->name ?? '—' }}</p>
                                </div>
                                <div class="mb-3">
                                    <p class="text-sm text-gray-600">Quantity</p>
                                    <p class="font-medium text-gray-800">
                                        {{ number_format($workOrder->produced_qty, 2) }} / {{ number_format($workOrder->planned_qty, 2) }}
                                        @if($workOrder->planned_qty > 0)
                                            <span class="text-sm text-gray-500">
                                                ({{ number_format(($workOrder->produced_qty / $workOrder->planned_qty) * 100, 1) }}%)
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <p class="text-sm text-gray-600">Batches</p>
                                    <p class="font-medium text-gray-800">{{ $workOrder->batches->count() }}</p>
                                </div>
                                <div class="border-t border-gray-200 pt-3 mt-3 flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Priority: <span class="font-medium">{{ $workOrder->priority }}</span></span>
                                    @if($workOrder->due_date)
                                        <span class="text-gray-600">Due: <span class="font-medium">{{ \Carbon\Carbon::parse($workOrder->due_date)->format('M d') }}</span></span>
                                    @endif
                                </div>
                                <div class="mt-4 flex items-center justify-end text-blue-600">
                                    <span class="text-sm font-medium">View Details</span>
                                    <svg class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

        @endif
    </div>

    <!-- ===================== RECENTLY COMPLETED ===================== -->
    <div>
        <h2 class="text-xl font-bold text-gray-800 mb-3">
            Recently Completed
            <span class="text-sm font-normal text-gray-500 ml-2">({{ $completedWorkOrders->count() }})</span>
        </h2>

        @if($completedWorkOrders->isEmpty())
            <div class="card text-center py-8">
                <p class="text-sm text-gray-500">No recently completed work orders</p>
            </div>
        @else

            {{-- ── TABLE VIEW ── --}}
            <div x-show="view === 'table'" x-cloak class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Order No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produced</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Completed at</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($completedWorkOrders as $workOrder)
                                <tr class="hover:bg-gray-50 transition-colors cursor-pointer opacity-80"
                                    onclick="location.href='{{ route('operator.work-order.detail', $workOrder) }}'">
                                    <td class="px-4 py-3 font-mono font-semibold text-gray-700 whitespace-nowrap">
                                        {{ $workOrder->order_no }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $workOrder->productType?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 font-medium">
                                        {{ number_format($workOrder->produced_qty, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                        {{ $workOrder->completed_at ? \Carbon\Carbon::parse($workOrder->completed_at)->format('d M Y, H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <svg class="w-5 h-5 text-gray-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── CARD VIEW ── --}}
            <div x-show="view === 'cards'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($completedWorkOrders as $workOrder)
                        <a href="{{ route('operator.work-order.detail', $workOrder) }}" class="card hover:shadow-xl cursor-pointer transition-all opacity-75">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800">{{ $workOrder->order_no }}</h3>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Completed</span>
                            </div>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600">Product</p>
                                <p class="font-medium text-gray-800">{{ $workOrder->productType?->name ?? '—' }}</p>
                            </div>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600">Completed</p>
                                <p class="font-medium text-gray-800">{{ number_format($workOrder->produced_qty, 2) }}</p>
                            </div>
                            @if($workOrder->completed_at)
                                <div class="border-t border-gray-200 pt-3 mt-3 text-sm text-gray-600">
                                    Completed: {{ \Carbon\Carbon::parse($workOrder->completed_at)->format('M d, Y H:i') }}
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

        @endif
    </div>
</div>

<style>
[x-cloak]{display:none!important}

/* Row hover: darken slightly without disturbing the status tint */
.wo-row:hover { filter: brightness(0.93); }
.wo-row:active { filter: brightness(0.85); }

/* Arrow cell — large touch target, separate action */
td[data-detail-link] {
    min-width: 48px;
    cursor: pointer;
}
td[data-detail-link]:hover svg { color: #2563eb; }
</style>

@if($lineStatuses->isNotEmpty())
<script>
const WO_STATUSES = @json($lineStatuses->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color])->values());

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('tr.wo-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Arrow cell → navigate to detail
            if (e.target.closest('[data-detail-link]')) {
                location.href = row.dataset.detailUrl;
                return;
            }
            // Cycle board status
            if (row.dataset.cycle === '1') {
                cycleStatus(row);
            } else {
                location.href = row.dataset.detailUrl;
            }
        });
    });
});

function cycleStatus(row) {
    const currentId = row.dataset.statusId ? parseInt(row.dataset.statusId) : null;
    const ids = [null, ...WO_STATUSES.map(s => s.id)];
    const currentIdx = ids.indexOf(currentId);
    const nextId = ids[(currentIdx + 1) % ids.length];

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = row.dataset.statusUrl;
    form.style.display = 'none';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = (document.querySelector('input[name="_token"]') || {}).value || '';
    form.appendChild(token);

    const field = document.createElement('input');
    field.type = 'hidden';
    field.name = 'line_status_id';
    field.value = nextId !== null ? nextId : '';
    form.appendChild(field);

    document.body.appendChild(form);
    form.submit();
}
</script>
@endif

@endsection
