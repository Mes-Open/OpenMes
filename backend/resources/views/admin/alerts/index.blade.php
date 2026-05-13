@extends('layouts.app')

@section('title', __('Alerts'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Alerts'), 'url' => null],
]" />

<div class="max-w-4xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Alerts') }}</h1>
        @php $total = $blockingIssues->count() + $overdueOrders->count() + $blockedOrders->count(); @endphp
        @if($total > 0)
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-600 text-white text-sm font-bold">
                {{ $total }}
            </span>
        @endif
    </div>

    @if($total === 0)
        <div class="card flex flex-col items-center py-16 text-center">
            <svg class="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xl font-semibold text-gray-700">{{ __('All clear') }}</p>
            <p class="text-gray-500 mt-1">{{ __('No active alerts at this time.') }}</p>
        </div>
    @endif

    {{-- Blocking Issues --}}
    @if($blockingIssues->count() > 0)
    <div class="mb-6">
        <h2 class="flex items-center gap-2 text-lg font-bold text-red-700 mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            {{ __('Blocking Issues') }}
            <span class="ml-1 bg-red-100 text-red-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $blockingIssues->count() }}</span>
        </h2>
        <div class="space-y-3">
            @foreach($blockingIssues as $issue)
            <div class="card border-l-4 border-red-500 bg-red-50">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-red-800">{{ $issue->issueType->name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                {{ $issue->status === 'OPEN' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' }}">
                                {{ $issue->status }}
                            </span>
                        </div>
                        @if($issue->description)
                            <p class="text-sm text-gray-600 mt-1">{{ $issue->description }}</p>
                        @endif
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500 flex-wrap">
                            @if($issue->workOrder)
                                <span>{{ __('Work Order') }}:
                                    <a href="{{ route('admin.work-orders.show', $issue->workOrder) }}"
                                       class="font-mono font-semibold text-blue-700 hover:underline">
                                        {{ $issue->workOrder->order_no }}
                                    </a>
                                </span>
                            @endif
                            <span>{{ __('Reported by') }}: {{ $issue->reportedBy?->name ?? '—' }}</span>
                            <span>{{ $issue->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <a href="{{ route('admin.issues.index') }}"
                       class="shrink-0 text-xs text-red-700 hover:underline font-medium">{{ __('View issues') }} →</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Overdue Work Orders --}}
    @if($overdueOrders->count() > 0)
    <div class="mb-6">
        <h2 class="flex items-center gap-2 text-lg font-bold text-orange-700 mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ __('Overdue Work Orders') }}
            <span class="ml-1 bg-orange-100 text-orange-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $overdueOrders->count() }}</span>
        </h2>
        <div class="overflow-hidden rounded-lg border border-orange-200 bg-white">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-orange-50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Order') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Line') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Due Date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Overdue') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($overdueOrders as $wo)
                    <tr class="hover:bg-orange-50 cursor-pointer" onclick="window.location='{{ route('admin.work-orders.show', $wo) }}'">
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm font-semibold text-blue-700">{{ $wo->order_no }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $wo->line?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-orange-700 font-medium">{{ $wo->due_date->translatedFormat('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-red-600 font-semibold">{{ $wo->due_date->diffForHumans(null, false, false, 1, ['locale' => 'en']) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $stColors = ['PENDING'=>'bg-gray-100 text-gray-700','ACCEPTED'=>'bg-blue-100 text-blue-700','IN_PROGRESS'=>'bg-yellow-100 text-yellow-700','BLOCKED'=>'bg-red-100 text-red-700','PAUSED'=>'bg-orange-100 text-orange-700','DONE'=>'bg-green-100 text-green-700','REJECTED'=>'bg-red-200 text-red-800','CANCELLED'=>'bg-gray-200 text-gray-600'];
                                $stLabels = ['PENDING'=>__('Pending'),'ACCEPTED'=>__('Accepted'),'IN_PROGRESS'=>__('In Progress'),'BLOCKED'=>__('Blocked'),'PAUSED'=>__('Paused'),'DONE'=>__('Done'),'REJECTED'=>__('Rejected'),'CANCELLED'=>__('Cancelled')];
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $stColors[$wo->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $stLabels[$wo->status] ?? $wo->status }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Blocked Work Orders --}}
    @if($blockedOrders->count() > 0)
    <div class="mb-6">
        <h2 class="flex items-center gap-2 text-lg font-bold text-yellow-700 mb-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            {{ __('Blocked Work Orders') }}
            <span class="ml-1 bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-0.5 rounded-full">{{ $blockedOrders->count() }}</span>
        </h2>
        <div class="overflow-hidden rounded-lg border border-yellow-200 bg-white">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr class="bg-yellow-50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Order') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Line') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">{{ __('Blocked since') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($blockedOrders as $wo)
                    <tr class="hover:bg-yellow-50 cursor-pointer" onclick="window.location='{{ route('admin.work-orders.show', $wo) }}'">
                        <td class="px-4 py-3">
                            <span class="font-mono text-sm font-semibold text-blue-700">{{ $wo->order_no }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $wo->line?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $wo->updated_at->diffForHumans(null, false, false, 1, ['locale' => 'en']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
