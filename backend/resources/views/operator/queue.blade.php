@extends('layouts.app')

@section('title', 'Work Order Queue')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="{
         view: localStorage.getItem('queueView') || 'table',
         report: { open: false, woId: null, woNo: '', title: '', typeId: '', desc: '' }
     }"
     x-init="$watch('view', v => localStorage.setItem('queueView', v))">

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
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
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
                                    {{-- Actions cell — does NOT cycle status --}}
                                    <td class="px-3 py-2 whitespace-nowrap" data-actions-cell="1">
                                        <button type="button"
                                                @click.stop="report.open = true; report.woId = {{ $workOrder->id }}; report.woNo = '{{ addslashes($workOrder->order_no) }}'; report.title = ''; report.typeId = ''; report.desc = ''"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 active:bg-orange-200 transition-colors"
                                                title="Report issue">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                                            </svg>
                                            Report
                                        </button>
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
                                <div class="mt-4 flex items-center justify-between">
                                    <button type="button"
                                            @click.stop="report.open = true; report.woId = {{ $workOrder->id }}; report.woNo = '{{ addslashes($workOrder->order_no) }}'; report.title = ''; report.typeId = ''; report.desc = ''"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-orange-600 bg-orange-50 hover:bg-orange-100 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                                        </svg>
                                        Report
                                    </button>
                                    <span class="flex items-center gap-1 text-blue-600 text-sm font-medium">
                                        View Details
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </span>
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

    {{-- ── REPORT ISSUE MODAL (shared, Alpine-driven) ── inside x-data scope --}}
@if($issueTypes->isNotEmpty())
<div x-show="report.open" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="report.open = false"></div>
    <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="relative bg-white w-full sm:max-w-lg sm:rounded-xl shadow-2xl"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             @click.stop>

            {{-- Drag handle on mobile --}}
            <div class="flex justify-center pt-3 pb-1 sm:hidden">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>

            <div class="px-5 pt-3 pb-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Report Issue</h3>
                        <p class="text-sm text-gray-500 font-mono" x-text="report.woNo"></p>
                    </div>
                    <button type="button" @click="report.open = false"
                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form action="{{ route('operator.issue.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="work_order_id" :value="report.woId">

                    {{-- Issue type selector --}}
                    <div>
                        <label class="form-label">Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            @foreach($issueTypes as $type)
                                <label class="flex items-center gap-2 p-2.5 rounded-lg border-2 cursor-pointer transition-colors"
                                       :class="report.typeId == '{{ $type->id }}'
                                           ? 'border-orange-400 bg-orange-50'
                                           : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="issue_type_id" value="{{ $type->id }}"
                                           x-model="report.typeId"
                                           @change="if (!report.title || issueTypeNames.includes(report.title)) report.title = '{{ addslashes($type->name) }}'"
                                           class="sr-only" required>
                                    <span class="flex-1 text-sm font-medium text-gray-700 leading-tight">
                                        {{ $type->name }}
                                        @if($type->is_blocking)
                                            <span class="block text-xs text-red-500 font-normal">⚠ blocking</span>
                                        @endif
                                    </span>
                                    <svg x-show="report.typeId == '{{ $type->id }}'" class="w-4 h-4 text-orange-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Title --}}
                    <div>
                        <label class="form-label">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" x-model="report.title"
                               class="form-input w-full" placeholder="Brief summary…"
                               required maxlength="255">
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="form-label">Details <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                        <textarea name="description" x-model="report.desc"
                                  rows="3" class="form-input w-full resize-none"
                                  placeholder="Additional details, photos description, measurements…"
                                  maxlength="2000"></textarea>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="report.open = false"
                                class="btn-touch btn-secondary flex-1">Cancel</button>
                        <button type="submit"
                                class="btn-touch flex-1 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg"
                                :disabled="!report.typeId || !report.title">
                            <svg class="w-4 h-4 inline-block mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.75-2.97L12.75 4.97a2 2 0 00-3.5 0l-7 12A2 2 0 005.07 19z"/>
                            </svg>
                            Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

</div>{{-- end x-data --}}

<style>
[x-cloak]{display:none!important}
.wo-row:hover { filter: brightness(0.93); }
.wo-row:active { filter: brightness(0.85); }
td[data-detail-link] { min-width: 48px; cursor: pointer; }
td[data-detail-link]:hover svg { color: #2563eb; }
</style>

<script>
@if($issueTypes->isNotEmpty())
const issueTypeNames = @json($issueTypes->pluck('name')->values());
@endif
@if($lineStatuses->isNotEmpty())
const WO_STATUSES = @json($lineStatuses->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color])->values());
@else
const WO_STATUSES = [];
@endif

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('tr.wo-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Arrow cell → navigate to detail
            if (e.target.closest('[data-detail-link]')) {
                location.href = row.dataset.detailUrl;
                return;
            }
            // Actions cell → handled by Alpine buttons, stop here
            if (e.target.closest('[data-actions-cell]')) {
                return;
            }
            // Cycle board status (if statuses configured)
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

@endsection
