@extends('layouts.app')

@section('title', 'Product Type Details')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('admin.product-types.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-2 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Product Types
        </a>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800">{{ $productType->name }}</h1>
                @if($productType->is_active)
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                @else
                    <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">Inactive</span>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.product-types.edit', $productType) }}" class="btn-touch btn-secondary">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Product Type
                </a>
                <form method="POST" action="{{ route('admin.product-types.toggle-active', $productType) }}" class="inline">
                    @csrf
                    <button type="submit" class="btn-touch {{ $productType->is_active ? 'btn-secondary' : 'btn-primary' }}">
                        @if($productType->is_active)
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Deactivate
                        @else
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Activate
                        @endif
                    </button>
                </form>
            </div>
        </div>
        <p class="text-sm text-gray-500 font-mono mt-1">{{ $productType->code }}</p>
        @if($productType->description)
            <p class="text-gray-600 mt-2">{{ $productType->description }}</p>
        @endif
        @if($productType->unit_of_measure)
            <p class="text-sm text-gray-600 mt-1">Unit: <span class="font-medium">{{ $productType->unit_of_measure }}</span></p>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Process Templates</p>
                    <p class="text-3xl font-bold text-blue-600">{{ $productType->processTemplates->count() }}</p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Work Orders</p>
                    <p class="text-3xl font-bold text-purple-600">{{ $productType->workOrders->count() }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Process Templates -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Process Templates</h2>
                <!-- Note: Process template creation will be added when we create ProcessTemplateManagementController -->
            </div>

            @if($productType->processTemplates->count() > 0)
                <div class="space-y-2">
                    @foreach($productType->processTemplates as $template)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="font-medium text-gray-800">{{ $template->name }}</p>
                                        @if($template->is_active)
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">Inactive</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600">Version {{ $template->version }} • {{ $template->steps->count() }} steps</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-600 mb-2">No process templates yet</p>
                    <p class="text-sm text-gray-500">Process templates define how this product is manufactured.</p>
                </div>
            @endif
        </div>

        <!-- Recent Work Orders -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Work Orders</h2>

            @if($recentWorkOrders->count() > 0)
                <div class="space-y-2">
                    @foreach($recentWorkOrders as $workOrder)
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800">{{ $workOrder->work_order_number }}</p>
                                    <p class="text-sm text-gray-600">{{ $workOrder->product_name }}</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Quantity: {{ $workOrder->quantity }} | {{ $workOrder->created_at->format('Y-m-d H:i') }}
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($workOrder->status === 'PENDING') bg-yellow-100 text-yellow-800
                                    @elseif($workOrder->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                                    @elseif($workOrder->status === 'COMPLETED') bg-green-100 text-green-800
                                    @elseif($workOrder->status === 'BLOCKED') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $workOrder->status }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($productType->workOrders->count() > 10)
                    <p class="text-sm text-gray-500 text-center mt-4">
                        Showing 10 most recent of {{ $productType->workOrders->count() }} total work orders
                    </p>
                @endif
            @else
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <p class="text-gray-600">No work orders yet</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
