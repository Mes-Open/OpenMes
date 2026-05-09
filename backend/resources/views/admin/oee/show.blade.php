@extends('layouts.app')

@section('title', 'OEE — ' . $line->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'OEE', 'url' => route('admin.oee.index')],
    ['label' => $line->name, 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $line->name }} — OEE</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ $dateFrom }} to {{ $dateTo }}</p>
        </div>
        <a href="{{ route('admin.oee.index') }}" class="btn-touch btn-secondary">Back to OEE</a>
    </div>

    <!-- Downtime by Reason -->
    @if(!empty($downtimeByReason))
        <div class="card mb-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Downtime by Reason</h2>
            <div class="space-y-2">
                @php $maxMinutes = collect($downtimeByReason)->max('total_minutes') ?: 1; @endphp
                @foreach($downtimeByReason as $item)
                    <div class="flex items-center gap-3">
                        <div class="w-40 shrink-0">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $item['reason'] }}</span>
                            @if($item['is_planned'])
                                <span class="text-xs text-gray-400 ml-1">(planned)</span>
                            @endif
                        </div>
                        <div class="flex-1 bg-gray-100 dark:bg-slate-700 rounded-full h-5 overflow-hidden">
                            <div class="h-full rounded-full {{ $item['is_planned'] ? 'bg-blue-400' : 'bg-red-400' }}"
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
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">Daily Records</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Planned</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Operating</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Downtime</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">A%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">P%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">OEE%</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Produced</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Scrap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($records as $record)
                            <tr>
                                <td class="px-3 py-2 font-mono">{{ $record->record_date->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $record->shift?->name ?? 'All' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->planned_minutes }}min</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $record->operating_minutes }}min</td>
                                <td class="px-3 py-2 text-right font-mono text-red-600">{{ $record->downtime_minutes }}min</td>
                                <td class="px-3 py-2 text-right">{{ $record->availability_pct !== null ? number_format($record->availability_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->performance_pct !== null ? number_format($record->performance_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $record->quality_pct !== null ? number_format($record->quality_pct, 1).'%' : '-' }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ ($record->oee_pct ?? 0) >= 85 ? 'text-green-600' : (($record->oee_pct ?? 0) >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
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
            <p class="text-gray-500">No OEE records for this period.</p>
        </div>
    @endif
</div>
@endsection
