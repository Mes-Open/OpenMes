@extends('layouts.app')

@section('title', __('Scrap Reports'))

@php
    $categoryLabel = fn ($c) => $c ? __(ucfirst($c)) : __('Unknown');
    $reasons = $pareto['reasons'] ?? [];
    $topReason = $reasons[0] ?? null;
@endphp

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Scrap Reports'), 'url' => null],
]" />

<div class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('Scrap Reports') }}</h1>

    {{-- Filters --}}
    <div class="card mb-6">
        <form method="GET" action="{{ route('admin.scrap-reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="form-label">{{ __('From') }}</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input">
            </div>
            <div>
                <label class="form-label">{{ __('To') }}</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input">
            </div>
            <div class="min-w-[200px]">
                <label class="form-label">{{ __('Line') }}</label>
                <select name="line_id" class="form-input w-full">
                    <option value="">{{ __('All lines') }}</option>
                    @foreach($lines as $line)
                        <option value="{{ $line->id }}" {{ $lineId === $line->id ? 'selected' : '' }}>{{ $line->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-touch btn-primary">{{ __('Apply') }}</button>
        </form>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card">
            <p class="text-sm text-gray-500">{{ __('Total scrap quantity') }}</p>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($pareto['total_qty'] ?? 0, 2) }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">{{ __('Scrap entries') }}</p>
            <p class="text-2xl font-bold text-gray-800">{{ number_format($pareto['total_entries'] ?? 0) }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">{{ __('Distinct reasons') }}</p>
            <p class="text-2xl font-bold text-gray-800">{{ count($reasons) }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-gray-500">{{ __('Top reason') }}</p>
            <p class="text-lg font-bold text-gray-800 truncate">{{ $topReason['name'] ?? '—' }}</p>
            @if($topReason)
                <p class="text-xs text-gray-500">{{ number_format($topReason['pct'], 1) }}% {{ __('of total') }}</p>
            @endif
        </div>
    </div>

    {{-- Pareto --}}
    <div class="card mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Scrap Pareto by reason') }}</h2>
        @if(empty($reasons))
            <p class="text-gray-500 text-center py-8">{{ __('No scrap reported in this period.') }}</p>
        @else
            <canvas id="paretoChart" height="110"></canvas>
            <div class="overflow-x-auto mt-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">{{ __('Code') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">{{ __('Reason') }}</th>
                            <th class="text-left py-2 px-3 font-semibold text-gray-700">{{ __('Category') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('Quantity') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('% of Total') }}</th>
                            <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('Cumulative %') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($reasons as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3 font-mono text-gray-600">{{ $row['code'] }}</td>
                                <td class="py-2 px-3 font-medium text-gray-900">{{ $row['name'] }}</td>
                                <td class="py-2 px-3 text-gray-600">{{ $categoryLabel($row['category']) }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td class="py-2 px-3 text-right">{{ number_format($row['pct'], 1) }}%</td>
                                <td class="py-2 px-3 text-right">{{ number_format($row['cumulative_pct'], 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- By category --}}
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Scrap by category') }}</h2>
            @if(empty($byCategory))
                <p class="text-gray-500 text-center py-8">{{ __('No data.') }}</p>
            @else
                <canvas id="categoryChart" height="160"></canvas>
            @endif
        </div>

        {{-- Scrap rate per line --}}
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Scrap rate per line') }}</h2>
            @if(empty($ratePerLine))
                <p class="text-gray-500 text-center py-8">{{ __('No data.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 px-3 font-semibold text-gray-700">{{ __('Line') }}</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('Scrap') }}</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('Produced') }}</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700">{{ __('Scrap rate') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($ratePerLine as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-3 font-medium text-gray-900">{{ $row['line_name'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($row['scrap_qty'], 2) }}</td>
                                    <td class="py-2 px-3 text-right">{{ number_format($row['produced_qty'], 2) }}</td>
                                    <td class="py-2 px-3 text-right">
                                        {{ $row['scrap_rate_pct'] !== null ? number_format($row['scrap_rate_pct'], 2) . '%' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Trend --}}
    <div class="card mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">{{ __('Scrap trend over time') }}</h2>
        @if(empty($trend))
            <p class="text-gray-500 text-center py-8">{{ __('No scrap reported in this period.') }}</p>
        @else
            <canvas id="trendChart" height="100"></canvas>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Pareto: bar (quantity) + cumulative % line on a secondary axis.
    const paretoCtx = document.getElementById('paretoChart');
    if (paretoCtx) {
        new Chart(paretoCtx, {
            data: {
                labels: @json(collect($reasons)->pluck('code')),
                datasets: [
                    {
                        type: 'bar',
                        label: @json(__('Scrap quantity')),
                        data: @json(collect($reasons)->pluck('qty')),
                        backgroundColor: 'rgba(239, 68, 68, 0.6)',
                        borderColor: 'rgb(239, 68, 68)',
                        yAxisID: 'y',
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: @json(__('Cumulative %')),
                        data: @json(collect($reasons)->pluck('cumulative_pct')),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.2,
                        order: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: { beginAtZero: true, position: 'left' },
                    y1: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false } },
                },
            },
        });
    }

    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: @json(collect($byCategory)->map(fn ($r) => $categoryLabel($r['category']))->values()),
                datasets: [{
                    data: @json(collect($byCategory)->pluck('qty')),
                    backgroundColor: [
                        'rgb(239, 68, 68)',
                        'rgb(251, 191, 36)',
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(139, 92, 246)',
                    ],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom' } },
            },
        });
    }

    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json(collect($trend)->pluck('date')),
                datasets: [{
                    label: @json(__('Scrap quantity')),
                    data: @json(collect($trend)->pluck('qty')),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }
});
</script>
@endpush
