@extends('layouts.app')

@section('title', 'Workstation — ' . $line->name)

@section('content')
@php
    $allColumnsJson = json_encode($allColumns);
    $defaultVisible = collect($allColumns)->filter(fn($c) => $c['default'])->pluck('key')->values()->toJson();
@endphp

<div class="max-w-full mx-auto px-2 sm:px-4"
     x-data="workstationView({{ $allColumnsJson }}, {{ $defaultVisible }}, {{ $line->id }})"
     x-init="init()">

    {{-- Header --}}
    <div class="mb-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $line->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                {{-- Mode toggle --}}
                <div class="flex items-center bg-gray-100 dark:bg-gray-800 rounded-lg p-1 gap-1">
                    <a href="{{ route('operator.queue') }}"
                       class="px-3 py-1.5 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 transition-all">
                        Queue
                    </a>
                    <span class="px-3 py-1.5 rounded-md text-sm font-medium bg-white dark:bg-gray-700 shadow text-blue-600 dark:text-blue-400">
                        Workstation
                    </span>
                </div>

                {{-- Column picker --}}
                <div class="relative" x-data="{ colMenu: false }">
                    <button @click="colMenu = !colMenu" type="button"
                            class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                            title="Configure columns">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                    <div x-show="colMenu" @click.away="colMenu = false" x-transition
                         class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-200">Columns</span>
                            <button @click="resetColumns()" type="button" class="text-xs text-blue-500 hover:underline">Reset</button>
                        </div>

                        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1 mt-2">System fields</div>
                        <template x-for="col in allColumns.filter(c => c.source !== 'extra_data')" :key="col.key">
                            <label class="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                <input type="checkbox" :checked="visibleKeys.includes(col.key)"
                                       @change="toggleColumn(col.key)"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300" x-text="col.label"></span>
                            </label>
                        </template>

                        <template x-if="allColumns.filter(c => c.source === 'extra_data').length > 0">
                            <div>
                                <div class="text-xs text-gray-400 uppercase tracking-wider mb-1 mt-3">Import data</div>
                                <template x-for="col in allColumns.filter(c => c.source === 'extra_data')" :key="col.key">
                                    <label class="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                        <input type="checkbox" :checked="visibleKeys.includes(col.key)"
                                               @change="toggleColumn(col.key)"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300" x-text="col.label"></span>
                                        <span class="text-xs text-gray-400 ml-auto" x-text="col.key"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <a href="{{ route('operator.select-line') }}" class="btn-touch btn-secondary text-sm">Change Line</a>
            </div>
        </div>

        {{-- Week filter --}}
        @if($availableWeeks->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="text-sm text-gray-500 dark:text-gray-400">Select week:</span>
            <a href="{{ route('operator.workstation', array_filter(['search' => $search])) }}"
               class="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors
                      {{ !$weekFilter || $weekFilter === 'all' ? 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-200 shadow-sm' : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                All weeks
            </a>
            @foreach($availableWeeks as $wk)
            <a href="{{ route('operator.workstation', array_filter(['week' => $wk, 'search' => $search])) }}"
               class="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors
                      {{ $weekFilter == $wk ? 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 text-blue-700 dark:text-blue-300 shadow-sm' : 'border-transparent text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                W{{ str_pad($wk, 2, '0', STR_PAD_LEFT) }}
            </a>
            @endforeach
        </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex gap-2 mb-3">
            <button type="button" disabled
                    class="px-5 py-2.5 rounded-lg text-sm font-bold bg-yellow-400 text-yellow-900 opacity-60 cursor-not-allowed">
                Cleaning
            </button>
            <button type="button" disabled
                    class="px-5 py-2.5 rounded-lg text-sm font-bold bg-red-500 text-white opacity-60 cursor-not-allowed">
                Failure
            </button>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500 mb-2">
            Click a row to change production status. Use "Z1" or "Z2" columns to enter produced quantities per shift.
        </p>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('operator.workstation') }}" class="mb-4">
        @if($weekFilter)<input type="hidden" name="week" value="{{ $weekFilter }}">@endif
        <input type="text" name="search" value="{{ $search }}"
               placeholder="Search by order number, product or data..."
               class="form-input w-full sm:w-96"
               autocomplete="off">
    </form>

    {{-- Table --}}
    @if($workOrders->isEmpty())
        <div class="card text-center py-16">
            <p class="text-gray-500 text-lg">No work orders found</p>
        </div>
    @else
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse border-2 border-gray-400 dark:border-gray-500">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 border-b-2 border-gray-300 dark:border-gray-600">
                        {{-- Dynamic columns --}}
                        @foreach($allColumns as $col)
                        <th x-show="visibleKeys.includes('{{ $col['key'] }}')"
                            class="px-3 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">
                            {{ $col['label'] }}
                        </th>
                        @endforeach
                        {{-- Fixed quantity columns --}}
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider border-l-2 border-gray-300 dark:border-gray-600">To Produce</th>
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Produced</th>
                        <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider bg-blue-600 text-white">Remaining</th>
                        {{-- Dynamic shift columns (only if shifts configured for this line) --}}
                        @if($shifts->isNotEmpty() && $shifts->contains(fn($s) => $s->line_id === $line->id))
                        @foreach($shifts->where('line_id', $line->id) as $shift)
                        <th class="px-3 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider" title="{{ $shift->name }} ({{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }})">{{ $shift->code }}</th>
                        @endforeach
                        @endif
                        <th class="px-3 py-3 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workOrders as $wo)
                    @php
                        $planned   = (float) $wo->planned_qty;
                        $produced  = (float) $wo->produced_qty;
                        $remaining = max(0, $planned - $produced);
                        $isDone    = $wo->status === 'DONE';
                        $isActive  = $wo->status === 'IN_PROGRESS';
                    @endphp
                    <tr class="border-b-2 border-gray-400 dark:border-gray-500 transition-colors
                               {{ $isDone ? 'bg-green-100 dark:bg-green-900/30 border-l-4 border-l-green-500' : ($isActive ? 'bg-blue-100 dark:bg-blue-900/30 border-l-4 border-l-blue-500' : ($wo->status === 'BLOCKED' ? 'bg-red-50 dark:bg-red-900/20 border-l-4 border-l-red-500' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50 border-l-4 border-l-transparent')) }}
                               {{ !$isDone ? 'cursor-pointer active:bg-gray-100 dark:active:bg-gray-700' : '' }}"
                        @if(!$isDone && !$isActive)
                            @click="startModal = {
                                open: true,
                                id: {{ $wo->id }},
                                orderNo: '{{ addslashes($wo->order_no) }}',
                                product: '{{ addslashes($wo->productType?->name ?? $wo->order_no) }}',
                                qty: {{ $planned }},
                                url: '{{ route('operator.workstation.start', $wo) }}'
                            }"
                        @elseif($isActive)
                            @click="completeModal = {
                                open: true,
                                id: {{ $wo->id }},
                                orderNo: '{{ addslashes($wo->order_no) }}',
                                product: '{{ addslashes($wo->productType?->name ?? $wo->order_no) }}',
                                planned: {{ $planned }},
                                produced: {{ $produced }},
                                url: '{{ route('operator.workstation.complete', $wo) }}'
                            }; producedQty = ''"
                        @endif>

                        {{-- Dynamic columns --}}
                        @foreach($allColumns as $col)
                        <td x-show="visibleKeys.includes('{{ $col['key'] }}')"
                            class="px-3 py-3 text-sm text-gray-800 dark:text-gray-200">
                            @if($col['source'] === 'extra_data')
                                {{ data_get($wo->extra_data, $col['key'], '—') }}
                            @elseif($col['source'] === 'product_type')
                                {{ $wo->productType?->name ?? '—' }}
                            @elseif($col['key'] === 'status')
                                @if($isDone)
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-green-200 text-green-800 dark:bg-green-800 dark:text-green-200">Done</span>
                                @elseif($isActive)
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200 animate-pulse">In Progress</span>
                                @elseif($wo->status === 'BLOCKED')
                                    <span class="px-3 py-1 rounded-full text-sm font-bold bg-red-200 text-red-800">Blocked</span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-sm font-bold bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300">{{ $wo->status === 'PENDING' ? 'Not Started' : ucfirst(strtolower(str_replace('_', ' ', $wo->status))) }}</span>
                                @endif
                            @elseif($col['key'] === 'due_date')
                                {{ $wo->due_date ? $wo->due_date->format('d M') : '—' }}
                            @elseif($col['key'] === 'week_number')
                                {{ $wo->week_number ? 'W' . str_pad($wo->week_number, 2, '0', STR_PAD_LEFT) : '—' }}
                            @else
                                {{ $wo->{$col['key']} ?? '—' }}
                            @endif
                        </td>
                        @endforeach

                        {{-- To Produce --}}
                        <td class="px-3 py-3 text-center font-bold text-gray-800 dark:text-gray-200 border-l-2 border-gray-200 dark:border-gray-600 tabular-nums">
                            {{ number_format($planned, 0) }}
                        </td>

                        {{-- Produced --}}
                        <td class="px-3 py-3 text-center font-semibold text-gray-700 dark:text-gray-300 tabular-nums">
                            {{ number_format($produced, 0) }}
                        </td>

                        {{-- Remaining --}}
                        <td class="px-3 py-3 text-center font-bold tabular-nums
                                   {{ $remaining <= 0 ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-blue-600 text-white' }}">
                            {{ number_format($remaining, 0) }}
                        </td>

                        {{-- Dynamic shift columns (only if shifts configured for this line) --}}
                        @if($shifts->isNotEmpty() && $shifts->contains(fn($s) => $s->line_id === $line->id))
                        @foreach($shifts->where('line_id', $line->id) as $shift)
                        @php
                            $entryKey = $wo->id . '_' . $shift->id;
                            $entryQty = isset($shiftEntries[$entryKey]) ? (float) $shiftEntries[$entryKey]->first()->quantity : 0;
                        @endphp
                        <td class="px-2 py-1 text-center" @click.stop>
                            @if(!$isDone)
                            <form action="{{ route('operator.workstation.shift-entry', $wo) }}" method="POST"
                                  class="inline" onsubmit="return parseInt(this.quantity.value) > 0">
                                @csrf
                                <input type="hidden" name="shift_id" value="{{ $shift->id }}">
                                <input type="number" name="quantity"
                                       value="{{ $entryQty > 0 ? (int) $entryQty : '' }}"
                                       class="w-16 text-center text-sm font-semibold border border-gray-300 dark:border-gray-600 rounded px-1 py-1.5 bg-white dark:bg-gray-800 tabular-nums
                                              {{ $entryQty > 0 ? 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-900/20' : 'text-gray-800 dark:text-gray-200' }}
                                              focus:ring-2 focus:ring-blue-400"
                                       placeholder="—" min="1" step="1" inputmode="numeric"
                                       onfocus="this.select()"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();if(parseInt(this.value)>0)this.form.submit()}"
                                       onblur="if(parseInt(this.value)>0 && this.value != this.defaultValue)this.form.submit()">
                            </form>
                            @else
                                <span class="text-gray-400">{{ $entryQty > 0 ? (int) $entryQty : 0 }}</span>
                            @endif
                        </td>
                        @endforeach
                        @endif

                        {{-- Action (+) --}}
                        <td class="px-2 py-3 text-center" @click.stop>
                            <div class="flex items-center justify-center gap-1">
                                @if(!$isDone)
                                <button type="button"
                                        @click="completeModal = {
                                            open: true,
                                            id: {{ $wo->id }},
                                            orderNo: '{{ addslashes($wo->order_no) }}',
                                            product: '{{ addslashes($wo->productType?->name ?? $wo->order_no) }}',
                                            planned: {{ $planned }},
                                            produced: {{ $produced }},
                                            url: '{{ route('operator.workstation.complete', $wo) }}'
                                        }; producedQty = ''"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-700 text-white text-lg font-bold shadow transition-colors"
                                        title="Add produced quantity">
                                    +
                                </button>
                                @endif
                                <button type="button"
                                        @click="report = { open: true, woId: {{ $wo->id }}, woNo: '{{ addslashes($wo->order_no) }}', typeId: '', title: '', desc: '' }"
                                        class="w-8 h-8 flex items-center justify-center rounded-full bg-red-500 hover:bg-red-600 text-white text-lg font-bold shadow transition-colors"
                                        title="Report problem">
                                    !
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ═══════ START PRODUCTION MODAL ═══════ --}}
    <div x-show="startModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="startModal.open = false"></div>
        <div class="relative bg-white dark:bg-gray-800 w-full max-w-sm rounded-xl shadow-2xl p-6"
             x-transition @click.stop>
            <h3 class="text-lg font-bold text-green-700 dark:text-green-400 mb-3">Start Production</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-2 text-lg">
                <strong x-text="startModal.product"></strong>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                Order: <span class="font-mono" x-text="startModal.orderNo"></span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                Planned: <strong x-text="startModal.qty"></strong> units
            </p>
            <div class="flex gap-3">
                <button @click="startModal.open = false"
                        class="btn-touch btn-secondary flex-1 py-3 text-base">Cancel</button>
                <form :action="startModal.url" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full btn-touch bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg py-3 text-base">
                        Start
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════ COMPLETE / ADD PRODUCTION MODAL ═══════ --}}
    <div x-show="completeModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="completeModal.open = false"></div>
        <div class="relative bg-white dark:bg-gray-800 w-full max-w-sm rounded-xl shadow-2xl p-6"
             x-transition @click.stop>
            <h3 class="text-lg font-bold text-blue-700 dark:text-blue-400 mb-3">Add Produced Quantity</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-1">
                <strong x-text="completeModal.product"></strong>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                Order: <span class="font-mono" x-text="completeModal.orderNo"></span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Planned: <strong x-text="completeModal.planned"></strong> |
                Already produced: <strong x-text="completeModal.produced"></strong>
            </p>

            <form :action="completeModal.url" method="POST">
                @csrf
                <div class="mb-5">
                    <label class="form-label">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="produced_qty" x-model="producedQty"
                           class="form-input w-full text-3xl font-bold text-center py-4 tabular-nums"
                           placeholder="0" min="0" step="1" required autofocus inputmode="numeric">
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="completeModal.open = false"
                            class="btn-touch btn-secondary flex-1 py-3 text-base">Cancel</button>
                    <button type="submit"
                            :disabled="producedQty === '' || producedQty < 0"
                            class="btn-touch flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg disabled:opacity-40 py-3 text-base">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ REPORT ISSUE MODAL ═══════ --}}
    @if($issueTypes->isNotEmpty())
    <div x-show="report.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="report.open = false"></div>
        <div class="relative bg-white dark:bg-gray-800 w-full max-w-lg rounded-xl shadow-2xl p-6"
             x-transition @click.stop>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Report Issue</h3>
                    <p class="text-sm text-gray-500 font-mono" x-text="report.woNo"></p>
                </div>
                <button @click="report.open = false" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('operator.issue.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="work_order_id" :value="report.woId">

                <div>
                    <label class="form-label">Type <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($issueTypes as $type)
                        <label class="flex items-center gap-2 p-2.5 rounded-lg border-2 cursor-pointer transition-colors"
                               :class="report.typeId == '{{ $type->id }}' ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'">
                            <input type="radio" name="issue_type_id" value="{{ $type->id }}" x-model="report.typeId"
                                   @change="if (!report.title) report.title = '{{ addslashes($type->name) }}'"
                                   class="sr-only" required>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $type->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="form-label">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" x-model="report.title" class="form-input w-full" required maxlength="255">
                </div>

                <div>
                    <label class="form-label">Details <span class="text-gray-400 text-xs">(optional)</span></label>
                    <textarea name="description" x-model="report.desc" rows="3" class="form-input w-full resize-none" maxlength="2000"></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="report.open = false" class="btn-touch btn-secondary flex-1">Cancel</button>
                    <button type="submit" class="btn-touch flex-1 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg"
                            :disabled="!report.typeId || !report.title">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

<script>
function workstationView(allColumns, defaultVisible, lineId) {
    return {
        allColumns: allColumns,
        visibleKeys: [],
        storageKey: 'workstation_cols_' + lineId,
        startModal: { open: false, id: null, orderNo: '', product: '', qty: 0, url: '' },
        completeModal: { open: false, id: null, orderNo: '', product: '', planned: 0, produced: 0, url: '' },
        producedQty: '',
        report: { open: false, woId: null, woNo: '', typeId: '', title: '', desc: '' },

        init() {
            const saved = localStorage.getItem(this.storageKey);
            if (saved) {
                try {
                    this.visibleKeys = JSON.parse(saved);
                } catch (e) {
                    this.visibleKeys = [...defaultVisible];
                }
            } else {
                this.visibleKeys = [...defaultVisible];
            }
        },

        toggleColumn(key) {
            const idx = this.visibleKeys.indexOf(key);
            if (idx >= 0) {
                this.visibleKeys.splice(idx, 1);
            } else {
                this.visibleKeys.push(key);
            }
            localStorage.setItem(this.storageKey, JSON.stringify(this.visibleKeys));
        },

        resetColumns() {
            this.visibleKeys = [...defaultVisible];
            localStorage.removeItem(this.storageKey);
        }
    };
}
</script>
@endsection
