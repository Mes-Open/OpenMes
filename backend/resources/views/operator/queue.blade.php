@extends('layouts.app')

@section('title', 'Work Order Queue')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Work Order Queue</h1>
            <p class="text-gray-600 mt-2">Line: {{ $line->name }}</p>
        </div>
        <a href="{{ route('operator.select-line') }}" class="btn-touch btn-secondary">
            Change Line
        </a>
    </div>

    <!-- Active Work Orders -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Active Work Orders</h2>

        @if($activeWorkOrders->isEmpty())
            <div class="card text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No active work orders</h3>
                <p class="mt-1 text-sm text-gray-500">There are no work orders currently in progress on this line.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($activeWorkOrders as $workOrder)
                    <a href="{{ route('operator.work-order.detail', $workOrder) }}" class="card hover:shadow-xl cursor-pointer transition-all">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800">{{ $workOrder->order_no }}</h3>
                            @include('components.wo-status-badge', ['status' => $workOrder->status])
                        </div>

                        <!-- Product Type -->
                        <div class="mb-3">
                            <p class="text-sm text-gray-600">Product</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->productType?->name ?? '—' }}</p>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-3">
                            <p class="text-sm text-gray-600">Quantity</p>
                            <p class="font-medium text-gray-800">
                                {{ number_format($workOrder->produced_qty, 2) }} / {{ number_format($workOrder->planned_qty, 2) }}
                                @if($workOrder->planned_qty > 0)
                                <span class="text-sm text-gray-500">
                                    ({{ number_format(($workOrder->produced_qty / $workOrder->planned_qty) * 100, 1) }}%)
                                </span>
                                @endif
                            </p>
                        </div>

                        <!-- Batches -->
                        <div class="mb-3">
                            <p class="text-sm text-gray-600">Batches</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->batches->count() }}</p>
                        </div>

                        <!-- Priority & Due Date -->
                        <div class="border-t border-gray-200 pt-3 mt-3 flex justify-between items-center text-sm">
                            <span class="text-gray-600">
                                Priority: <span class="font-medium">{{ $workOrder->priority }}</span>
                            </span>
                            @if($workOrder->due_date)
                                <span class="text-gray-600">
                                    Due: <span class="font-medium">{{ \Carbon\Carbon::parse($workOrder->due_date)->format('M d') }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Arrow -->
                        <div class="mt-4 flex items-center justify-end text-blue-600">
                            <span class="text-sm font-medium">View Details</span>
                            <svg class="ml-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Completed Work Orders -->
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Recently Completed</h2>

        @if($completedWorkOrders->isEmpty())
            <div class="card text-center py-8">
                <p class="text-sm text-gray-500">No recently completed work orders</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($completedWorkOrders as $workOrder)
                    <a href="{{ route('operator.work-order.detail', $workOrder) }}" class="card hover:shadow-xl cursor-pointer transition-all opacity-75">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-gray-800">{{ $workOrder->order_no }}</h3>
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                Completed
                            </span>
                        </div>

                        <!-- Product Type -->
                        <div class="mb-3">
                            <p class="text-sm text-gray-600">Product</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->productType?->name ?? '—' }}</p>
                        </div>

                        <!-- Quantity -->
                        <div class="mb-3">
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="font-medium text-gray-800">{{ number_format($workOrder->produced_qty, 2) }}</p>
                        </div>

                        <!-- Completed At -->
                        @if($workOrder->completed_at)
                            <div class="border-t border-gray-200 pt-3 mt-3 text-sm text-gray-600">
                                Completed: {{ \Carbon\Carbon::parse($workOrder->completed_at)->format('M d, Y H:i') }}
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
