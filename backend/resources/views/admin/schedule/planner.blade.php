@extends('layouts.app')

@section('title', __('Production Planner'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Production Schedule'), 'url' => null],
]" />

@php
    $shiftColors = [
        1 => ['bg' => 'bg-sky-300',    'border' => 'border-sky-400',    'label' => 'S1', 'hours' => '00-06'],
        2 => ['bg' => 'bg-amber-300',  'border' => 'border-amber-400',  'label' => 'S2', 'hours' => '06-12'],
        3 => ['bg' => 'bg-orange-400', 'border' => 'border-orange-500', 'label' => 'S3', 'hours' => '12-18'],
        4 => ['bg' => 'bg-rose-400',   'border' => 'border-rose-500',   'label' => 'S4', 'hours' => '18-24'],
    ];
    $woColors = [
        'PENDING'     => 'bg-gray-200 border-gray-300',
        'ACCEPTED'    => 'bg-blue-200 border-blue-400',
        'IN_PROGRESS' => 'bg-amber-200 border-amber-400',
        'BLOCKED'     => 'bg-red-200 border-red-400',
        'PAUSED'      => 'bg-orange-200 border-orange-400',
        'DONE'        => 'bg-green-200 border-green-400',
    ];
    $woTextColors = [
        'PENDING'     => 'text-gray-700',
        'ACCEPTED'    => 'text-blue-800',
        'IN_PROGRESS' => 'text-amber-800',
        'BLOCKED'     => 'text-red-800',
        'PAUSED'      => 'text-orange-800',
        'DONE'        => 'text-green-800',
    ];
    $priorityLabels = [
        5 => ['label' => __('Urgent'), 'color' => 'text-red-600', 'bg' => 'bg-red-50 border-red-200', 'icon' => '⚠'],
        4 => ['label' => __('High'), 'color' => 'text-orange-600', 'bg' => 'bg-orange-50 border-orange-200', 'icon' => '▲'],
        3 => ['label' => __('Medium'), 'color' => 'text-amber-600', 'bg' => 'bg-amber-50 border-amber-200', 'icon' => '●'],
        2 => ['label' => __('Low'), 'color' => 'text-blue-600', 'bg' => 'bg-blue-50 border-blue-200', 'icon' => '▼'],
        1 => ['label' => __('Lowest'), 'color' => 'text-gray-500', 'bg' => 'bg-gray-50 border-gray-200', 'icon' => '—'],
    ];
    $statusLabels = [
        'PENDING'     => __('Pending'),
        'ACCEPTED'    => __('Accepted'),
        'IN_PROGRESS' => __('In Progress'),
        'BLOCKED'     => __('Blocked'),
        'PAUSED'      => __('Paused'),
        'DONE'        => __('Done'),
        'REJECTED'    => __('Rejected'),
        'CANCELLED'   => __('Cancelled'),
    ];
    $daysInWeek = $showWeekends ? 7 : 5;
    $backlogJson = $backlogOrders->map(fn($wo) => [
        'id' => $wo->id,
        'order_no' => $wo->order_no,
        'product' => $wo->productType?->name ?? '-',
        'qty' => $wo->planned_qty,
        'priority' => $wo->priority,
        'status' => $statusLabels[$wo->status] ?? $wo->status,
        'due_date' => $wo->due_date?->format('d.m.Y') ?? '-',
    ])->values()->toJson();
    $confirmMsg = __('Remove this order from schedule?');
@endphp

<script>
    function schedulePlanner() {
        return {
            tooltip: null, tx: 0, ty: 0,
            backlogSearch: '', backlogLine: '', backlogPriority: '', backlogSort: 'due_date',
            backlogCollapsed: false,
            assignPopup: false, assignLineId: null, assignDate: null, assignShift: null, assignWeekNumber: null,
            backlogItems: {!! $backlogJson !!},
            assignSearch: '',
            toast: null, toastTimeout: null,
            saving: false,

            // Drag state
            dragOrderId: null,
            dragOrderNo: null,
            dragOverCell: null,

            showTip(e, d) {
                this.tooltip = d;
                const r = e.target.getBoundingClientRect();
                this.tx = r.left + window.scrollX;
                this.ty = r.bottom + window.scrollY + 8;
            },
            hideTip() { this.tooltip = null; },

            showToast(msg, type) {
                this.toast = { msg, type };
                clearTimeout(this.toastTimeout);
                this.toastTimeout = setTimeout(() => this.toast = null, 3000);
            },

            openAssign(lineId, date, shift, weekNumber) {
                this.assignLineId = lineId;
                this.assignDate = date;
                this.assignShift = shift;
                this.assignWeekNumber = weekNumber;
                this.assignSearch = '';
                this.assignPopup = true;
            },
            closeAssign() { this.assignPopup = false; },

            async saveOrder(orderId, data) {
                this.saving = true;
                try {
                    const res = await fetch('/admin/schedule/' + orderId, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(data),
                    });
                    const json = await res.json();
                    if (json.success) {
                        this.showToast(json.message, 'success');
                        return json;
                    } else {
                        this.showToast(json.message || {!! json_encode(__('Error saving')) !!}, 'error');
                        return null;
                    }
                } catch (err) {
                    this.showToast({!! json_encode(__('Connection error')) !!}, 'error');
                    return null;
                } finally {
                    this.saving = false;
                }
            },

            async refreshContent() {
                try {
                    const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const html = await res.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Replace grid area
                    const newGrid = doc.querySelector('[data-schedule-grid]');
                    const oldGrid = document.querySelector('[data-schedule-grid]');
                    if (newGrid && oldGrid) oldGrid.innerHTML = newGrid.innerHTML;

                    // Replace backlog cards area
                    const newBacklog = doc.querySelector('[data-backlog-cards]');
                    const oldBacklog = document.querySelector('[data-backlog-cards]');
                    if (newBacklog && oldBacklog) oldBacklog.innerHTML = newBacklog.innerHTML;

                    // Replace backlog summary
                    const newSummary = doc.querySelector('[data-backlog-summary]');
                    const oldSummary = document.querySelector('[data-backlog-summary]');
                    if (newSummary && oldSummary) oldSummary.innerHTML = newSummary.innerHTML;

                    // Update backlog count in header
                    const newCount = doc.querySelector('[data-backlog-count]');
                    const oldCount = document.querySelector('[data-backlog-count]');
                    if (newCount && oldCount) oldCount.textContent = newCount.textContent;

                    // Update backlogItems from new page's script
                    const scriptMatch = html.match(/backlogItems:\s*(\[[\s\S]*?\]),\s*\n/);
                    if (scriptMatch) {
                        try { this.backlogItems = JSON.parse(scriptMatch[1]); } catch(e) {}
                    }
                } catch(e) { /* silent fail — data is already saved */ }
            },

            async assignOrder(orderId) {
                const data = { line_id: this.assignLineId };
                if (this.assignDate) data.due_date = this.assignDate;
                if (this.assignWeekNumber) data.week_number = this.assignWeekNumber;
                if (this.assignShift) data.shift_number = this.assignShift;

                const result = await this.saveOrder(orderId, data);
                if (result) {
                    this.assignPopup = false;
                    await this.refreshContent();
                }
            },

            async unassignOrder(orderId) {
                if (!confirm({!! json_encode($confirmMsg) !!})) return;
                const result = await this.saveOrder(orderId, { line_id: '', due_date: '', week_number: '', shift_number: '' });
                if (result) {
                    await this.refreshContent();
                }
            },

            // Drag and drop
            onDragStart(e, orderId, orderNo) {
                this.dragOrderId = orderId;
                if (orderNo) {
                    this.dragOrderNo = orderNo;
                } else {
                    const item = this.backlogItems.find(i => i.id == orderId);
                    this.dragOrderNo = item ? item.order_no : 'WO';
                }
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', orderId);
                e.target.style.opacity = '0.4';
            },
            onDragEnd(e) {
                this.dragOrderId = null;
                this.dragOrderNo = null;
                this.dragOverCell = null;
                e.target.style.opacity = '';
            },
            onDragOver(e, cellId) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.dragOverCell = cellId;
            },
            onDragLeave(e, cellId) {
                if (this.dragOverCell === cellId) this.dragOverCell = null;
            },
            async onDrop(e, lineId, date, shift, weekNumber) {
                e.preventDefault();
                const orderId = e.dataTransfer.getData('text/plain') || this.dragOrderId;
                this.dragOverCell = null;
                this.dragOrderId = null;
                this.dragOrderNo = null;
                if (!orderId) return;

                this.assignLineId = lineId;
                this.assignDate = date;
                this.assignShift = shift;
                this.assignWeekNumber = weekNumber;
                await this.assignOrder(orderId);
            }
        };
    }
</script>
<div x-data="schedulePlanner()">

    {{-- ===== TOOLBAR ===== --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-2.5 mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.schedule', ['start_date' => $navPrev->format('Y-m-d')]) }}"
           class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="{{ __('Previous') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">
            {{ $rangeStart->translatedFormat('d.m') }} &ndash; {{ $rangeEnd->translatedFormat('d.m.Y') }}
        </span>
        <a href="{{ route('admin.schedule', ['start_date' => $navNext->format('Y-m-d')]) }}"
           class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="{{ __('Next') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <div class="h-6 border-l border-gray-300 dark:border-gray-600 mx-1"></div>

        <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
            @foreach(['weekly' => __('Weekly'), 'daily' => __('Daily'), 'monthly' => __('Monthly')] as $mode => $ml)
                <a href="{{ route('admin.schedule', ['start_date' => $startDate->format('Y-m-d'), 'view_mode' => $mode, 'line_id' => request('line_id')]) }}"
                   class="px-3 py-1 text-xs font-medium rounded-md transition {{ $viewMode === $mode ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $ml }}
                </a>
            @endforeach
        </div>

        <div class="h-6 border-l border-gray-300 dark:border-gray-600 mx-1"></div>

        <form method="GET" action="{{ route('admin.schedule') }}" class="flex items-center gap-1.5">
            <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            <select name="line_id" onchange="this.form.submit()"
                    class="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2">
                <option value="">{{ __('All Lines') }}</option>
                @foreach($allLines as $l)
                    <option value="{{ $l->id }}" {{ request('line_id') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                @endforeach
            </select>
        </form>

        <div class="flex-1"></div>

        <div class="flex items-center gap-2 text-[10px] text-gray-500">
            @for($s = 1; $s <= $shiftsPerDay; $s++)
                <span class="inline-flex items-center gap-1">
                    <span class="w-3 h-2.5 rounded-sm {{ $shiftColors[$s]['bg'] }}"></span>
                    {{ $shiftColors[$s]['label'] }}
                </span>
            @endfor
        </div>

        <div class="h-6 border-l border-gray-300 dark:border-gray-600 mx-1"></div>

        <a href="{{ route('admin.schedule') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition">
            {{ __('Today') }}
        </a>
    </div>

    {{-- ===== MAIN LAYOUT: Gantt + Backlog ===== --}}
    <div class="flex gap-4">

        {{-- LEFT: Main schedule area --}}
        <div class="flex-1 min-w-0" data-schedule-grid>

            {{-- ===== WEEKLY VIEW ===== --}}
            @if($viewMode === 'weekly')
                <div class="space-y-4">
                @foreach($data as $period)
                    @php
                        $isOverloaded = $period['total_load_percent'] > 100;
                        $isCurrentWeek = now()->isoWeek() === $period['number'] && now()->isoWeekYear() === $period['start']->isoWeekYear();
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-xl border-2 overflow-hidden shadow-sm
                                {{ $isOverloaded ? 'border-red-400' : ($isCurrentWeek ? 'border-blue-400' : 'border-gray-200 dark:border-gray-700') }}">

                        {{-- Week header --}}
                        <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-black text-gray-800 dark:text-gray-100">
                                    {{ __('wk') }}. {{ $period['number'] }}
                                </span>
                                <span class="text-sm text-gray-500">{{ $period['start']->format('d.m') }}&ndash;{{ $period['end']->format('d.m') }}</span>
                                <div class="flex gap-0.5">
                                    @for($s = 1; $s <= $shiftsPerDay; $s++)
                                        <div class="w-5 h-3 rounded-sm {{ $shiftColors[$s]['bg'] }} flex items-center justify-center text-[7px] font-bold text-white/80">
                                            {{ $shiftColors[$s]['label'] }}
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-xs">
                                <span class="text-gray-500">{{ __('orders') }}: <strong class="text-gray-800 dark:text-gray-100">{{ $period['total_orders'] }}</strong></span>
                                <span>
                                    {{ __('load') }}:
                                    <strong class="@if($period['total_load_percent'] > 100) text-red-600 @elseif($period['total_load_percent'] > 80) text-orange-600 @else text-green-600 @endif">
                                        {{ $period['total_load_percent'] }}%
                                    </strong>
                                </span>
                                <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full @if($period['total_load_percent'] > 100) bg-red-500 @elseif($period['total_load_percent'] > 80) bg-orange-500 @else bg-green-500 @endif"
                                         style="width: {{ min($period['total_load_percent'], 100) }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Gantt grid --}}
                        <div>
                            <table class="w-full border-collapse table-fixed">
                                <colgroup>
                                    <col style="width: 100px;">
                                    @for($d = 0; $d < $daysInWeek; $d++)
                                        <col style="width: {{ 100 / $daysInWeek }}%;">
                                    @endfor
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="p-1.5 text-left text-[10px] font-semibold text-gray-400 uppercase border-r border-gray-100 dark:border-gray-700">
                                            {{ __('Line') }} / {{ __('Shift') }}
                                        </th>
                                        @php $dayCursor = $period['start']->copy(); @endphp
                                        @for($d = 0; $d < $daysInWeek; $d++)
                                            @php $isToday = $dayCursor->isToday(); @endphp
                                            <th class="p-1 text-center border-r border-gray-100 dark:border-gray-700
                                                       {{ $isToday ? 'bg-blue-100 dark:bg-blue-900/40' : '' }}
                                                       {{ $dayCursor->isWeekend() ? 'bg-gray-50/50' : '' }}">
                                                <div class="text-[10px] text-gray-400 uppercase">{{ $dayCursor->translatedFormat('D') }}</div>
                                                <div class="text-xs font-bold {{ $isToday ? 'text-blue-700' : 'text-gray-700 dark:text-gray-200' }}">{{ $dayCursor->format('d.m') }}</div>
                                                @if($isToday)
                                                    <div class="h-0.5 bg-blue-500 rounded-full mt-0.5 mx-auto w-8"></div>
                                                @endif
                                            </th>
                                            @php $dayCursor->addDay(); @endphp
                                        @endfor
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($period['lines'] as $lineData)
                                        @php
                                            $line = $lineData['line'];
                                            $orders = $lineData['orders'];
                                            $grid = $lineData['grid'] ?? [];
                                            $lineLoad = $lineData['load_percent'];
                                        @endphp

                                        {{-- Line section header --}}
                                        <tr class="bg-gray-50/80 dark:bg-gray-700/30">
                                            <td colspan="{{ $daysInWeek + 1 }}" class="px-2 py-1">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ $line->name }}</span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[10px] text-gray-400">{{ __('load') }}:</span>
                                                        <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                                            <div class="h-full rounded-full @if($lineLoad > 100) bg-red-500 @elseif($lineLoad > 80) bg-orange-500 @else bg-green-500 @endif"
                                                                 style="width: {{ min($lineLoad, 100) }}%"></div>
                                                        </div>
                                                        <span class="text-[10px] font-semibold @if($lineLoad > 100) text-red-600 @elseif($lineLoad > 80) text-orange-600 @else text-green-600 @endif">{{ $lineLoad }}%</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Shift rows --}}
                                        @for($s = 1; $s <= $shiftsPerDay; $s++)
                                            <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50/30">
                                                <td class="px-2 py-0.5 text-[10px] font-medium border-r border-gray-100 dark:border-gray-700 whitespace-nowrap">
                                                    <span class="inline-flex items-center gap-1">
                                                        <span class="w-2.5 h-2 rounded-sm {{ $shiftColors[$s]['bg'] }}"></span>
                                                        <span class="text-gray-500">{{ $shiftColors[$s]['label'] }}</span>
                                                        <span class="text-gray-400">{{ $shiftColors[$s]['hours'] }}</span>
                                                    </span>
                                                </td>
                                                @php $dayCursor2 = $period['start']->copy(); @endphp
                                                @for($d = 0; $d < $daysInWeek; $d++)
                                                    @php
                                                        $cellDate = $dayCursor2->format('Y-m-d');
                                                        $gridKey = $cellDate . '-' . $s;
                                                        $slotOrder = $grid[$gridKey] ?? null;
                                                        $isToday = $dayCursor2->isToday();
                                                    @endphp
                                                    <td class="p-0.5 border-r border-gray-50 dark:border-gray-700/30
                                                               {{ $isToday ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}
                                                               {{ $dayCursor2->isWeekend() ? 'bg-gray-50/30' : '' }}">
                                                        @php $cellId = "cell-{$line->id}-{$d}-{$s}-{$period['number']}"; @endphp
                                                        @if($slotOrder)
                                                            {{-- Assigned order - draggable + accepts drops --}}
                                                            @php
                                                                $isOverdue = $slotOrder->due_date
                                                                    && $slotOrder->due_date->lt(today())
                                                                    && !in_array($slotOrder->status, \App\Models\WorkOrder::TERMINAL_STATUSES);
                                                            @endphp
                                                            <div class="relative group/cell"
                                                                 data-order-id="{{ $slotOrder->id }}" data-order-no="{{ $slotOrder->order_no }}"
                                                                 draggable="true"
                                                                 @dragstart="onDragStart($event, {{ $slotOrder->id }}, '{{ addslashes($slotOrder->order_no) }}')"
                                                                 @dragend="onDragEnd($event)"
                                                                 @dragover.prevent="onDragOver($event, '{{ $cellId }}')"
                                                                 @dragleave="onDragLeave($event, '{{ $cellId }}')"
                                                                 @drop="onDrop($event, {{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})">
                                                                <a href="{{ route('admin.work-orders.show', $slotOrder) }}"
                                                                   class="block px-1.5 py-0.5 rounded border text-[10px] font-medium truncate cursor-grab active:cursor-grabbing hover:opacity-80 transition
                                                                          @if($isOverdue) bg-red-500 border-red-600 text-white animate-pulse ring-2 ring-red-400 @else {{ $woColors[$slotOrder->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$slotOrder->status] ?? 'text-gray-700' }} @endif"
                                                                   @click.prevent
                                                                   x-on:mouseenter="showTip($event, {
                                                                       order_no: '{{ addslashes($slotOrder->order_no) }}',
                                                                       product: '{{ addslashes($slotOrder->productType?->name ?? '-') }}',
                                                                       qty: '{{ $slotOrder->planned_qty }}',
                                                                       status: '{{ $statusLabels[$slotOrder->status] ?? $slotOrder->status }}'
                                                                   })"
                                                                   x-on:mouseleave="hideTip()">
                                                                    {{ $slotOrder->order_no }}
                                                                </a>
                                                                <button @click.prevent="unassignOrder({{ $slotOrder->id }})"
                                                                        class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center
                                                                               opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-10"
                                                                        title="{{ __('Remove from schedule') }}">
                                                                    ✕
                                                                </button>
                                                            </div>
                                                        @else
                                                            {{-- Empty cell - click or drop --}}
                                                            <div @click="openAssign({{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})"
                                                                 @dragover.prevent="onDragOver($event, '{{ $cellId }}')"
                                                                 @dragleave="onDragLeave($event, '{{ $cellId }}')"
                                                                 @drop="onDrop($event, {{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})"
                                                                 data-cell-line="{{ $line->id }}" data-cell-date="{{ $cellDate }}" data-cell-shift="{{ $s }}"
                                                                 class="h-6 rounded transition-all cursor-pointer relative overflow-hidden"
                                                                 :class="dragOverCell === '{{ $cellId }}'
                                                                     ? 'bg-blue-200 border-2 border-dashed border-blue-500 scale-[1.02]'
                                                                     : '{{ $shiftColors[$s]['bg'] }} opacity-15 hover:opacity-40'">
                                                                {{-- Drag preview: show order number when hovering --}}
                                                                <span x-show="dragOverCell === '{{ $cellId }}' && dragOrderNo"
                                                                      x-text="dragOrderNo"
                                                                      class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-blue-700"></span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    @php $dayCursor2->addDay(); @endphp
                                                @endfor
                                            </tr>
                                        @endfor
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
                </div>

            {{-- ===== DAILY VIEW ===== --}}
            @elseif($viewMode === 'daily')
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-x-auto">
                    <table class="w-full border-collapse table-fixed" style="min-width: {{ count($data) * 80 + 120 }}px;">
                        <colgroup>
                            <col style="width: 120px;">
                            @foreach($data as $day)
                                <col>
                            @endforeach
                        </colgroup>
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="sticky left-0 z-10 bg-white dark:bg-gray-800 p-2 text-xs font-semibold text-gray-500 text-left border-r border-gray-200 dark:border-gray-700">
                                    {{ __('Production line') }}
                                </th>
                                @foreach($data as $day)
                                    @php $isToday = $day['date']->isToday(); @endphp
                                    <th class="p-1.5 text-center border-r border-gray-100 dark:border-gray-700
                                               {{ $isToday ? 'bg-blue-100 dark:bg-blue-900/40' : '' }}
                                               {{ $day['date']->isWeekend() ? 'bg-gray-50 dark:bg-gray-800/60' : '' }}">
                                        <div class="text-[10px] text-gray-400 uppercase">{{ $day['date']->translatedFormat('D') }}</div>
                                        <div class="text-xs font-bold {{ $isToday ? 'text-blue-700' : 'text-gray-700 dark:text-gray-200' }}">{{ $day['date']->format('d.m') }}</div>
                                        @if($isToday)
                                            <div class="h-0.5 bg-blue-500 rounded-full mt-0.5 mx-auto w-8"></div>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50/50">
                                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 p-2 text-xs font-medium text-gray-700 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700 whitespace-nowrap">
                                        {{ $line->code ?? $line->name }}
                                    </td>
                                    @foreach($data as $day)
                                        @php
                                            $dayLineData = collect($day['lines'])->firstWhere('line.id', $line->id);
                                            $dayOrders = $dayLineData ? $dayLineData['orders'] : collect();
                                            $isToday = $day['date']->isToday();
                                        @endphp
                                        <td class="p-1 border-r border-gray-100 dark:border-gray-700/50 align-top
                                                   {{ $isToday ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}">
                                            <div class="flex flex-col gap-0.5">
                                                @foreach($dayOrders as $wo)
                                                    @php $isOverdue = $wo->due_date && $wo->due_date->lt(today()) && !in_array($wo->status, \App\Models\WorkOrder::TERMINAL_STATUSES); @endphp
                                                    <div class="relative group/cell">
                                                        <a href="{{ route('admin.work-orders.show', $wo) }}"
                                                           class="block px-1 py-0.5 rounded text-[9px] font-medium truncate border
                                                                  @if($isOverdue) bg-red-500 border-red-600 text-white animate-pulse ring-2 ring-red-400 @else {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? 'text-gray-800' }} @endif"
                                                           title="{{ $wo->order_no }}">
                                                            {{ $wo->order_no }}
                                                        </a>
                                                        <button @click.prevent="unassignOrder({{ $wo->id }})"
                                                                class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center
                                                                       opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-10"
                                                                title="{{ __('Remove from schedule') }}">
                                                            ✕
                                                        </button>
                                                    </div>
                                                @endforeach
                                                @if($dayOrders->isEmpty())
                                                    <div class="h-5"></div>
                                                @endif
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            {{-- ===== MONTHLY VIEW ===== --}}
            @elseif($viewMode === 'monthly')
                <div class="space-y-4">
                @foreach($data as $period)
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                            <span class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $period['label'] }}</span>
                            <div class="flex items-center gap-4 text-sm">
                                <span>{{ __('orders') }}: <strong>{{ $period['total_orders'] }}</strong></span>
                                <span>{{ __('load') }}: <strong class="@if($period['total_load_percent'] > 80) text-red-600 @else text-green-600 @endif">{{ $period['total_load_percent'] }}%</strong></span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                            @foreach($period['lines'] as $lineData)
                                <div class="flex items-center px-4 py-2.5 hover:bg-gray-50/50">
                                    <div class="w-32 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $lineData['line']->name }}</div>
                                    <div class="flex-1 flex flex-wrap gap-1.5">
                                        @foreach($lineData['orders'] as $wo)
                                            @php $isOverdue = $wo->due_date && $wo->due_date->lt(today()) && !in_array($wo->status, \App\Models\WorkOrder::TERMINAL_STATUSES); @endphp
                                            <a href="{{ route('admin.work-orders.show', $wo) }}"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-medium
                                                      @if($isOverdue) bg-red-500 border-red-600 text-white animate-pulse ring-2 ring-red-400 @else {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? 'text-gray-800' }} @endif">
                                                {{ $wo->order_no }} <span class="opacity-50">&middot;</span> {{ $wo->planned_qty }}{{ __('pcs') }}
                                            </a>
                                        @endforeach
                                        @if($lineData['orders']->isEmpty())
                                            <span class="text-xs text-gray-400 italic">{{ __('No orders') }}</span>
                                        @endif
                                    </div>
                                    <div class="w-20 text-right text-sm font-bold @if($lineData['load_percent'] > 80) text-red-600 @else text-green-600 @endif">
                                        {{ $lineData['load_percent'] }}%
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                </div>
            @endif

        </div>

        {{-- RIGHT: Backlog panel --}}
        <div class="shrink-0 transition-all duration-300"
             :class="backlogCollapsed ? 'w-10' : 'w-[380px]'">

            <div x-show="backlogCollapsed" class="h-full">
                <button @click="backlogCollapsed = false"
                        class="w-10 h-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm flex flex-col items-center pt-4 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    <span class="mt-2 text-[10px] font-medium text-gray-500 [writing-mode:vertical-lr]">{{ __('Backlog') }} ({{ $backlogOrders->count() }})</span>
                </button>
            </div>

            <div x-show="!backlogCollapsed" x-cloak
                 class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm flex flex-col h-[calc(100vh-180px)] sticky top-4">

                <div class="px-3 py-2.5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ __('Backlog') }}</span>
                        <span class="text-xs text-gray-400" data-backlog-count>({{ $backlogOrders->count() }})</span>
                    </div>
                    <button @click="backlogCollapsed = true" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
                    <input type="text" x-model="backlogSearch" placeholder="{{ __('Search orders...') }}"
                           class="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2.5 placeholder-gray-400">
                </div>

                <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700 space-y-2">
                    <div class="flex flex-wrap gap-1">
                        <button @click="backlogLine = ''" :class="backlogLine === '' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-2 py-0.5 text-[10px] font-medium rounded transition">{{ __('All') }}</button>
                        @foreach($allLines as $l)
                            <button @click="backlogLine = backlogLine === '{{ $l->id }}' ? '' : '{{ $l->id }}'"
                                    :class="backlogLine === '{{ $l->id }}' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="px-2 py-0.5 text-[10px] font-medium rounded transition">{{ $l->code ?? $l->name }}</button>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($priorityLabels as $pv => $pl)
                            <button @click="backlogPriority = backlogPriority === '{{ $pv }}' ? '' : '{{ $pv }}'"
                                    :class="backlogPriority === '{{ $pv }}' ? 'ring-2 ring-gray-400' : ''"
                                    class="px-2 py-0.5 text-[10px] font-medium rounded border {{ $pl['bg'] }} {{ $pl['color'] }} transition">
                                {{ $pl['icon'] }} {{ $pl['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-gray-400">{{ __('Sort') }}:</span>
                        <select x-model="backlogSort" class="text-[10px] border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded py-0.5 px-1.5">
                            <option value="due_date">{{ __('Due date') }}</option>
                            <option value="priority">{{ __('Priority') }}</option>
                            <option value="planned_qty">{{ __('Quantity') }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2" data-backlog-cards>
                    @if($backlogOrders->isEmpty())
                        <div class="text-center py-8">
                            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25-2.25M12 13.875V7.5M3.75 7.5h16.5"/></svg>
                            <p class="text-xs text-gray-400">{{ __('No unassigned orders') }}</p>
                        </div>
                    @else
                        @php $groupedBacklog = $backlogOrders->groupBy('priority')->sortKeysDesc(); @endphp
                        @foreach($groupedBacklog as $priority => $orders)
                            @php $pl = $priorityLabels[$priority] ?? $priorityLabels[3]; @endphp

                            <div class="flex items-center gap-1.5 pt-1" x-show="!backlogPriority || backlogPriority === '{{ $priority }}'">
                                <span class="text-[10px] font-bold {{ $pl['color'] }}">{{ $pl['icon'] }} {{ $pl['label'] }}</span>
                                <span class="text-[10px] text-gray-400">({{ $orders->count() }})</span>
                                <div class="flex-1 border-t border-gray-100 dark:border-gray-700"></div>
                            </div>

                            @foreach($orders as $wo)
                                <div class="border rounded-lg p-2.5 {{ $pl['bg'] }} hover:shadow-sm transition text-xs cursor-grab active:cursor-grabbing"
                                     draggable="true"
                                     @dragstart="onDragStart($event, {{ $wo->id }})"
                                     @dragend="onDragEnd($event)"
                                     x-show="(!backlogPriority || backlogPriority === '{{ $priority }}')
                                             && (!backlogLine || backlogLine === '{{ $wo->line_id }}' || '{{ $wo->line_id }}' === '')
                                             && (!backlogSearch || '{{ strtolower($wo->order_no . ' ' . ($wo->productType?->name ?? '')) }}'.includes(backlogSearch.toLowerCase()))">

                                    <div class="flex items-start justify-between mb-1.5">
                                        <div>
                                            <a href="{{ route('admin.work-orders.show', $wo) }}" class="font-bold text-gray-800 dark:text-gray-100 hover:underline">
                                                {{ $wo->order_no }}
                                            </a>
                                            <div class="text-[10px] text-gray-500 mt-0.5">{{ $wo->productType?->name ?? '-' }}</div>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-medium {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? '' }} border">
                                            {{ $statusLabels[$wo->status] ?? $wo->status }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[10px] text-gray-600 dark:text-gray-400">
                                        <div>{{ __('Qty') }}: <strong class="text-gray-800 dark:text-gray-200">{{ number_format($wo->planned_qty) }}</strong></div>
                                        <div>{{ __('Due') }}: <strong class="@if($wo->due_date?->isPast()) text-red-600 @else text-gray-800 dark:text-gray-200 @endif">{{ $wo->due_date?->format('d.m.Y') ?? '-' }}</strong></div>
                                        <div>{{ __('Line') }}: <strong class="text-gray-800 dark:text-gray-200">{{ $wo->line?->code ?? $wo->line?->name ?? __('unassigned') }}</strong></div>
                                        <div>{{ __('Priority') }}: <strong class="{{ $pl['color'] }}">{{ $wo->priority ?? '-' }}</strong></div>
                                    </div>

                                    @if(!$wo->line_id)
                                        <div class="mt-1.5 text-[10px] text-amber-600 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-1">
                                            {{ __('Suggestion') }}: {{ __('Assign to available line with free capacity') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    @endif
                </div>

                <div class="px-3 py-2.5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/80 rounded-b-xl" data-backlog-summary>
                    <div class="grid grid-cols-3 gap-2 text-center mb-2.5">
                        <div>
                            <div class="text-[10px] text-gray-400">{{ __('total pcs') }}</div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ number_format($backlogOrders->sum('planned_qty')) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400">{{ __('orders') }}</div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $backlogOrders->count() }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-400">{{ __('urgent') }}</div>
                            <div class="text-sm font-bold text-red-600">{{ $backlogOrders->where('priority', '>=', 4)->count() }}</div>
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <a href="{{ route('admin.work-orders.create') }}"
                           class="flex-1 text-center py-1.5 rounded-lg text-[10px] font-medium bg-blue-600 text-white hover:bg-blue-700 transition">
                            + {{ __('Add') }}
                        </a>
                        <a href="{{ route('admin.csv-import') }}"
                           class="flex-1 text-center py-1.5 rounded-lg text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 transition border border-gray-300 dark:border-gray-600">
                            {{ __('Import CSV') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ===== ASSIGN POPUP ===== --}}
    <div x-show="assignPopup" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @click.self="closeAssign()" @keydown.escape.window="closeAssign()">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-[420px] max-h-[70vh] flex flex-col border border-gray-200 dark:border-gray-700"
             @click.stop>
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ __('Assign order to shift') }}</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">
                        {{ __('Select an unassigned order to place in this slot') }}
                    </p>
                </div>
                <button @click="closeAssign()" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                <input type="text" x-model="assignSearch" placeholder="{{ __('Search by order number or product...') }}"
                       class="w-full text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg py-1.5 px-2.5 placeholder-gray-400"
                       x-ref="assignSearchInput">
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-2 space-y-1.5">
                <template x-for="item in backlogItems.filter(i => !assignSearch || (i.order_no + ' ' + i.product).toLowerCase().includes(assignSearch.toLowerCase()))" :key="item.id">
                    <button @click="assignOrder(item.id)"
                            class="w-full text-left p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition group">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-800 dark:text-gray-100" x-text="item.order_no"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500" x-text="item.status"></span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-[10px] text-gray-500">
                            <span x-text="item.product"></span>
                            <span>&middot;</span>
                            <span x-text="item.qty + ' {{ __('pcs') }}'"></span>
                            <span>&middot;</span>
                            <span x-text="'{{ __('Due') }}: ' + item.due_date"></span>
                        </div>
                    </button>
                </template>
                <div x-show="backlogItems.filter(i => !assignSearch || (i.order_no + ' ' + i.product).toLowerCase().includes(assignSearch.toLowerCase())).length === 0"
                     class="text-center py-6 text-xs text-gray-400">
                    {{ __('No matching orders found') }}
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TOAST ===== --}}
    <div x-show="toast" x-transition.opacity.duration.200ms x-cloak
         class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-sm font-medium max-w-sm"
         :class="toast?.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
        <template x-if="toast?.type === 'success'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </template>
        <template x-if="toast?.type === 'error'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </template>
        <span x-text="toast?.msg"></span>
    </div>

    {{-- ===== SAVING OVERLAY ===== --}}
    <div x-show="saving" x-cloak class="fixed inset-0 z-40 bg-white/10 pointer-events-none flex items-center justify-center">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg px-4 py-2 text-sm text-gray-600 flex items-center gap-2">
            <svg class="animate-spin h-4 w-4 text-blue-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            {{ __('Saving...') }}
        </div>
    </div>

    {{-- ===== TOOLTIP ===== --}}
    <div x-show="tooltip" x-transition.opacity.duration.100ms x-cloak
         class="fixed z-40 bg-gray-900 text-white rounded-lg shadow-xl px-3 py-2 text-xs pointer-events-none max-w-xs"
         :style="'left:' + tx + 'px; top:' + ty + 'px'">
        <template x-if="tooltip">
            <div>
                <div class="font-bold mb-1" x-text="tooltip.order_no"></div>
                <div class="opacity-80" x-text="tooltip.product"></div>
                <div class="opacity-80">{{ __('Qty') }}: <span x-text="tooltip.qty"></span></div>
                <div class="opacity-80">{{ __('Status') }}: <span x-text="tooltip.status"></span></div>
            </div>
        </template>
    </div>

</div>
@endsection
