@extends('layouts.app')

@section('title', 'Work Order Detail')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-gray-800">{{ $workOrder->order_no }}</h1>
                <span class="px-4 py-2 rounded-full text-sm font-medium
                    @if($workOrder->status === 'PENDING') bg-gray-100 text-gray-800
                    @elseif($workOrder->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                    @elseif($workOrder->status === 'COMPLETED') bg-green-100 text-green-800
                    @elseif($workOrder->status === 'BLOCKED') bg-red-100 text-red-800
                    @endif">
                    {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
                </span>
            </div>
            <p class="text-gray-600 mt-2">{{ $workOrder->productType->name }}</p>
        </div>
        <a href="{{ route('operator.queue') }}" class="btn-touch btn-secondary">
            Back to Queue
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content (Left Column - 2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Work Order Details Card -->
            <div class="card">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Work Order Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Order Number</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->order_no }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Product Type</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->productType->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Line</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->line->name }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Priority</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->priority }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Planned Quantity</p>
                        <p class="font-medium text-gray-800">{{ number_format($workOrder->planned_qty, 2) }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600">Completed Quantity</p>
                        <p class="font-medium text-gray-800">
                            {{ number_format($workOrder->completed_qty, 2) }}
                            <span class="text-sm text-gray-500">
                                ({{ number_format(($workOrder->completed_qty / $workOrder->planned_qty) * 100, 1) }}%)
                            </span>
                        </p>
                    </div>

                    @if($workOrder->due_date)
                        <div>
                            <p class="text-sm text-gray-600">Due Date</p>
                            <p class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($workOrder->due_date)->format('M d, Y') }}</p>
                        </div>
                    @endif

                    @if($workOrder->description)
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-600">Description</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Batches Section -->
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Batches</h2>
                    @if($workOrder->status !== 'COMPLETED')
                        <button
                            x-data
                            @click="$dispatch('open-create-batch-modal')"
                            class="btn-touch btn-primary text-sm"
                        >
                            + Create Batch
                        </button>
                    @endif
                </div>

                @if($workOrder->batches->isEmpty())
                    <div class="text-center py-8 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">No batches created yet</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($workOrder->batches as $batch)
                            <div class="border border-gray-200 rounded-lg p-4" x-data="{ expanded: {{ $loop->first ? 'true' : 'false' }} }">
                                <!-- Batch Header -->
                                <div class="flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
                                    <div class="flex items-center gap-4">
                                        <h3 class="text-lg font-bold text-gray-800">Batch #{{ $batch->batch_no }}</h3>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            @if($batch->status === 'PENDING') bg-gray-100 text-gray-800
                                            @elseif($batch->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                                            @elseif($batch->status === 'COMPLETED') bg-green-100 text-green-800
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $batch->status)) }}
                                        </span>
                                        <span class="text-sm text-gray-600">
                                            Qty: {{ number_format($batch->actual_qty, 2) }}
                                        </span>
                                    </div>
                                    <svg class="w-6 h-6 text-gray-500 transform transition-transform" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>

                                <!-- Batch Steps (Livewire Component) -->
                                <div x-show="expanded" x-transition class="mt-4">
                                    @livewire('batch-step-list', ['batchId' => $batch->id], key('batch-'.$batch->id))
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar (Right Column - 1/3) -->
        <div class="space-y-6">
            <!-- Progress Card -->
            <div class="card">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Progress</h3>

                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>Completion</span>
                        <span>{{ number_format(($workOrder->completed_qty / $workOrder->planned_qty) * 100, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="bg-blue-600 h-4 rounded-full" style="width: {{ min(($workOrder->completed_qty / $workOrder->planned_qty) * 100, 100) }}%"></div>
                    </div>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Planned:</span>
                        <span class="font-medium">{{ number_format($workOrder->planned_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Completed:</span>
                        <span class="font-medium">{{ number_format($workOrder->completed_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Remaining:</span>
                        <span class="font-medium">{{ number_format($workOrder->planned_qty - $workOrder->completed_qty, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Issues Card -->
            <div class="card">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Issues</h3>

                @if($workOrder->issues->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-4">No issues reported</p>
                @else
                    <div class="space-y-3">
                        @foreach($workOrder->issues->take(5) as $issue)
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-800">{{ $issue->issueType->name }}</span>
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($issue->is_blocking) bg-red-100 text-red-800
                                        @else bg-yellow-100 text-yellow-800
                                        @endif">
                                        {{ $issue->is_blocking ? 'Blocking' : 'Non-blocking' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">{{ Str::limit($issue->description, 100) }}</p>
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ \Carbon\Carbon::parse($issue->reported_at)->diffForHumans() }}
                                    @if($issue->reportedBy)
                                        by {{ $issue->reportedBy->name }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Create Batch Modal (Alpine.js) -->
<div
    x-data="{ open: false }"
    @open-create-batch-modal.window="open = true"
    @close-create-batch-modal.window="open = false"
    x-show="open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
>
    <!-- Overlay -->
    <div class="fixed inset-0 bg-black bg-opacity-50" @click="open = false"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6"
            @click.stop
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            <h3 class="text-xl font-bold text-gray-800 mb-4">Create New Batch</h3>

            <form action="{{ route('operator.batch.store') }}" method="POST" x-data="{ quantity: {{ $workOrder->planned_qty - $workOrder->completed_qty }} }">
                @csrf
                <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">

                <!-- Quantity -->
                <div class="mb-4">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input
                        type="number"
                        id="quantity"
                        name="actual_qty"
                        x-model="quantity"
                        step="0.01"
                        min="0.01"
                        max="{{ $workOrder->planned_qty - $workOrder->completed_qty }}"
                        class="form-input w-full"
                        required
                    >
                    <p class="mt-1 text-sm text-gray-500">
                        Remaining: {{ number_format($workOrder->planned_qty - $workOrder->completed_qty, 2) }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 justify-end">
                    <button type="button" @click="open = false" class="btn-touch btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn-touch btn-primary">
                        Create Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-refresh page every 30 seconds to get latest data
setTimeout(() => {
    location.reload();
}, 30000);
</script>
@endpush
