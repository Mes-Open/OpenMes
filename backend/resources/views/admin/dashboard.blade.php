@extends('layouts.app')

@section('title', __('Admin Dashboard'))

@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Header + Line Filter --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Admin Dashboard') }}</h1>
            <p class="text-gray-500 mt-1 text-sm">{{ now()->format('d M Y, H:i') }}
                @if($selectedLineId)
                    &mdash; <span class="font-medium text-blue-600">{{ $lines->find($selectedLineId)?->name }}</span>
                @else
                    &mdash; {{ __('all lines') }}
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

    @php $enabledWidgets = $enabledWidgets ?? []; @endphp

    {{-- KPI Cards --}}
    @if(empty($enabledWidgets) || in_array('kpi_cards', $enabledWidgets))
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">

        <a href="{{ route('admin.work-orders.index', $selectedLineId ? ['line_id' => $selectedLineId] : []) }}"
           class="card hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500 mb-1">{{ __('Total Work Orders') }}</p>
            <p class="text-3xl font-bold text-gray-800">{{ $stats['total_work_orders'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'IN_PROGRESS', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-blue-400">
            <p class="text-sm text-gray-500 mb-1">{{ __('In Progress') }}</p>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['in_progress'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('incl. accepted') }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'PENDING', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-gray-300">
            <p class="text-sm text-gray-500 mb-1">{{ __('Pending') }}</p>
            <p class="text-3xl font-bold text-gray-600">{{ $stats['pending'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'BLOCKED', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-red-400">
            <p class="text-sm text-gray-500 mb-1">{{ __('Blocked') }}</p>
            <p class="text-3xl font-bold text-red-600">{{ $stats['blocked'] }}</p>
        </a>

        <a href="{{ route('admin.work-orders.index', array_filter(['status' => 'DONE', 'line_id' => $selectedLineId])) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-green-400">
            <p class="text-sm text-gray-500 mb-1">{{ __('Done Today') }}</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['done_today'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $stats['done'] }} {{ __('total done') }}</p>
        </a>

        <a href="{{ route('admin.issues.index') }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-yellow-400">
            <p class="text-sm text-gray-500 mb-1">{{ __('Open Issues') }}</p>
            <p class="text-3xl font-bold text-yellow-600">{{ $stats['open_issues'] }}</p>
        </a>

        <a href="{{ route('admin.issues.index', ['blocking' => 1]) }}"
           class="card hover:shadow-md transition-shadow border-l-4 border-red-600">
            <p class="text-sm text-gray-500 mb-1">{{ __('Blocking Issues') }}</p>
            <p class="text-3xl font-bold text-red-700">{{ $stats['blocking_issues'] }}</p>
        </a>

        <a href="{{ route('admin.lines.index') }}"
           class="card hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500 mb-1">{{ __('Active Lines') }}</p>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['active_lines'] }}</p>
        </a>

    </div>
    @endif

    {{-- ── Widget zone: admin_dashboard.kpi ── --}}
    @foreach($widgetRegistry->getWidgets('admin_dashboard.kpi') as $widget)
        @include($widget['view'], array_merge($widget['data'], ['selectedLineId' => $selectedLineId]))
    @endforeach

    {{-- OEE Overview — full width --}}
    @if((empty($enabledWidgets) || in_array('oee_overview', $enabledWidgets)) && ($oeeRecords ?? collect())->isNotEmpty())
    <div class="card mb-6">
        <div class="flex justify-between items-center mb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-gray-800">{{ __('OEE Overview') }}</h2>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-500 dark:text-gray-300 flex items-center justify-center text-xs font-bold hover:bg-blue-100 hover:text-blue-600 transition-colors" title="What is OEE?">?</button>
                    <div x-show="open" x-cloak x-transition class="absolute left-0 top-7 z-50 w-72 p-4 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 text-sm">
                        <p class="font-bold text-gray-800 dark:text-white mb-2">OEE = A × P × Q</p>
                        <ul class="space-y-1 text-gray-600 dark:text-gray-300">
                            <li><strong>A</strong> — Availability: actual run time vs planned time (downtime impact)</li>
                            <li><strong>P</strong> — Performance: actual speed vs ideal speed (slow cycles impact)</li>
                            <li><strong>Q</strong> — Quality: good units vs total produced (defects impact)</li>
                        </ul>
                        <p class="mt-2 text-xs text-gray-400">Target: >85% world-class, 60-85% typical, &lt;60% needs improvement</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.oee.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Full report') }} →</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
            @foreach($lines as $line)
                @php $oee = ($oeeRecords ?? collect())->get($line->id); @endphp
                <div class="p-3 rounded-lg border {{ $oee ? ($oee->oee_pct >= 85 ? 'border-green-200 bg-green-50' : ($oee->oee_pct >= 60 ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50')) : 'border-gray-200 bg-gray-50' }}">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700 truncate" title="{{ $line->name }}">{{ $line->name }}</span>
                        @if($oee)
                            <span class="text-lg font-bold {{ $oee->oee_pct >= 85 ? 'text-green-700' : ($oee->oee_pct >= 60 ? 'text-yellow-700' : 'text-red-700') }}">
                                {{ number_format($oee->oee_pct, 1) }}%
                            </span>
                        @else
                            <span class="text-sm text-gray-400">N/A</span>
                        @endif
                    </div>
                    @if($oee)
                        <div class="flex gap-3 text-xs text-gray-500">
                            <span>A: {{ number_format($oee->availability_pct, 0) }}%</span>
                            <span>P: {{ $oee->performance_pct !== null ? number_format($oee->performance_pct, 0).'%' : '-' }}</span>
                            <span>Q: {{ number_format($oee->quality_pct, 0) }}%</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- {{ __('Recent Work Orders') }} --}}
        <div class="lg:col-span-2 card">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-800">{{ __('Recent Work Orders') }}</h2>
                <a href="{{ route('admin.work-orders.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('View all') }} →</a>
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
                                        <span class="inline-flex items-center font-mono text-sm font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded px-2 py-0.5">{{ $wo->order_no }}</span>
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

            {{-- {{ __('Open Issues') }} --}}
            <div class="card">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="text-base font-bold text-gray-800">{{ __('Open Issues') }}</h2>
                    <a href="{{ route('admin.issues.index') }}" class="text-xs text-blue-600 hover:underline">{{ __('View all') }} →</a>
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

            {{-- {{ __('Quick Links') }} --}}
            <div class="card">
                <h2 class="text-base font-bold text-gray-800 mb-3">{{ __('Quick Links') }}</h2>
                <div class="space-y-1">
                    @foreach([
                        ['route' => 'admin.work-orders.create', 'label' => '+ ' . __('New Work Order')],
                        ['route' => 'admin.lines.index',        'label' => __('Production Lines')],
                        ['route' => 'admin.product-types.index','label' => __('Product Types')],
                        ['route' => 'admin.users.index',        'label' => __('User Management')],
                        ['route' => 'admin.issue-types.index',  'label' => __('Issue Types')],
                        ['route' => 'admin.csv-import',         'label' => __('CSV Import')],
                        ['route' => 'admin.audit-logs',         'label' => __('Audit Logs')],
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
