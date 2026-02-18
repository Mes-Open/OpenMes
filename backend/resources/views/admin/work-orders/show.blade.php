@extends('layouts.app')

@section('title', 'Work Order ' . $workOrder->order_no)

@section('content')
<div class="max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-gray-800 font-mono">{{ $workOrder->order_no }}</h1>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    @if($workOrder->status === 'PENDING')      bg-gray-100 text-gray-700
                    @elseif($workOrder->status === 'IN_PROGRESS') bg-blue-100 text-blue-700
                    @elseif($workOrder->status === 'BLOCKED')   bg-red-100 text-red-700
                    @elseif($workOrder->status === 'DONE')      bg-green-100 text-green-700
                    @else                                       bg-gray-100 text-gray-400
                    @endif">
                    {{ str_replace('_', ' ', $workOrder->status) }}
                </span>
            </div>
            <p class="text-gray-500 mt-1">
                Created {{ $workOrder->created_at->diffForHumans() }}
                @if($workOrder->productType) · {{ $workOrder->productType->name }} @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if(!in_array($workOrder->status, ['DONE','CANCELLED']))
                <a href="{{ route('admin.work-orders.edit', $workOrder) }}" class="btn-touch btn-secondary text-sm">Edit</a>
                <form method="POST" action="{{ route('admin.work-orders.cancel', $workOrder) }}"
                      onsubmit="return confirm('Cancel this work order?')">
                    @csrf
                    <button class="btn-touch btn-secondary text-sm text-orange-600">Cancel</button>
                </form>
            @endif
            <a href="{{ route('admin.work-orders.index') }}" class="btn-touch btn-secondary text-sm">← Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Main --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Details --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Details</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Order Number</p>
                        <p class="font-mono font-semibold text-gray-800">{{ $workOrder->order_no }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Line</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->line->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Product Type</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->productType->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Planned Qty</p>
                        <p class="font-medium text-gray-800">{{ number_format($workOrder->planned_qty, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Produced Qty</p>
                        <p class="font-medium text-gray-800">{{ number_format($workOrder->produced_qty, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Priority</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->priority }}</p>
                    </div>
                    @if($workOrder->due_date)
                        <div>
                            <p class="text-gray-500">Due Date</p>
                            <p class="font-medium {{ $workOrder->due_date->isPast() && $workOrder->status !== 'DONE' ? 'text-red-600' : 'text-gray-800' }}">
                                {{ $workOrder->due_date->format('d M Y') }}
                            </p>
                        </div>
                    @endif
                    @if($workOrder->description)
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-gray-500">Description</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->description }}</p>
                        </div>
                    @endif
                    @if(!empty($workOrder->extra_data))
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-gray-500 mb-1">Extra Data</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($workOrder->extra_data as $key => $val)
                                    <div class="bg-gray-50 rounded px-2 py-1">
                                        <span class="text-xs text-gray-400">{{ $key }}</span>
                                        <p class="text-gray-700 font-medium">{{ $val }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Batches --}}
            <div class="card">
                <h2 class="text-lg font-bold text-gray-800 mb-4">
                    Batches
                    <span class="text-sm font-normal text-gray-400">({{ $workOrder->batches->count() }})</span>
                </h2>
                @if($workOrder->batches->isEmpty())
                    <p class="text-sm text-gray-400 py-4 text-center">No batches yet.</p>
                @else
                    <div class="space-y-3">
                        @foreach($workOrder->batches as $batch)
                            <div class="border border-gray-100 rounded-lg p-3" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                                <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
                                    <div class="flex items-center gap-3">
                                        <span class="font-semibold text-gray-700">Batch #{{ $batch->batch_number }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            @if($batch->status === 'PENDING')      bg-gray-100 text-gray-600
                                            @elseif($batch->status === 'IN_PROGRESS') bg-blue-100 text-blue-600
                                            @elseif($batch->status === 'DONE')     bg-green-100 text-green-600
                                            @else                                  bg-gray-100 text-gray-400
                                            @endif">
                                            {{ str_replace('_', ' ', $batch->status) }}
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            {{ number_format($batch->produced_qty, 2) }} / {{ number_format($batch->target_qty, 2) }}
                                        </span>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div x-show="open" x-transition class="mt-3 space-y-1">
                                    @foreach($batch->steps as $step)
                                        <div class="flex items-center gap-3 py-1 px-2 rounded text-sm">
                                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs
                                                @if($step->status === 'DONE')        bg-green-100 text-green-700
                                                @elseif($step->status === 'IN_PROGRESS') bg-blue-100 text-blue-700
                                                @else                                bg-gray-100 text-gray-500
                                                @endif">
                                                {{ $step->step_number }}
                                            </span>
                                            <span class="flex-1 text-gray-700">{{ $step->name }}</span>
                                            <span class="text-xs text-gray-400">{{ str_replace('_', ' ', $step->status) }}</span>
                                        </div>
                                    @endforeach
                                    @if($batch->started_at)
                                        <p class="text-xs text-gray-400 pt-1">
                                            Started: {{ $batch->started_at->format('d M Y H:i') }}
                                            @if($batch->completed_at) · Completed: {{ $batch->completed_at->format('d M Y H:i') }} @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">

            {{-- Progress --}}
            <div class="card">
                <h3 class="text-base font-bold text-gray-800 mb-3">Progress</h3>
                @php $pct = $workOrder->planned_qty > 0 ? min(($workOrder->produced_qty / $workOrder->planned_qty) * 100, 100) : 0 @endphp
                <div class="mb-3">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Completion</span>
                        <span>{{ number_format($pct, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full {{ $pct >= 100 ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Planned:</span>
                        <span class="font-medium">{{ number_format($workOrder->planned_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Produced:</span>
                        <span class="font-medium">{{ number_format($workOrder->produced_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Batches:</span>
                        <span class="font-medium">{{ $workOrder->batches->count() }}</span>
                    </div>
                </div>
            </div>

            {{-- Issues --}}
            <div class="card">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-bold text-gray-800">Issues</h3>
                    <a href="{{ route('admin.issues.index', ['search' => $workOrder->order_no]) }}" class="text-xs text-blue-600 hover:underline">Manage →</a>
                </div>
                @if($workOrder->issues->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-3">No issues.</p>
                @else
                    <div class="space-y-2">
                        @foreach($workOrder->issues as $issue)
                            <div class="p-2 rounded-lg text-xs {{ in_array($issue->status, ['OPEN','ACKNOWLEDGED']) && $issue->isBlocking() ? 'bg-red-50' : 'bg-gray-50' }}">
                                <div class="flex justify-between">
                                    <span class="font-medium text-gray-800">{{ $issue->issueType->name }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-xs
                                        @if($issue->status === 'OPEN')         bg-red-100 text-red-700
                                        @elseif($issue->status === 'ACKNOWLEDGED') bg-yellow-100 text-yellow-700
                                        @elseif($issue->status === 'RESOLVED') bg-green-100 text-green-700
                                        @else                                  bg-gray-100 text-gray-500
                                        @endif">
                                        {{ $issue->status }}
                                    </span>
                                </div>
                                <p class="text-gray-600 mt-1 truncate">{{ $issue->title }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection
