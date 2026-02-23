@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Header + Line Filter --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
            <p class="text-gray-500 mt-1 text-sm">{{ now()->format('d M Y, H:i') }}
                @if($selectedLineId)
                    &mdash; <span class="font-medium text-blue-600">{{ $lines->find($selectedLineId)?->name }}</span>
                @else
                    &mdash; all lines
                @endif
            </p>
        </div>

        {{-- Line filter --}}
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <select name="line_id" onchange="this.form.submit()"
                    class="form-input py-1.5 text-sm pr-8 min-w-[180px]">
                <option value="">All lines</option>
                @foreach($lines as $line)
                    <option value="{{ $line->id }}" {{ $selectedLineId == $line->id ? 'selected' : '' }}>
                        {{ $line->name }}
                    </option>
                @endforeach
            </select>
            @if($selectedLineId)
                <a href="{{ route('admin.dashboard') }}"
                   class="text-xs text-gray-400 hover:text-gray-700 whitespace-nowrap">✕ Clear</a>
            @endif
        </form>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-6">

        <a href="{{ route('admin.work-orders.index', $selectedLineId ? ['line_id' => $selectedLineId] : []) }}"
           class="card hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500 mb-1">Total Work Orders</p>
            <p class="text-3xl font-bold text-gray-800">{{ $stats['total_work_orders'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'IN_PROGRESS', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-blue-400">
            <p class="text-sm text-gray-500 mb-1">In Progress</p>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['in_progress'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">incl. accepted</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'PENDING', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-gray-300">
            <p class="text-sm text-gray-500 mb-1">Pending</p>
            <p class="text-3xl font-bold text-gray-600">{{ $stats['pending'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'BLOCKED', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-red-400">
            <p class="text-sm text-gray-500 mb-1">Blocked</p>
            <p class="text-3xl font-bold text-red-600">{{ $stats['blocked'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'DONE', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-green-400">
            <p class="text-sm text-gray-500 mb-1">Done Today</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['done_today'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $stats['done'] }} total done</p>
        </a>

        <a href="{{ route('admin.issues.index') }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-yellow-400">
            <p class="text-sm text-gray-500 mb-1">Open Issues</p>
            <p class="text-3xl font-bold text-yellow-600">{{ $stats['open_issues'] }}</p>
        </a>

        <a href="{{ route('admin.issues.index', ['blocking' => 1]) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-red-600">
            <p class="text-sm text-gray-500 mb-1">Blocking Issues</p>
            <p class="text-3xl font-bold text-red-700">{{ $stats['blocking_issues'] }}</p>
        </a>

        <a href="{{ route('admin.lines.index') }}"
           class="card hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500 mb-1">Active Lines</p>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['active_lines'] }}</p>
        </a>

    </div>

    {{-- ── Widget zone: admin_dashboard.kpi ── --}}
    @foreach($widgetRegistry->getWidgets('admin_dashboard.kpi') as $widget)
        @include($widget['view'], array_merge($widget['data'], ['selectedLineId' => $selectedLineId]))
    @endforeach

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
                                <tr class="hover:bg-gray-50 cursor-pointer"
                                    onclick="location.href='{{ route('admin.work-orders.show', $wo) }}'">
                                    <td class="py-2">
                                        <span class="font-mono font-medium text-blue-700">{{ $wo->order_no }}</span>
                                        @if($wo->productType)
                                            <p class="text-xs text-gray-400">{{ $wo->productType->name }}</p>
                                        @endif
                                    </td>
                                    <td class="py-2 text-gray-600">{{ $wo->line?->name ?? '—' }}</td>
                                    <td class="py-2">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                            @if($wo->status === 'PENDING')      bg-gray-100 text-gray-700
                                            @elseif($wo->status === 'ACCEPTED') bg-indigo-100 text-indigo-700
                                            @elseif($wo->status === 'IN_PROGRESS') bg-blue-100 text-blue-700
                                            @elseif($wo->status === 'BLOCKED')  bg-red-100 text-red-700
                                            @elseif($wo->status === 'DONE')     bg-green-100 text-green-700
                                            @elseif($wo->status === 'PAUSED')   bg-yellow-100 text-yellow-700
                                            @else                               bg-gray-100 text-gray-500
                                            @endif">
                                            {{ str_replace('_', ' ', $wo->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-gray-600">
                                        @if($wo->planned_qty > 0)
                                            @php $pct = min(($wo->produced_qty / $wo->planned_qty) * 100, 100); @endphp
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                                    <div class="h-full bg-blue-400 rounded-full" style="width:{{ $pct }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
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
                                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $issue->isBlocking() ? 'bg-red-500' : 'bg-yellow-400' }}"></span>
                                    <p class="text-xs font-medium text-gray-800 truncate">{{ $issue->title }}</p>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 ml-4">
                                    {{ $issue->workOrder?->order_no ?? '—' }} · {{ $issue->issueType?->name }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ── Widget zone: admin_dashboard.sidebar ── --}}
            @foreach($widgetRegistry->getWidgets('admin_dashboard.sidebar') as $widget)
                @include($widget['view'], array_merge($widget['data'], ['selectedLineId' => $selectedLineId]))
            @endforeach

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

    {{-- ── Widget zone: admin_dashboard.main ── --}}
    @if($widgetRegistry->hasWidgets('admin_dashboard.main'))
        <div class="mt-6 space-y-6">
            @foreach($widgetRegistry->getWidgets('admin_dashboard.main') as $widget)
                @include($widget['view'], array_merge($widget['data'], ['selectedLineId' => $selectedLineId]))
            @endforeach
        </div>
    @endif

</div>
@endsection
