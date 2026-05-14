@extends('layouts.app')

@section('title', __('Production Planner'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Production Schedule'), 'url' => null],
]" />

@php
    // Shift color palette (Z1..Z4)
    $shiftColors = [
        1 => ['bg' => 'bg-sky-300',    'label' => 'Z1'],
        2 => ['bg' => 'bg-amber-300',  'label' => 'Z2'],
        3 => ['bg' => 'bg-orange-400', 'label' => 'Z3'],
        4 => ['bg' => 'bg-rose-400',   'label' => 'Z4'],
    ];
    // Work order colors by status
    $woColors = [
        'PENDING'     => 'bg-gray-300 border-gray-400',
        'ACCEPTED'    => 'bg-blue-300 border-blue-400',
        'IN_PROGRESS' => 'bg-amber-300 border-amber-500',
        'BLOCKED'     => 'bg-red-300 border-red-500',
        'PAUSED'      => 'bg-orange-300 border-orange-500',
        'DONE'        => 'bg-green-300 border-green-500',
    ];
    $woTextColors = [
        'PENDING'     => 'text-gray-800',
        'ACCEPTED'    => 'text-blue-900',
        'IN_PROGRESS' => 'text-amber-900',
        'BLOCKED'     => 'text-red-900',
        'PAUSED'      => 'text-orange-900',
        'DONE'        => 'text-green-900',
    ];
    $daysInWeek = $showWeekends ? 7 : 5;
    $totalSlotsPerLine = $daysInWeek * $shiftsPerDay;
@endphp

<div x-data="{
    tooltip: null,
    tx: 0, ty: 0,
    showTip(e, d) {
        this.tooltip = d;
        const r = e.target.getBoundingClientRect();
        this.tx = r.left + window.scrollX;
        this.ty = r.bottom + window.scrollY + 8;
    },
    hideTip() { this.tooltip = null; }
}">

    {{-- ===== TOOLBAR ===== --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-2.5 mb-4 flex flex-wrap items-center gap-3">
        {{-- Nav --}}
        <a href="{{ route('admin.schedule', ['start_date' => $navPrev->format('Y-m-d')]) }}"
           class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="{{ __('Previous') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">
            {{ __('horizon') }}: {{ $horizonWeeks }} {{ trans_choice('week|weeks', $horizonWeeks) }}
            <span class="text-gray-400 mx-1">&middot;</span>
            {{ $rangeStart->translatedFormat('d.m') }} &ndash; {{ $rangeEnd->translatedFormat('d.m.Y') }}
        </span>
        <a href="{{ route('admin.schedule', ['start_date' => $navNext->format('Y-m-d')]) }}"
           class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 transition" title="{{ __('Next') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <div class="h-6 border-l border-gray-300 dark:border-gray-600 mx-1"></div>

        {{-- View mode --}}
        <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
            @foreach(['weekly' => __('Weekly'), 'daily' => __('Daily'), 'monthly' => __('Monthly')] as $mode => $ml)
                <a href="{{ route('admin.schedule', ['start_date' => $startDate->format('Y-m-d'), 'view_mode' => $mode, 'line_id' => request('line_id')]) }}"
                   class="px-3 py-1 text-xs font-medium rounded-md transition {{ $viewMode === $mode ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $ml }}
                </a>
            @endforeach
        </div>

        <div class="h-6 border-l border-gray-300 dark:border-gray-600 mx-1"></div>

        {{-- Line filter --}}
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

        {{-- Today --}}
        <a href="{{ route('admin.schedule') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition">
            {{ __('Today') }}
        </a>
    </div>

    {{-- ===== SHIFT LEGEND ===== --}}
    <div class="flex items-center gap-4 mb-4 px-1 text-xs text-gray-600 dark:text-gray-400">
        @for($s = 1; $s <= $shiftsPerDay; $s++)
            <span class="inline-flex items-center gap-1.5">
                <span class="w-4 h-3 rounded-sm {{ $shiftColors[$s]['bg'] }}"></span>
                {{ $shiftColors[$s]['label'] }}
                @if($s === 1 && $shiftsPerDay >= 3) {{ __('noc') }}
                @elseif($s === 2 && $shiftsPerDay >= 3) {{ __('rano') }}
                @elseif($s === 3) {{ __('popoł.') }}
                @elseif($s === 4) {{ __('wiecz.') }}
                @elseif($shiftsPerDay === 1) {{ __('dzienna') }}
                @elseif($shiftsPerDay === 2 && $s === 1) {{ __('rano') }}
                @elseif($shiftsPerDay === 2 && $s === 2) {{ __('popoł.') }}
                @endif
            </span>
        @endfor
        <span class="inline-flex items-center gap-1.5">
            <span class="w-4 h-3 rounded-sm bg-pink-300 border border-pink-400"></span>
            {{ __('overload / alert') }}
        </span>
        <span class="flex-1"></span>
        <span class="text-gray-400">{{ __('grid') }}: 1 {{ __('week') }} &middot; {{ __('shift planning') }}</span>
    </div>

    {{-- ===== WEEKLY VIEW ===== --}}
    @if($viewMode === 'weekly')
        <div class="space-y-4">
        @foreach($data as $period)
            @php
                $isOverloaded = $period['total_load_percent'] > 100;
                $isCurrentWeek = now()->isoWeek() === $period['number'] && now()->isoWeekYear() === $period['start']->isoWeekYear();
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-xl border-2 overflow-hidden shadow-sm
                        {{ $isOverloaded ? 'border-red-400 bg-red-50/30' : ($isCurrentWeek ? 'border-blue-400' : 'border-gray-200 dark:border-gray-700') }}">

                <div class="flex min-h-[160px]">
                    {{-- LEFT: Week info --}}
                    <div class="w-44 shrink-0 p-4 border-r border-gray-200 dark:border-gray-700 flex flex-col justify-between bg-gray-50/50 dark:bg-gray-800/80">
                        <div>
                            <div class="text-3xl font-black italic text-gray-800 dark:text-gray-100 leading-tight">
                                {{ __('wk') }}. {{ $period['number'] }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $period['start']->format('d.m') }}&ndash;{{ $period['end']->format('d.m') }}
                            </div>
                        </div>

                        {{-- Shift preview blocks --}}
                        <div class="mt-3">
                            <div class="text-xs text-gray-400 mb-1">{{ __('shifts') }}</div>
                            <div class="flex gap-0.5">
                                @for($s = 1; $s <= $shiftsPerDay; $s++)
                                    <div class="h-4 flex-1 rounded-sm {{ $shiftColors[$s]['bg'] }}"></div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    {{-- CENTER: Gantt shift grid --}}
                    <div class="flex-1 p-3 overflow-x-auto">
                        {{-- Day headers --}}
                        <div class="flex mb-1.5" style="padding-left: 80px;">
                            @php
                                $dayCursor = $period['start']->copy();
                            @endphp
                            @for($d = 0; $d < $daysInWeek; $d++)
                                <div class="flex-1 text-center text-[10px] font-medium text-gray-400 uppercase min-w-[{{ $shiftsPerDay * 16 }}px]">
                                    {{ $dayCursor->translatedFormat('D') }}
                                </div>
                                @php $dayCursor->addDay(); @endphp
                            @endfor
                        </div>

                        {{-- Line rows with shift cells --}}
                        @foreach($period['lines'] as $lineData)
                            @php
                                $line = $lineData['line'];
                                $orders = $lineData['orders'];
                                $lineLoad = $lineData['load_percent'];
                            @endphp
                            <div class="flex items-center mb-1 group">
                                {{-- Line label --}}
                                <div class="w-20 shrink-0 text-xs font-medium text-gray-600 dark:text-gray-300 truncate pr-2" title="{{ $line->name }}">
                                    {{ $line->code ?? $line->name }}
                                </div>
                                {{-- Shift grid cells --}}
                                <div class="flex-1 flex gap-px min-w-0">
                                    @for($d = 0; $d < $daysInWeek; $d++)
                                        @for($s = 1; $s <= $shiftsPerDay; $s++)
                                            @php
                                                // Check if any order occupies this slot
                                                $slotIndex = $d * $shiftsPerDay + ($s - 1);
                                                $slotOrder = $orders[$slotIndex] ?? null;
                                                $isOccupied = $slotOrder !== null;
                                            @endphp
                                            @if($isOccupied)
                                                <a href="{{ route('admin.work-orders.show', $slotOrder) }}"
                                                   class="h-5 flex-1 rounded-sm border cursor-pointer hover:opacity-80 transition {{ $woColors[$slotOrder->status] ?? 'bg-gray-200 border-gray-300' }}"
                                                   title="{{ $slotOrder->order_no }}"
                                                   x-on:mouseenter="showTip($event, {
                                                       order_no: '{{ addslashes($slotOrder->order_no) }}',
                                                       product: '{{ addslashes($slotOrder->productType?->name ?? '-') }}',
                                                       qty: '{{ $slotOrder->planned_qty }}',
                                                       status: '{{ __(['PENDING'=>'Pending','ACCEPTED'=>'Accepted','IN_PROGRESS'=>'In Progress','BLOCKED'=>'Blocked','PAUSED'=>'Paused','DONE'=>'Done'][$slotOrder->status] ?? $slotOrder->status) }}'
                                                   })"
                                                   x-on:mouseleave="hideTip()">
                                                </a>
                                            @else
                                                <div class="h-5 flex-1 rounded-sm {{ $shiftColors[$s]['bg'] }} opacity-30"></div>
                                            @endif
                                        @endfor
                                        @if($d < $daysInWeek - 1)
                                            <div class="w-px bg-gray-200 dark:bg-gray-600"></div>
                                        @endif
                                    @endfor
                                </div>
                            </div>
                        @endforeach

                        {{-- Order pills below grid --}}
                        <div class="flex flex-wrap gap-1.5 mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                            @foreach($period['lines'] as $lineData)
                                @foreach($lineData['orders'] as $wo)
                                    <a href="{{ route('admin.work-orders.show', $wo) }}"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-medium hover:shadow transition
                                              {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? 'text-gray-800' }}">
                                        {{ $wo->order_no }}
                                        <span class="opacity-60">&middot;</span>
                                        <span class="opacity-70">{{ Str::limit($wo->productType?->name ?? '-', 8) }}</span>
                                        <span class="opacity-60">&middot;</span>
                                        <span class="opacity-70">{{ $wo->line?->code ?? 'L?' }}</span>
                                        @if($wo->due_date && $wo->due_date->isPast() && !in_array($wo->status, ['DONE','CANCELLED']))
                                            <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"/></svg>
                                        @endif
                                    </a>
                                @endforeach
                            @endforeach
                            @if(collect($period['lines'])->sum(fn($l) => $l['orders']->count()) === 0)
                                <span class="text-xs text-gray-400 italic">{{ __('No orders') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- RIGHT: Stats --}}
                    <div class="w-40 shrink-0 p-4 border-l border-gray-200 dark:border-gray-700 flex flex-col justify-between text-right bg-gray-50/50 dark:bg-gray-800/80">
                        <div class="space-y-2">
                            <div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wider">{{ __('orders') }}</div>
                                <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $period['total_orders'] }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wider">{{ __('load') }}</div>
                                <div class="text-xl font-black
                                    @if($period['total_load_percent'] > 100) text-red-600
                                    @elseif($period['total_load_percent'] > 80) text-orange-600
                                    @elseif($period['total_load_percent'] > 50) text-amber-600
                                    @else text-green-600
                                    @endif">
                                    {{ $period['total_load_percent'] }}%
                                </div>
                            </div>
                            <div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wider">{{ __('free slots') }}</div>
                                <div class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $period['free_slots_percent'] }}%</div>
                            </div>
                        </div>

                        {{-- Capacity bar --}}
                        <div class="mt-3">
                            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all
                                    @if($period['total_load_percent'] > 100) bg-red-500
                                    @elseif($period['total_load_percent'] > 80) bg-orange-500
                                    @elseif($period['total_load_percent'] > 50) bg-amber-500
                                    @else bg-green-500
                                    @endif"
                                     style="width: {{ min($period['total_load_percent'], 100) }}%"></div>
                            </div>
                        </div>

                        @if($isOverloaded)
                            <div class="mt-2 text-[10px] text-red-600 font-medium leading-tight">
                                ⚠ {{ __('overload') }} &mdash; {{ __('redistribute to next week') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        </div>

    {{-- ===== DAILY VIEW (Timeline Gantt) ===== --}}
    @elseif($viewMode === 'daily')
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-x-auto">
            <table class="w-full border-collapse" style="min-width: {{ count($data) * 80 + 120 }}px;">
                {{-- Date headers --}}
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-800 w-28 p-2 text-xs font-semibold text-gray-500 text-left border-r border-gray-200 dark:border-gray-700">
                            {{ __('Production line') }}
                        </th>
                        @foreach($data as $day)
                            @php $isToday = $day['date']->isToday(); @endphp
                            <th class="p-1.5 text-center min-w-[70px] border-r border-gray-100 dark:border-gray-700
                                       {{ $isToday ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}
                                       {{ $day['date']->isWeekend() ? 'bg-gray-50 dark:bg-gray-800/60' : '' }}">
                                <div class="text-[10px] text-gray-400 uppercase">{{ $day['date']->translatedFormat('D') }}</div>
                                <div class="text-xs font-bold {{ $isToday ? 'text-blue-600' : 'text-gray-700 dark:text-gray-200' }}">{{ $day['date']->format('d.m') }}</div>
                                {{-- Shift sub-columns --}}
                                <div class="flex gap-px mt-1 justify-center">
                                    @for($s = 1; $s <= $shiftsPerDay; $s++)
                                        <div class="w-3 h-2 rounded-sm {{ $shiftColors[$s]['bg'] }} opacity-50"></div>
                                    @endfor
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
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
                                           {{ $isToday ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}
                                           {{ $day['date']->isWeekend() ? 'bg-gray-50/50 dark:bg-gray-800/40' : '' }}">
                                    <div class="flex flex-col gap-0.5">
                                        @foreach($dayOrders as $wo)
                                            <a href="{{ route('admin.work-orders.show', $wo) }}"
                                               class="block px-1 py-0.5 rounded text-[9px] font-medium truncate border cursor-pointer hover:opacity-80
                                                      {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? 'text-gray-800' }}"
                                               title="{{ $wo->order_no }} - {{ $wo->productType?->name ?? '' }} - {{ $wo->planned_qty }} {{ __('pcs') }}"
                                               x-on:mouseenter="showTip($event, {
                                                   order_no: '{{ addslashes($wo->order_no) }}',
                                                   product: '{{ addslashes($wo->productType?->name ?? '-') }}',
                                                   qty: '{{ $wo->planned_qty }}',
                                                   status: '{{ $wo->status }}'
                                               })"
                                               x-on:mouseleave="hideTip()">
                                                {{ $wo->order_no }}
                                            </a>
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
                        <span>{{ __('free slots') }}: <strong>{{ $period['free_slots_percent'] }}%</strong></span>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @foreach($period['lines'] as $lineData)
                        <div class="flex items-center px-4 py-2.5 hover:bg-gray-50/50">
                            <div class="w-32 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $lineData['line']->name }}</div>
                            <div class="flex-1 flex flex-wrap gap-1.5">
                                @foreach($lineData['orders'] as $wo)
                                    <a href="{{ route('admin.work-orders.show', $wo) }}"
                                       class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-medium
                                              {{ $woColors[$wo->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$wo->status] ?? 'text-gray-800' }}">
                                        {{ $wo->order_no }}
                                        <span class="opacity-50">&middot;</span>
                                        {{ $wo->planned_qty }}{{ __('pcs') }}
                                    </a>
                                @endforeach
                                @if($lineData['orders']->isEmpty())
                                    <span class="text-xs text-gray-400 italic">{{ __('No orders') }}</span>
                                @endif
                            </div>
                            <div class="w-20 text-right text-sm font-bold
                                @if($lineData['load_percent'] > 80) text-red-600
                                @else text-green-600
                                @endif">
                                {{ $lineData['load_percent'] }}%
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        </div>
    @endif

    {{-- ===== TOOLTIP ===== --}}
    <div x-show="tooltip" x-transition.opacity.duration.100ms x-cloak
         class="fixed z-50 bg-gray-900 text-white rounded-lg shadow-xl px-3 py-2 text-xs pointer-events-none max-w-xs"
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
