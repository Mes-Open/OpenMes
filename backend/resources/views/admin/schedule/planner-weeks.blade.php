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
    $statusLabels = [
        'PENDING'     => __('Pending'),
        'ACCEPTED'    => __('Accepted'),
        'IN_PROGRESS' => __('In Progress'),
        'BLOCKED'     => __('Blocked'),
        'PAUSED'      => __('Paused'),
        'DONE'        => __('Done'),
    ];
    $daysInWeek = $showWeekends ? 7 : 5;
@endphp

@foreach($data as $idx => $period)
    @php
        $isOverloaded = $period['total_load_percent'] > 100;
        $isCurrentWeek = now()->isoWeek() === $period['number'] && now()->isoWeekYear() === $period['start']->isoWeekYear();
        $prevYear = $idx > 0 ? $data[$idx - 1]['start']->year : null;
        $thisYear = $period['start']->year;
    @endphp

    @if($prevYear !== null && $thisYear !== $prevYear)
        {{-- Year separator --}}
        <div class="flex items-center gap-4 py-3">
            <div class="flex-1 border-t-2 border-gray-300 dark:border-gray-500"></div>
            <span class="text-lg font-black text-gray-500 dark:text-gray-400 tracking-wider">{{ $thisYear }}</span>
            <div class="flex-1 border-t-2 border-gray-300 dark:border-gray-500"></div>
        </div>
    @endif

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
                            $spans = $lineData['spans'] ?? [];
                            $lineLoad = $lineData['load_percent'];
                        @endphp

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

                        @for($s = 1; $s <= $shiftsPerDay; $s++)
                            <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50/30">
                                <td class="px-2 py-2 text-[10px] font-medium border-r border-gray-100 dark:border-gray-700 whitespace-nowrap">
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
                                    @php
                                        $cellId = "cell-{$line->id}-{$d}-{$s}-{$period['number']}";
                                        $spanInfo = $spans[$gridKey] ?? null;
                                        $isCont = $spanInfo && $spanInfo['type'] === 'cont';
                                    @endphp
                                    @if($isCont)
                                        {{-- Continuation cell: covered by rowspan, do NOT output <td> --}}
                                    @else
                                    @php
                                        $rowspan = ($spanInfo && in_array($spanInfo['type'], ['start', 'day-start'])) ? $spanInfo['rowspan'] : 1;
                                        // Check if this order continues to next day (remove right border)
                                        $spanType = $spanInfo['type'] ?? 'single';
                                        $isWo = $slotOrder && is_object($slotOrder);
                                        $isSpanStart = $spanType === 'start' && $isWo && $slotOrder->end_date && $slotOrder->end_date->format('Y-m-d') > $cellDate;
                                        $isDayStart = $spanType === 'day-start';
                                        // Check if this day-start continues to yet another day
                                        $isDayStartContinues = $isDayStart && $isWo && $slotOrder->end_date && $slotOrder->end_date->format('Y-m-d') > $cellDate;
                                    @endphp
                                    @php
                                        $isMultiDay = $isSpanStart || $isDayStart;
                                        $tdPadding = $isMultiDay ? 'p-0' : 'p-0.5';
                                        $tdBorderR = ($isSpanStart || $isDayStartContinues) ? 'border-r-0' : 'border-r border-gray-50 dark:border-gray-700/30';
                                        $tdBorderL = $isDayStart ? 'border-l-0' : '';
                                    @endphp
                                    <td class="{{ $tdPadding }} relative {{ $tdBorderR }} {{ $tdBorderL }}
                                               {{ $isToday ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}
                                               {{ $dayCursor2->isWeekend() ? 'bg-gray-50/30' : '' }} transition-colors"
                                        :class="isSelectedCell({{ $line->id }}, '{{ $cellDate }}', {{ $s }}) ? 'ring-2 ring-inset ring-blue-500 bg-blue-100 dark:bg-blue-900/40' : ''"
                                        @if($rowspan > 1) rowspan="{{ $rowspan }}" style="height: 1px; padding: 0;" @endif
                                        data-cell-line="{{ $line->id }}" data-cell-date="{{ $cellDate }}" data-cell-shift="{{ $s }}">
                                        @if($slotOrder && $slotOrder !== '__span__')
                                            @php
                                                $isOverdue = $slotOrder->due_date
                                                    && $slotOrder->due_date->lt(today())
                                                    && !in_array($slotOrder->status, \App\Models\WorkOrder::TERMINAL_STATUSES);
                                                // Rounding: remove corners on connecting sides
                                                $roundClass = 'rounded';
                                                if ($isSpanStart) $roundClass = 'rounded-l rounded-r-none';
                                                elseif ($isDayStart && !$isDayStartContinues) $roundClass = 'rounded-r rounded-l-none';
                                                elseif ($isDayStartContinues) $roundClass = 'rounded-none';
                                            @endphp
                                            <div class="relative group/cell h-full"
                                                 :class="selectedOrderId === {{ $slotOrder->id }} ? 'ring-2 ring-blue-600 rounded z-10' : ''"
                                                 data-order-id="{{ $slotOrder->id }}" data-order-no="{{ $slotOrder->order_no }}"
                                                 data-span-cells="{{ $rowspan }}"
                                                 draggable="true"
                                                 @dragstart="onDragStart($event, {{ $slotOrder->id }}, '{{ addslashes($slotOrder->order_no) }}')"
                                                 @dragend="onDragEnd($event)"
                                                 @dragover.prevent="onDragOver($event, '{{ $cellId }}')"
                                                 @dragleave="onDragLeave($event, '{{ $cellId }}')"
                                                 @drop="onDrop($event, {{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})">
                                                <a href="{{ route('admin.work-orders.show', $slotOrder) }}"
                                                   class="block px-2 py-4 {{ $roundClass }} text-[11px] font-medium truncate cursor-pointer hover:opacity-80 transition h-full flex items-center
                                                          @if($isOverdue) bg-red-500 text-white animate-pulse ring-2 ring-red-400 @else {{ $woColors[$slotOrder->status] ?? 'bg-gray-200 border-gray-300' }} {{ $woTextColors[$slotOrder->status] ?? 'text-gray-700' }} @endif
                                                          {{ $isSpanStart ? 'border-2 border-r-0' : ($isDayStartContinues ? 'border-2 border-l-0 border-r-0' : ($isDayStart ? 'border-2 border-l-0' : 'border-2')) }}"
                                                   @click.prevent="selectOrder({{ $slotOrder->id }}, '{{ addslashes($slotOrder->order_no) }}', {{ $line->id }}, '{{ $slotOrder->due_date?->format('Y-m-d') ?? '' }}', '{{ $slotOrder->shift_number ?? '' }}', '{{ $slotOrder->end_date?->format('Y-m-d') ?? '' }}', '{{ $slotOrder->end_shift_number ?? '' }}', '{{ route('admin.work-orders.show', $slotOrder) }}')"
                                                   x-on:mouseenter="showTip($event, {
                                                       order_no: '{{ addslashes($slotOrder->order_no) }}',
                                                       product: '{{ addslashes($slotOrder->productType?->name ?? '-') }}',
                                                       qty: '{{ $slotOrder->planned_qty }}',
                                                       status: '{{ $statusLabels[$slotOrder->status] ?? $slotOrder->status }}'
                                                   })"
                                                   x-on:mouseleave="hideTip()">
                                                    {{ $slotOrder->order_no }}
                                                </a>
                                                {{-- Resize handle (bottom edge) --}}
                                                <div class="absolute -bottom-3 left-0 w-full h-7 cursor-s-resize opacity-30 group-hover/cell:opacity-100 transition-opacity z-20 flex justify-center"
                                                     @mousedown.prevent="startResize($event, {{ $slotOrder->id }}, '{{ addslashes($slotOrder->order_no) }}', '{{ $slotOrder->due_date?->format('Y-m-d') ?? $cellDate }}', {{ $slotOrder->shift_number ?? $s }}, {{ $line->id }}, {{ $period['number'] }}, '{{ $slotOrder->end_date?->format('Y-m-d') ?? '' }}', {{ $slotOrder->end_shift_number ?? 0 }})">
                                                    <div class="h-1.5 w-8 bg-gray-600 rounded-full my-auto shadow"></div>
                                                </div>
                                                <button @click.prevent="unassignOrder({{ $slotOrder->id }})"
                                                        class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[8px] font-bold leading-none flex items-center justify-center
                                                               opacity-0 group-hover/cell:opacity-100 transition-opacity shadow-sm hover:bg-red-600 z-10"
                                                        title="{{ __('Remove from schedule') }}">
                                                    ✕
                                                </button>
                                            </div>
                                        @else
                                            <div @click="openAssign({{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})"
                                                 @dragover.prevent="onDragOver($event, '{{ $cellId }}')"
                                                 @dragleave="onDragLeave($event, '{{ $cellId }}')"
                                                 @drop="onDrop($event, {{ $line->id }}, '{{ $cellDate }}', {{ $s }}, {{ $period['number'] }})"
                                                 data-cell-line="{{ $line->id }}" data-cell-date="{{ $cellDate }}" data-cell-shift="{{ $s }}"
                                                 class="h-[52px] rounded transition-all cursor-pointer relative overflow-hidden"
                                                 :class="dragOverCell === '{{ $cellId }}'
                                                     ? 'bg-blue-200 border-2 border-dashed border-blue-500 scale-[1.02]'
                                                     : '{{ $shiftColors[$s]['bg'] }} opacity-15 hover:opacity-40'">
                                                <span x-show="dragOverCell === '{{ $cellId }}' && dragOrderNo"
                                                      x-text="dragOrderNo"
                                                      class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-blue-700"></span>
                                            </div>
                                        @endif
                                    </td>
                                    @endif
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
