@extends('layouts.app')

@section('title', 'Production Reports')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Reports', 'url' => null],
]" />

<div class="max-w-7xl mx-auto" x-data>

    {{-- Page header --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Production Reports</h1>
        <p class="text-gray-600 mt-1">Overview of production performance for the selected period.</p>
    </div>

    {{-- Filter form --}}
    <div class="card mb-8">
        <form method="GET" action="{{ route('admin.reports') }}" id="report-filters">
            <div class="flex flex-wrap gap-4 items-end">

                {{-- Period type --}}
                <div>
                    <label for="period" class="form-label">Period Type</label>
                    <select id="period" name="period" class="form-input"
                            onchange="document.getElementById('report-filters').submit()">
                        <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="weekly"  {{ $period === 'weekly'  ? 'selected' : '' }}>Weekly</option>
                    </select>
                </div>

                {{-- Year --}}
                <div>
                    <label for="year" class="form-label">Year</label>
                    <select id="year" name="year" class="form-input"
                            onchange="document.getElementById('report-filters').submit()">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Month (visible only for monthly) --}}
                @if($period === 'monthly')
                <div>
                    <label for="month" class="form-label">Month</label>
                    <select id="month" name="month" class="form-input"
                            onchange="document.getElementById('report-filters').submit()">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Week (visible only for weekly) --}}
                @if($period === 'weekly')
                <div>
                    <label for="week" class="form-label">Week</label>
                    <select id="week" name="week" class="form-input"
                            onchange="document.getElementById('report-filters').submit()">
                        @foreach(range(1, 53) as $w)
                            <option value="{{ $w }}" {{ $week == $w ? 'selected' : '' }}>
                                Week {{ $w }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Line --}}
                <div>
                    <label for="line_id" class="form-label">Line</label>
                    <select id="line_id" name="line_id" class="form-input"
                            onchange="document.getElementById('report-filters').submit()">
                        <option value="">All Lines</option>
                        @foreach($lines as $line)
                            <option value="{{ $line->id }}" {{ $lineId == $line->id ? 'selected' : '' }}>
                                {{ $line->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Submit (fallback for no-JS) --}}
                <div>
                    <button type="submit" class="btn-primary">Apply</button>
                </div>

                {{-- Period label badge --}}
                <div class="ml-auto flex items-end">
                    <span class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                        @if($period === 'monthly')
                            {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $year }}
                        @else
                            Week {{ $week }}, {{ $year }}
                        @endif
                    </span>
                </div>
            </div>
        </form>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Work Orders</p>
                    <p class="text-3xl font-bold text-gray-800">{{ $totalWorkOrders }}</p>
                </div>
                <div class="bg-gray-100 rounded-full p-3 shrink-0">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Completed</p>
                    <p class="text-3xl font-bold text-green-600">{{ $completedWorkOrders }}</p>
                </div>
                <div class="bg-green-100 rounded-full p-3 shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Completion Rate</p>
                    <p class="text-3xl font-bold {{ $completionRate >= 80 ? 'text-green-600' : ($completionRate >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $completionRate }}%
                    </p>
                </div>
                <div class="bg-blue-100 rounded-full p-3 shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Total Produced Qty</p>
                    <p class="text-3xl font-bold text-indigo-600">{{ number_format((float) $totalProducedQty, 0) }}</p>
                </div>
                <div class="bg-indigo-100 rounded-full p-3 shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 mb-1">Avg Cycle Time</p>
                    @if($avgCycleTime !== null)
                        <p class="text-3xl font-bold text-purple-600">{{ $avgCycleTime }}<span class="text-base font-normal text-gray-500 ml-1">min</span></p>
                    @else
                        <p class="text-3xl font-bold text-gray-400">—</p>
                    @endif
                </div>
                <div class="bg-purple-100 rounded-full p-3 shrink-0">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

    </div>

    {{-- Tables grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Production by line --}}
        <div class="card lg:col-span-2">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Production by Line</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">Line</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Planned Qty</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Produced Qty</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Work Orders</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Completed</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Completion %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($byLine as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium text-gray-900">{{ $row->line_name }}</td>
                                <td class="py-3 px-4 text-right text-gray-700">{{ number_format((float) $row->planned_qty, 0) }}</td>
                                <td class="py-3 px-4 text-right text-gray-700">{{ number_format((float) $row->produced_qty, 0) }}</td>
                                <td class="py-3 px-4 text-right text-gray-700">{{ $row->total_orders }}</td>
                                <td class="py-3 px-4 text-right text-gray-700">{{ $row->completed_orders }}</td>
                                <td class="py-3 px-4 text-right">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        {{ $row->completion_pct >= 80 ? 'bg-green-100 text-green-800' : ($row->completion_pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $row->completion_pct }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    No data for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Work orders by status --}}
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Work Orders by Status</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Count</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">% of Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($byStatus as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    @php
                                        $statusClasses = [
                                            'PENDING'     => 'bg-gray-100 text-gray-700',
                                            'IN_PROGRESS' => 'bg-blue-100 text-blue-800',
                                            'BLOCKED'     => 'bg-red-100 text-red-800',
                                            'DONE'        => 'bg-green-100 text-green-800',
                                            'CANCELLED'   => 'bg-orange-100 text-orange-800',
                                        ];
                                        $cls = $statusClasses[$row->status] ?? 'bg-gray-100 text-gray-700';
                                        $label = str_replace('_', ' ', $row->status);
                                    @endphp
                                    <span class="px-2 py-1 rounded-full text-xs font-medium {{ $cls }}">{{ $label }}</span>
                                </td>
                                <td class="py-3 px-4 text-right font-medium text-gray-900">{{ $row->count }}</td>
                                <td class="py-3 px-4 text-right text-gray-600">{{ $row->pct }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-10 text-center text-gray-400">
                                    No data for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Top 5 issues by type --}}
        <div class="card">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Top 5 Issues by Type</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700">Issue Type</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($topIssues as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 text-gray-900">{{ $row->type_name }}</td>
                                <td class="py-3 px-4 text-right">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                        {{ $row->count }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-10 text-center text-gray-400">
                                    No issues reported for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
