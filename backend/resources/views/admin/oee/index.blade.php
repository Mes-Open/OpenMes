@extends('layouts.app')

@section('title', __('OEE Report'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('OEE'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ __('OEE Report') }}</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ __('Overall Equipment Effectiveness — Availability × Performance × Quality') }}</p>
        </div>
        <a href="{{ route('admin.oee.print.pdf', array_filter(['line_id' => $lineId, 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}"
           class="btn-touch btn-secondary inline-flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
            </svg>
            {{ __('Download PDF') }}
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="card mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Line') }}</label>
                <select name="line_id" class="form-input w-full">
                    <option value="">{{ __('All Lines') }}</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" {{ $lineId == $line->id ? 'selected' : '' }}>{{ $line->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('From') }}</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('To') }}</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input">
            </div>
            <button type="submit" class="btn-touch btn-primary">{{ __('Filter') }}</button>
        </div>
    </form>

    <!-- Summary Cards -->
    @if($summary->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach($lines as $line)
                @php $s = $summary->get($line->id); @endphp
                @if($s)
                    <a href="{{ route('admin.oee.show', $line) }}?date_from={{ $dateFrom }}&date_to={{ $dateTo }}"
                       class="card hover:shadow-lg transition-shadow flex flex-col items-center text-center">
                        <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-1">{{ $line->name }}</h3>
                        <x-oee-gauge :value="$s['avg_oee']" :size="160" :showLabel="true" label="OEE" />
                        <div class="w-full grid grid-cols-3 gap-2 mt-3">
                            <div>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wide leading-tight">{{ __('Availability') }}</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ number_format($s['avg_availability'], 1) }}%</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wide leading-tight">{{ __('Performance') }}</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $s['avg_performance'] ? number_format($s['avg_performance'], 1).'%' : 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] text-gray-500 uppercase tracking-wide leading-tight">{{ __('Quality') }}</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ number_format($s['avg_quality'], 1) }}%</p>
                            </div>
                        </div>
                        <div class="w-full mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 flex justify-around gap-3 text-xs text-gray-500">
                            <span>{{ __('Produced') }}: {{ number_format($s['total_produced']) }}</span>
                            <span>{{ __('Scrap') }}: {{ number_format($s['total_scrap']) }}</span>
                            <span>{{ __('Downtime') }}: {{ $s['total_downtime'] }}min</span>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    <!-- Trend Chart -->
    @if($trend->isNotEmpty())
        @php
            $baseQuery = array_filter([
                'line_id' => $lineId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ], fn($v) => $v !== null && $v !== '');
            $granularities = [
                'day' => __('Daily'),
                'week' => __('Weekly'),
                'month' => __('Monthly'),
            ];
        @endphp
        @php
            // Distinct color per line for the per-line view. Cycled if more than 6 lines.
            $linePalette = ['#2563eb', '#db2777', '#0891b2', '#16a34a', '#ea580c', '#7c3aed'];
            $perLineColored = $trendByLine->values()->map(function ($line, $i) use ($linePalette) {
                return array_merge($line, ['color' => $linePalette[$i % count($linePalette)]]);
            });
        @endphp
        <div class="card mb-6" x-data="{ mode: '{{ $lineId ? 'per_line' : 'combined' }}' }">
            <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ __('OEE Trend') }}</h2>
                <div class="flex gap-2 flex-wrap">
                    @if($trendByLine->count() > 1)
                        <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                            <button type="button" @click="mode = 'combined'"
                                    :class="mode === 'combined' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600'"
                                    class="px-3 py-1 text-sm">{{ __('Combined') }}</button>
                            <button type="button" @click="mode = 'per_line'"
                                    :class="mode === 'per_line' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600'"
                                    class="px-3 py-1 text-sm">{{ __('Per line') }}</button>
                        </div>
                    @endif
                    <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                        @foreach($granularities as $key => $label)
                            <a href="?{{ http_build_query(array_merge($baseQuery, ['granularity' => $key])) }}"
                               class="px-3 py-1 text-sm {{ $granularity === $key ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Combined view (default) -->
            <div x-show="mode === 'combined'" class="h-48 flex items-end gap-1"
                 x-data="{ trend: {{ $trend->toJson() }}, granularity: '{{ $granularity }}' }">
                <template x-for="(day, i) in trend" :key="i">
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs font-bold" :class="{
                            'text-green-600': day.oee >= 85,
                            'text-yellow-600': day.oee >= 65 && day.oee < 85,
                            'text-red-600': day.oee < 65
                        }" x-text="day.oee + '%'"></span>
                        <div class="w-full rounded-t transition-all"
                             :style="'height: ' + (day.oee * 1.5) + 'px'"
                             :class="{
                                 'bg-green-500': day.oee >= 85,
                                 'bg-yellow-500': day.oee >= 65 && day.oee < 85,
                                 'bg-red-500': day.oee < 65
                             }"></div>
                        <span class="text-[10px] text-gray-400 whitespace-nowrap"
                              :class="granularity === 'day' ? '-rotate-45 origin-top-left' : ''"
                              x-text="granularity === 'day' ? day.date.substring(5) : day.date"></span>
                    </div>
                </template>
            </div>

            <!-- Per-line view (grouped bars) -->
            <div x-show="mode === 'per_line'" x-cloak class="h-48 flex items-end gap-3"
                 x-data="{ perLine: {{ $perLineColored->toJson() }}, granularity: '{{ $granularity }}' }">
                <template x-for="(bucket, b) in (perLine[0]?.points || [])" :key="b">
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="flex items-end gap-px h-44 w-full justify-center">
                            <template x-for="line in perLine" :key="line.line_id">
                                <div class="flex flex-col items-center justify-end" style="width: 18px;"
                                     :title="line.line_name + ': ' + line.points[b].oee + '%'">
                                    <span class="text-[9px] font-semibold" x-text="line.points[b].oee + '%'"
                                          :style="'color: ' + line.color"></span>
                                    <div class="rounded-t transition-all"
                                         :style="'background: ' + line.color + '; height: ' + (line.points[b].oee * 1.4) + 'px; width: 100%;'"></div>
                                </div>
                            </template>
                        </div>
                        <span class="text-[10px] text-gray-400 whitespace-nowrap"
                              :class="granularity === 'day' ? '-rotate-45 origin-top-left' : ''"
                              x-text="granularity === 'day' ? bucket.date.substring(5) : bucket.date"></span>
                    </div>
                </template>
            </div>

            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500 flex-wrap">
                <div x-show="mode === 'combined'" class="flex items-center gap-4">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-500 rounded"></span> ≥ 85% ({{ __('World-class') }})</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-yellow-500 rounded"></span> 65-84%</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded"></span> &lt; 65%</span>
                </div>
                <div x-show="mode === 'per_line'" x-cloak class="flex items-center gap-4 flex-wrap">
                    @foreach($perLineColored as $l)
                        <span class="flex items-center gap-1">
                            <span class="w-3 h-3 rounded" style="background: {{ $l['color'] }};"></span>
                            {{ $l['line_name'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Detail Table -->
    @if($records->isNotEmpty())
        <div class="card overflow-hidden">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ __('Daily Records') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Line') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Shift') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">A%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">P%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">OEE%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Produced') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Scrap') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Downtime') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($records as $record)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $record->record_date->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $record->line->name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $record->shift?->name ?? __('All') }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->availability_pct !== null ? number_format($record->availability_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->performance_pct !== null ? number_format($record->performance_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->quality_pct !== null ? number_format($record->quality_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ \App\Support\OeeBand::textClass($record->oee_pct) }}">
                                    {{ $record->oee_pct !== null ? number_format($record->oee_pct, 1).'%' : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($record->total_produced) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-red-600">{{ $record->scrap_qty > 0 ? number_format($record->scrap_qty) : '-' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->downtime_minutes }}min</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card text-center py-12">
            <p class="text-gray-500 text-lg mb-2">{{ __('No OEE data available') }}</p>
            <p class="text-sm text-gray-400">{{ __('OEE data will appear once production batches are completed and downtimes are reported.') }}</p>
        </div>
    @endif
</div>
@endsection
