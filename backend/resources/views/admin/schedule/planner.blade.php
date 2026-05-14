@extends('layouts.app')

@section('title', __('Production Planner'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Schedule'), 'url' => route('admin.schedule')],
    ['label' => __('Planner'), 'url' => null],
]" />

<div x-data="{
    viewMode: '{{ $viewMode }}',
    tooltipOrder: null,
    tooltipX: 0,
    tooltipY: 0,
    showTooltip(e, order) {
        this.tooltipOrder = order;
        const rect = e.target.getBoundingClientRect();
        this.tooltipX = rect.left + window.scrollX;
        this.tooltipY = rect.bottom + window.scrollY + 6;
    },
    hideTooltip() { this.tooltipOrder = null; }
}">

    {{-- ===== TOP TOOLBAR ===== --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-3 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">

            {{-- Navigation --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.schedule.planner', ['start_date' => $navPrev->format('Y-m-d')]) }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                   title="{{ __('Previous') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>

                <div class="text-sm font-semibold text-gray-700 dark:text-gray-200 px-2 whitespace-nowrap">
                    {{ $horizonWeeks }} {{ trans_choice('week|weeks', $horizonWeeks) }}
                    <span class="text-gray-400 dark:text-gray-500 font-normal mx-1">&middot;</span>
                    {{ $rangeStart->format('d.m') }} &ndash; {{ $rangeEnd->format('d.m.Y') }}
                </div>

                <a href="{{ route('admin.schedule.planner', ['start_date' => $navNext->format('Y-m-d')]) }}"
                   class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                   title="{{ __('Next') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>

                <a href="{{ route('admin.schedule.planner') }}"
                   class="ml-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                    {{ __('Today') }}
                </a>
            </div>

            {{-- View mode switcher --}}
            <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                @foreach(['weekly' => __('Weekly'), 'daily' => __('Daily'), 'monthly' => __('Monthly')] as $mode => $modeLabel)
                    <a href="{{ route('admin.schedule.planner', ['start_date' => $startDate->format('Y-m-d'), 'view_mode' => $mode]) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-md transition
                              {{ $viewMode === $mode
                                  ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                        {{ $modeLabel }}
                    </a>
                @endforeach
            </div>

            {{-- Line filter --}}
            <form method="GET" action="{{ route('admin.schedule.planner') }}" class="flex items-center gap-2">
                <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                <select name="line_id" onchange="this.form.submit()"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg py-1.5 px-3 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">{{ __('All Lines') }}</option>
                    @foreach($allLines as $line)
                        <option value="{{ $line->id }}" {{ request('line_id') == $line->id ? 'selected' : '' }}>
                            {{ $line->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- ===== LEGEND ===== --}}
    <div class="flex flex-wrap items-center gap-3 mb-4 text-xs">
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">{{ __('Pending') }}</span>
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-blue-200 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">{{ __('Accepted') }}</span>
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-amber-200 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">{{ __('In Progress') }}</span>
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-red-200 dark:bg-red-900/40 text-red-700 dark:text-red-300">{{ __('Blocked') }}</span>
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-orange-200 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300">{{ __('Paused') }}</span>
        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-green-200 dark:bg-green-900/40 text-green-700 dark:text-green-300">{{ __('Done') }}</span>
    </div>

    {{-- ===== MAIN CONTENT ===== --}}
    @if(empty($data))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col items-center py-16 text-center">
            <svg class="w-14 h-14 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-gray-500 dark:text-gray-400">{{ __('No orders in this period.') }}</p>
        </div>
    @else
        <div class="space-y-4 overflow-x-auto pb-4">
            @foreach($data as $period)
                @php
                    $periodLabel = $period['label'] ?? '';
                    $dateRange = $period['date_range'] ?? ($period['start']->format('d.m') . ' - ' . $period['end']->format('d.m'));
                    $totalOrders = $period['total_orders'] ?? 0;
                    $totalLoad = $period['total_load_percent'] ?? 0;
                    $freeSlots = $period['free_slots_percent'] ?? 0;
                @endphp

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Period header --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-4">
                            <div>
                                <span class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $periodLabel }}</span>
                                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ $dateRange }}</span>
                            </div>
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $shiftsPerDay }} {{ trans_choice('shift|shifts', $shiftsPerDay) }}/{{ __('day') }}
                            </div>
                        </div>

                        <div class="flex items-center gap-5 text-sm">
                            <div class="text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Orders') }}</div>
                                <div class="font-bold text-gray-800 dark:text-gray-100">{{ $totalOrders }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Load') }}</div>
                                <div class="font-bold @if($totalLoad > 100) text-red-600 dark:text-red-400 @elseif($totalLoad > 80) text-orange-600 dark:text-orange-400 @elseif($totalLoad > 50) text-amber-600 dark:text-amber-400 @else text-green-600 dark:text-green-400 @endif">
                                    {{ $totalLoad }}%
                                    @if($totalLoad > 100)
                                        <svg class="inline w-3.5 h-3.5 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    @endif
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('Free') }}</div>
                                <div class="font-bold text-gray-600 dark:text-gray-300">{{ $freeSlots }}%</div>
                            </div>
                            {{-- Mini progress bar --}}
                            <div class="w-24 hidden sm:block">
                                <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-300 @if($totalLoad > 100) bg-red-500 @elseif($totalLoad > 80) bg-orange-500 @elseif($totalLoad > 50) bg-amber-500 @else bg-green-500 @endif"
                                         style="width: {{ min($totalLoad, 100) }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Lines grid --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @foreach($period['lines'] as $lineData)
                            @php
                                $line = $lineData['line'];
                                $orders = $lineData['orders'];
                                $lineLoad = $lineData['load_percent'];
                                $lineCapacity = $lineData['capacity'];
                                $lineUsed = $lineData['used_slots'];
                            @endphp

                            <div class="flex items-stretch min-h-[3.5rem] hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition">
                                {{-- Line name --}}
                                <div class="flex-shrink-0 w-36 sm:w-44 px-4 py-2.5 flex items-center gap-2 border-r border-gray-100 dark:border-gray-700/50">
                                    <span class="w-2 h-2 rounded-full flex-shrink-0
                                        @if($lineLoad > 100) bg-red-500
                                        @elseif($lineLoad > 80) bg-orange-500
                                        @elseif($lineLoad > 50) bg-amber-500
                                        @else bg-green-500
                                        @endif"></span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate" title="{{ $line->name }}">
                                        {{ $line->name }}
                                    </span>
                                </div>

                                {{-- Order blocks (Gantt area) --}}
                                <div class="flex-1 px-3 py-2 flex items-center gap-1.5 overflow-x-auto">
                                    @forelse($orders as $wo)
                                        @php
                                            $statusClasses = match($wo->status) {
                                                'ACCEPTED' => 'bg-blue-200 dark:bg-blue-800/50 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700',
                                                'IN_PROGRESS' => 'bg-amber-200 dark:bg-amber-800/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-700',
                                                'BLOCKED' => 'bg-red-200 dark:bg-red-800/40 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700',
                                                'PAUSED' => 'bg-orange-200 dark:bg-orange-800/40 text-orange-700 dark:text-orange-300 border-orange-300 dark:border-orange-700',
                                                'DONE' => 'bg-green-200 dark:bg-green-800/40 text-green-700 dark:text-green-300 border-green-300 dark:border-green-700',
                                                default => 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600',
                                            };
                                        @endphp
                                        <a href="{{ route('admin.work-orders.show', $wo) }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium border transition-all hover:shadow-md hover:scale-[1.02] flex-shrink-0 {{ $statusClasses }}"
                                           x-on:mouseenter="showTooltip($event, {
                                               order_no: '{{ addslashes($wo->order_no) }}',
                                               product: '{{ addslashes($wo->productType?->name ?? '-') }}',
                                               qty: '{{ $wo->planned_qty }}',
                                               produced: '{{ $wo->produced_qty ?? 0 }}',
                                               status: '{{ $wo->status }}',
                                               due: '{{ $wo->due_date?->format('d.m.Y') ?? '-' }}',
                                               priority: '{{ $wo->priority ?? '-' }}'
                                           })"
                                           x-on:mouseleave="hideTooltip()">
                                            <span class="max-w-[8rem] truncate">{{ $wo->order_no }}</span>
                                            @if($wo->priority && $wo->priority >= 4)
                                                <svg class="w-3 h-3 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                            @endif
                                        </a>
                                    @empty
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">{{ __('No orders') }}</span>
                                    @endforelse
                                </div>

                                {{-- Line stats --}}
                                <div class="flex-shrink-0 w-32 sm:w-40 px-3 py-2 flex items-center gap-3 border-l border-gray-100 dark:border-gray-700/50">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $lineUsed }}/{{ $lineCapacity }} {{ __('slots') }}
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-300
                                                @if($lineLoad > 100) bg-red-500
                                                @elseif($lineLoad > 80) bg-orange-500
                                                @elseif($lineLoad > 50) bg-amber-500
                                                @else bg-green-500
                                                @endif"
                                                 style="width: {{ min($lineLoad, 100) }}%"></div>
                                        </div>
                                    </div>
                                    <span class="text-xs font-bold whitespace-nowrap
                                        @if($lineLoad > 100) text-red-600 dark:text-red-400
                                        @elseif($lineLoad > 80) text-orange-600 dark:text-orange-400
                                        @elseif($lineLoad > 50) text-amber-600 dark:text-amber-400
                                        @else text-green-600 dark:text-green-400
                                        @endif">
                                        {{ $lineLoad }}%
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ===== TOOLTIP ===== --}}
    <div x-show="tooltipOrder"
         x-transition.opacity.duration.150ms
         x-cloak
         class="fixed z-50 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 px-4 py-3 text-sm pointer-events-none max-w-xs"
         :style="'left:' + tooltipX + 'px; top:' + tooltipY + 'px'">
        <template x-if="tooltipOrder">
            <div>
                <div class="font-bold text-gray-800 dark:text-gray-100 mb-1.5" x-text="tooltipOrder.order_no"></div>
                <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Product') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.product"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Planned Qty') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.qty"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Produced') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.produced"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Status') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.status"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Due Date') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.due"></span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Priority') }}:</span>
                    <span class="text-gray-700 dark:text-gray-200" x-text="tooltipOrder.priority"></span>
                </div>
            </div>
        </template>
    </div>

</div>
@endsection
