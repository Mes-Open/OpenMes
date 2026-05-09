@extends('layouts.app')

@section('title', 'OEE Report')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'OEE', 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">OEE Report</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Overall Equipment Effectiveness — Availability × Performance × Quality</p>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card mb-6">
        <div class="flex flex-wrap gap-4 items-end">
            <div class="w-48">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Line</label>
                <select name="line_id" class="form-input w-full">
                    <option value="">All Lines</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" {{ $lineId == $line->id ? 'selected' : '' }}>{{ $line->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input">
            </div>
            <button type="submit" class="btn-touch btn-primary">Filter</button>
        </div>
    </form>

    <!-- Summary Cards -->
    @if($summary->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @foreach($lines as $line)
                @php $s = $summary->get($line->id); @endphp
                @if($s)
                    <a href="{{ route('admin.oee.show', $line) }}?date_from={{ $dateFrom }}&date_to={{ $dateTo }}"
                       class="card hover:shadow-lg transition-shadow">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="font-bold text-gray-800 dark:text-gray-100">{{ $line->name }}</h3>
                            @php $oeeColor = $s['avg_oee'] >= 85 ? 'green' : ($s['avg_oee'] >= 60 ? 'yellow' : 'red'); @endphp
                            <span class="text-2xl font-bold text-{{ $oeeColor }}-600">
                                {{ number_format($s['avg_oee'], 1) }}%
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Availability</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ number_format($s['avg_availability'], 1) }}%</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Performance</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $s['avg_performance'] ? number_format($s['avg_performance'], 1).'%' : 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase">Quality</p>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ number_format($s['avg_quality'], 1) }}%</p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600 flex gap-4 text-xs text-gray-500">
                            <span>Produced: {{ number_format($s['total_produced']) }}</span>
                            <span>Scrap: {{ number_format($s['total_scrap']) }}</span>
                            <span>Downtime: {{ $s['total_downtime'] }}min</span>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    <!-- Trend Chart -->
    @if($trend->isNotEmpty())
        <div class="card mb-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">OEE Trend</h2>
            <div class="h-48 flex items-end gap-1" x-data="{ trend: {{ $trend->toJson() }} }">
                <template x-for="(day, i) in trend" :key="i">
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-xs font-bold" :class="{
                            'text-green-600': day.oee >= 85,
                            'text-yellow-600': day.oee >= 60 && day.oee < 85,
                            'text-red-600': day.oee < 60
                        }" x-text="day.oee + '%'"></span>
                        <div class="w-full rounded-t transition-all"
                             :style="'height: ' + (day.oee * 1.5) + 'px'"
                             :class="{
                                 'bg-green-500': day.oee >= 85,
                                 'bg-yellow-500': day.oee >= 60 && day.oee < 85,
                                 'bg-red-500': day.oee < 60
                             }"></div>
                        <span class="text-[10px] text-gray-400 -rotate-45 origin-top-left whitespace-nowrap"
                              x-text="day.date.substring(5)"></span>
                    </div>
                </template>
            </div>
            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-500 rounded"></span> ≥ 85% (World-class)</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-yellow-500 rounded"></span> 60-84%</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded"></span> &lt; 60%</span>
            </div>
        </div>
    @endif

    <!-- Detail Table -->
    @if($records->isNotEmpty())
        <div class="card overflow-hidden">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Daily Records</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Line</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">A%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">P%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">OEE%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Produced</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Scrap</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Downtime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($records as $record)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $record->record_date->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $record->line->name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $record->shift?->name ?? 'All' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->availability_pct !== null ? number_format($record->availability_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->performance_pct !== null ? number_format($record->performance_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->quality_pct !== null ? number_format($record->quality_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $record->oee_pct >= 85 ? 'text-green-600' : ($record->oee_pct >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
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
            <p class="text-gray-500 text-lg mb-2">No OEE data available</p>
            <p class="text-sm text-gray-400">OEE data will appear once production batches are completed and downtimes are reported.</p>
        </div>
    @endif
</div>
@endsection
