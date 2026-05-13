@extends('layouts.app')

@section('title', __('Supervisor Dashboard'))

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Supervisor Dashboard') }}</h1>
            <p class="text-gray-600 mt-2">{{ __('Production analytics and insights') }}</p>
        </div>

        <!-- Line Filter -->
        <div class="flex items-center gap-3">
            <label for="line_filter" class="text-sm font-medium text-gray-700">{{ __('Line') }}:</label>
            <select
                id="line_filter"
                name="line_id"
                class="form-input min-w-[200px]"
                onchange="window.location.href = '{{ route('supervisor.dashboard') }}?line_id=' + this.value"
            >
                <option value="">{{ __('All Lines') }}</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}" {{ $selectedLine && $selectedLine->id === $line->id ? 'selected' : '' }}>
                        {{ $line->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Active Work Orders') }}</p>
                    <p class="text-3xl font-bold text-blue-600">{{ $stats['active_work_orders'] }}</p>
                </div>
                <div class="bg-blue-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Completed') }}</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['completed_work_orders'] }}</p>
                </div>
                <div class="bg-green-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Blocked Orders') }}</p>
                    <p class="text-3xl font-bold text-red-600">{{ $stats['blocked_work_orders'] }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Open Issues') }}</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $stats['open_issues'] }}</p>
                </div>
                <div class="bg-yellow-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Blocking Issues') }}</p>
                    <p class="text-3xl font-bold text-purple-600">{{ $stats['blocking_issues'] }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">{{ __('Total Orders') }}</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $stats['total_work_orders'] }}</p>
                </div>
                <div class="bg-gray-100 rounded-full p-4">
                    <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Throughput Chart -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Daily Production (Last 30 Days)') }}</h2>
            <canvas id="throughputChart"></canvas>
            <p class="text-sm text-gray-600 mt-4 text-center">
                {{ __('Average') }}: <span class="font-medium">{{ $throughputData['average'] }}</span> {{ __('units/day') }}
            </p>
        </div>

        <!-- Issues by Type Chart -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Issues by Type (Last 30 Days)') }}</h2>
            @if(empty($issueStats['by_type']['labels']))
                <div class="text-center py-12 text-gray-500">{{ __('No issues reported') }}</div>
            @else
                <canvas id="issuesTypeChart"></canvas>
            @endif
        </div>
    </div>

    <!-- Cycle Time Table -->
    <div class="card mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Recent Batch Cycle Times') }}</h2>

        @if($cycleTimeData->isEmpty())
            <div class="text-center py-12 text-gray-500">{{ __('No completed batches in the last 30 days') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Batch') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Work Order') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Product') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Quantity') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Cycle Time') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($cycleTimeData->take(10) as $batch)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $batch['batch_number'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $batch['work_order_no'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $batch['product_type'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ number_format($batch['produced_qty'], 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $batch['cycle_time_hours'] }} hrs</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ \Carbon\Carbon::parse($batch['completed_at'])->format('M d, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Production Controls Overview -->
    @if(isset($productionControls))
    <div class="card mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Production Controls') }}</h2>

        {{-- Alert Badges --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 rounded-lg {{ $productionControls['unconfirmed_today'] > 0 ? 'bg-orange-50 border border-orange-200' : 'bg-green-50 border border-green-200' }}">
                <p class="text-sm font-medium {{ $productionControls['unconfirmed_today'] > 0 ? 'text-orange-800' : 'text-green-800' }}">
                    {{ __('Parameters Unconfirmed Today') }}
                </p>
                <p class="text-2xl font-bold {{ $productionControls['unconfirmed_today'] > 0 ? 'text-orange-600' : 'text-green-600' }}">
                    {{ $productionControls['unconfirmed_today'] }}
                </p>
            </div>
            <div class="p-4 rounded-lg {{ $productionControls['qc_needed_count'] > 0 ? 'bg-blue-50 border border-blue-200' : 'bg-green-50 border border-green-200' }}">
                <p class="text-sm font-medium {{ $productionControls['qc_needed_count'] > 0 ? 'text-blue-800' : 'text-green-800' }}">{{ __('QC Checks Needed') }}</p>
                <p class="text-2xl font-bold {{ $productionControls['qc_needed_count'] > 0 ? 'text-blue-600' : 'text-green-600' }}">{{ $productionControls['qc_needed_count'] }}</p>
            </div>
            <div class="p-4 rounded-lg {{ $productionControls['unreleased_count'] > 0 ? 'bg-purple-50 border border-purple-200' : 'bg-green-50 border border-green-200' }}">
                <p class="text-sm font-medium {{ $productionControls['unreleased_count'] > 0 ? 'text-purple-800' : 'text-green-800' }}">{{ __('Awaiting Release') }}</p>
                <p class="text-2xl font-bold {{ $productionControls['unreleased_count'] > 0 ? 'text-purple-600' : 'text-green-600' }}">{{ $productionControls['unreleased_count'] }}</p>
            </div>
        </div>

        {{-- Active Batches Table --}}
        @if(count($productionControls['active_batches']) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Batch') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Order') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Product') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('LOT') }}</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Workstation') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Params') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('QC') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Checklist') }}</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Released') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($productionControls['active_batches'] as $b)
                            <tr>
                                <td class="px-3 py-2 font-medium">#{{ $b['batch_number'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $b['work_order'] }}</td>
                                <td class="px-3 py-2">{{ $b['product'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-blue-600">{{ $b['lot_number'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $b['workstation'] ?? '-' }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($b['confirmed_today'])
                                        <span class="text-green-600" title="Last: {{ $b['last_confirmation'] }}">OK</span>
                                    @else
                                        <span class="text-orange-600">{{ __('Needed') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="{{ $b['qc_ok'] ? 'text-green-600' : 'text-orange-600' }}">
                                        {{ $b['qc_count'] }}/3
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($b['checklist_done'])
                                        <span class="{{ $b['checklist_passed'] ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $b['checklist_passed'] ? __('Pass') : __('Fail') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if($b['released'])
                                        <span class="text-green-600">{{ $b['release_type'] === 'for_sale' ? __('Sale') : __('Prod') }}</span>
                                    @elseif($b['status'] === 'DONE')
                                        <span class="text-purple-600">{{ __('Pending') }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-center py-8 text-gray-500">{{ __('No active batches.') }}</p>
        @endif
    </div>
    @endif

    <!-- Recent Issues -->
    <div class="card">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Recent Issues') }}</h2>

        @if($recentIssues->isEmpty())
            <div class="text-center py-12 text-gray-500">{{ __('No recent issues') }}</div>
        @else
            <div class="space-y-3">
                @foreach($recentIssues as $issue)
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h4 class="font-medium text-gray-800">{{ $issue->issueType->name }}</h4>
                                <p class="text-sm text-gray-600">{{ $issue->workOrder->order_no }}</p>
                            </div>
                            <div class="flex gap-2">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    @if($issue->status === 'OPEN') bg-yellow-100 text-yellow-800
                                    @elseif($issue->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                                    @elseif($issue->status === 'RESOLVED') bg-green-100 text-green-800
                                    @endif">
                                    {{ $issue->status }}
                                </span>
                                @if($issue->is_blocking)
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                        {{ __('Blocking') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mb-2">{{ $issue->description }}</p>
                        <p class="text-xs text-gray-500">
                            {{ \Carbon\Carbon::parse($issue->reported_at)->diffForHumans() }}
                            @if($issue->reportedBy)
                                {{ __('by') }} {{ $issue->reportedBy->name }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Throughput Chart
    const throughputCtx = document.getElementById('throughputChart');
    if (throughputCtx) {
        new Chart(throughputCtx, {
            type: 'line',
            data: {
                labels: @json($throughputData['labels']),
                datasets: [{
                    label: 'Units Produced',
                    data: @json($throughputData['values']),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Issues by Type Chart
    const issuesTypeCtx = document.getElementById('issuesTypeChart');
    if (issuesTypeCtx && @json(!empty($issueStats['by_type']['labels']))) {
        new Chart(issuesTypeCtx, {
            type: 'doughnut',
            data: {
                labels: @json($issueStats['by_type']['labels']),
                datasets: [{
                    data: @json($issueStats['by_type']['values']),
                    backgroundColor: [
                        'rgb(239, 68, 68)',
                        'rgb(251, 191, 36)',
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(139, 92, 246)',
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
});
</script>
@endpush
