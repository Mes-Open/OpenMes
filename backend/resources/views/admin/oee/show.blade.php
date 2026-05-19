@extends('layouts.app')

@section('title', __('OEE') . ' — ' . $line->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('OEE'), 'url' => route('admin.oee.index')],
    ['label' => $line->name, 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $line->name }} — OEE</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $dateFrom }} {{ __('to') }} {{ $dateTo }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.oee.print.pdf', ['line_id' => $line->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="btn-touch btn-secondary inline-flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                {{ __('Download PDF') }}
            </a>
            <a href="{{ route('admin.oee.index') }}" class="btn-touch btn-secondary">{{ __('Back to OEE') }}</a>
        </div>
    </div>

    <!-- Downtime by Reason -->
    @if(!empty($downtimeByReason))
        <div class="card mb-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ __('Downtime by Reason') }}</h2>
            <div class="space-y-2">
                @php $maxMinutes = collect($downtimeByReason)->max('total_minutes') ?: 1; @endphp
                @foreach($downtimeByReason as $item)
                    @php
                        $barColor = match($item['kind_color']) {
                            'blue'  => 'bg-blue-400',
                            'amber' => 'bg-amber-400',
                            default => 'bg-red-400',
                        };
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-40 shrink-0">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $item['reason'] }}</span>
                            <span class="text-xs text-gray-400 ml-1">({{ $item['kind_label'] }})</span>
                        </div>
                        <div class="flex-1 bg-gray-100 dark:bg-slate-700 rounded-full h-5 overflow-hidden">
                            <div class="h-full rounded-full {{ $barColor }}"
                                 style="width: {{ ($item['total_minutes'] / $maxMinutes) * 100 }}%"></div>
                        </div>
                        <div class="w-24 text-right shrink-0">
                            <span class="text-sm font-mono font-bold text-gray-700 dark:text-gray-300">{{ $item['total_minutes'] }}min</span>
                            <span class="text-xs text-gray-400 ml-1">({{ $item['count'] }}×)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Records Table -->
    @if($records->isNotEmpty())
        <div class="card overflow-hidden">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ __('Daily Records') }}</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Date') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Shift') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Planned') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Operating') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Downtime') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">A%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">P%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">OEE%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Produced') }}</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Scrap') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($records as $record)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $record->record_date->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $record->shift?->name ?? __('All') }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->planned_minutes }}min</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->operating_minutes }}min</td>
                                <td class="px-3 py-2 text-right font-mono text-red-600">{{ $record->downtime_minutes }}min</td>
                                <td class="px-3 py-2 text-right">{{ $record->availability_pct !== null ? number_format($record->availability_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->performance_pct !== null ? number_format($record->performance_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->quality_pct !== null ? number_format($record->quality_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ \App\Support\OeeBand::textClass($record->oee_pct) }}">
                                    {{ $record->oee_pct !== null ? number_format($record->oee_pct, 1).'%' : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($record->total_produced) }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->scrap_qty > 0 ? number_format($record->scrap_qty) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card text-center py-8">
            <p class="text-gray-500">{{ __('No OEE records for this period.') }}</p>
        </div>
    @endif
</div>
@endsection
