@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
        <p class="text-gray-600 mt-1">System overview — {{ now()->format('d M Y, H:i') }}</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('admin.work-orders.index') }}" class="card hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500 mb-1">Total Work Orders</p>
            <p class="text-3xl font-bold text-gray-800">{{ $stats['total_work_orders'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', ['status' => 'IN_PROGRESS']) }}" class="card hover:shadow-md transition-shadow border-l-4 border-blue-400">
            <p class="text-sm text-gray-500 mb-1">In Progress</p>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['in_progress'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', ['status' => 'PENDING']) }}" class="card hover:shadow-md transition-shadow border-l-4 border-gray-300">
            <p class="text-sm text-gray-500 mb-1">Pending</p>
            <p class="text-3xl font-bold text-gray-600">{{ $stats['pending'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', ['status' => 'BLOCKED']) }}" class="card hover:shadow-md transition-shadow border-l-4 border-red-400">
            <p class="text-sm text-gray-500 mb-1">Blocked</p>
            <p class="text-3xl font-bold text-red-600">{{ $stats['blocked'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', ['status' => 'DONE']) }}" class="card hover:shadow-md transition-shadow border-l-4 border-green-400">
            <p class="text-sm text-gray-500 mb-1">Done Today</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['done_today'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['done'] }} total</p>
        </a>

        <a href="{{ route('admin.issues.index') }}" class="card hover:shadow-md transition-shadow border-l-4 border-yellow-400">
            <p class="text-sm text-gray-500 mb-1">Open Issues</p>
            <p class="text-3xl font-bold text-yellow-600">{{ $stats['open_issues'] }}</p>
        </a>

        <a href="{{ route('admin.issues.index', ['blocking' => 1]) }}" class="card hover:shadow-md transition-shadow border-l-4 border-red-600">
            <p class="text-sm text-gray-500 mb-1">Blocking Issues</p>
            <p class="text-3xl font-bold text-red-700">{{ $stats['blocking_issues'] }}</p>
        </a>

        <div class="card">
            <p class="text-sm text-gray-500 mb-1">Active Lines</p>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['active_lines'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent Work Orders --}}
        <div class="lg:col-span-2 card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">Recent Work Orders</h2>
                <a href="{{ route('admin.work-orders.index') }}" class="text-sm text-blue-600 hover:underline">View all →</a>
            </div>
            @if($recentWorkOrders->isEmpty())
                <p class="text-sm text-gray-500 py-4 text-center">No work orders yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left">
                                <th class="pb-2 text-gray-500 font-medium">Order #</th>
                                <th class="pb-2 text-gray-500 font-medium">Line</th>
                                <th class="pb-2 text-gray-500 font-medium">Status</th>
                                <th class="pb-2 text-gray-500 font-medium">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($recentWorkOrders as $wo)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2">
                                        <a href="{{ route('admin.work-orders.show', $wo) }}" class="font-mono font-medium text-blue-700 hover:underline">
                                            {{ $wo->order_no }}
                                        </a>
                                        @if($wo->productType)
                                            <p class="text-xs text-gray-400">{{ $wo->productType->name }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2 text-gray-600">{{ $wo->line->name ?? '—' }}</td>
                                    <td class="py-2">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                            @if($wo->status === 'PENDING')     bg-gray-100 text-gray-700
                                            @elseif($wo->status === 'IN_PROGRESS') bg-blue-100 text-blue-700
                                            @elseif($wo->status === 'BLOCKED')  bg-red-100 text-red-700
                                            @elseif($wo->status === 'DONE')     bg-green-100 text-green-700
                                            @else                               bg-gray-100 text-gray-500
                                            @endif">
                                            {{ str_replace('_', ' ', $wo->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-gray-600">
                                        {{ number_format($wo->produced_qty, 0) }} / {{ number_format($wo->planned_qty, 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">

            {{-- Open Issues --}}
            <div class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-base font-bold text-gray-800">Open Issues</h2>
                    <a href="{{ route('admin.issues.index') }}" class="text-xs text-blue-600 hover:underline">View all →</a>
                </div>
                @if($recentIssues->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-3">No open issues.</p>
                @else
                    <div class="space-y-2">
                        @foreach($recentIssues as $issue)
                            <div class="p-2 rounded-lg {{ $issue->isBlocking() ? 'bg-red-50 border border-red-100' : 'bg-gray-50' }}">
                                <div class="flex items-center gap-2">
                                    @if($issue->isBlocking())
                                        <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-yellow-400 flex-shrink-0"></span>
                                    @endif
                                    <p class="text-xs font-medium text-gray-800 truncate">{{ $issue->title }}</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 ml-4">
                                    {{ $issue->workOrder->order_no ?? '—' }} · {{ $issue->issueType->name }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick Links --}}
            <div class="card">
                <h2 class="text-base font-bold text-gray-800 mb-3">Quick Links</h2>
                <div class="space-y-1">
                    @foreach([
                        ['route' => 'admin.work-orders.create', 'label' => '+ New Work Order'],
                        ['route' => 'admin.lines.index',        'label' => 'Production Lines'],
                        ['route' => 'admin.product-types.index','label' => 'Product Types'],
                        ['route' => 'admin.users.index',        'label' => 'User Management'],
                        ['route' => 'admin.issue-types.index',  'label' => 'Issue Types'],
                        ['route' => 'admin.csv-import',         'label' => 'CSV Import'],
                        ['route' => 'admin.audit-logs',         'label' => 'Audit Logs'],
                    ] as $link)
                        <a href="{{ route($link['route']) }}"
                           class="block px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
