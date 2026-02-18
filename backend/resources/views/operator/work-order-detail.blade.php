@extends('layouts.app')

@section('title', 'Work Order Detail')

@section('content')
<div class="max-w-7xl mx-auto"
     x-data="{ createBatchOpen: false, reportIssueOpen: false }">

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-3xl font-bold text-gray-800">{{ $workOrder->order_no }}</h1>
                <span class="px-4 py-2 rounded-full text-sm font-medium
                    @if($workOrder->status === 'PENDING')      bg-gray-100 text-gray-800
                    @elseif($workOrder->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                    @elseif($workOrder->status === 'DONE')     bg-green-100 text-green-800
                    @elseif($workOrder->status === 'BLOCKED')  bg-red-100 text-red-800
                    @else                                       bg-gray-100 text-gray-500
                    @endif">
                    {{ ucfirst(str_replace('_', ' ', $workOrder->status)) }}
                </span>
            </div>
            @if($workOrder->productType)
                <p class="text-gray-600 mt-2">{{ $workOrder->productType->name }}</p>
            @endif
        </div>
        <a href="{{ route('operator.queue') }}" class="btn-touch btn-secondary">← Back to Queue</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Work Order Details --}}
            <div class="card">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Work Order Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Order Number</p>
                        <p class="font-medium text-gray-800 font-mono">{{ $workOrder->order_no }}</p>
                    </div>
                    @if($workOrder->productType)
                        <div>
                            <p class="text-sm text-gray-500">Product Type</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->productType->name }}</p>
                        </div>
                    @endif
                    @if($workOrder->line)
                        <div>
                            <p class="text-sm text-gray-500">Line</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->line->name }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-sm text-gray-500">Priority</p>
                        <p class="font-medium text-gray-800">{{ $workOrder->priority }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Planned Quantity</p>
                        <p class="font-medium text-gray-800">{{ number_format($workOrder->planned_qty, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Produced Quantity</p>
                        <p class="font-medium text-gray-800">
                            {{ number_format($workOrder->produced_qty, 2) }}
                            @if($workOrder->planned_qty > 0)
                                <span class="text-sm text-gray-500">
                                    ({{ number_format(($workOrder->produced_qty / $workOrder->planned_qty) * 100, 1) }}%)
                                </span>
                            @endif
                        </p>
                    </div>
                    @if($workOrder->due_date)
                        <div>
                            <p class="text-sm text-gray-500">Due Date</p>
                            <p class="font-medium {{ $workOrder->due_date->isPast() && $workOrder->status !== 'DONE' ? 'text-red-600' : 'text-gray-800' }}">
                                {{ $workOrder->due_date->format('d M Y') }}
                            </p>
                        </div>
                    @endif
                    @if($workOrder->description)
                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-500">Description</p>
                            <p class="font-medium text-gray-800">{{ $workOrder->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Batches --}}
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Batches</h2>
                    @if(!in_array($workOrder->status, ['DONE','CANCELLED','BLOCKED']))
                        <button @click="createBatchOpen = true" class="btn-touch btn-primary text-sm">
                            + Create Batch
                        </button>
                    @endif
                </div>

                @if($workOrder->batches->isEmpty())
                    <div class="text-center py-8 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">No batches created yet.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($workOrder->batches as $batch)
                            <div class="border border-gray-200 rounded-lg p-4"
                                 x-data="{ expanded: {{ $loop->first ? 'true' : 'false' }} }">
                                <div class="flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
                                    <div class="flex items-center gap-4">
                                        <h3 class="text-lg font-bold text-gray-800">Batch #{{ $batch->batch_number }}</h3>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            @if($batch->status === 'PENDING')      bg-gray-100 text-gray-700
                                            @elseif($batch->status === 'IN_PROGRESS') bg-blue-100 text-blue-700
                                            @elseif($batch->status === 'DONE')     bg-green-100 text-green-700
                                            @else                                  bg-gray-100 text-gray-400
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $batch->status)) }}
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            {{ number_format($batch->produced_qty, 2) }} / {{ number_format($batch->target_qty, 2) }}
                                        </span>
                                    </div>
                                    <svg class="w-6 h-6 text-gray-400 transform transition-transform"
                                         :class="{ 'rotate-180': expanded }"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>

                                <div x-show="expanded" x-transition class="mt-4">
                                    @livewire('batch-step-list', ['batchId' => $batch->id], key('batch-'.$batch->id))
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
                <h3 class="text-lg font-bold text-gray-800 mb-4">Progress</h3>
                @php $pct = $workOrder->planned_qty > 0 ? min(($workOrder->produced_qty / $workOrder->planned_qty) * 100, 100) : 0 @endphp
                <div class="mb-4">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>Completion</span>
                        <span>{{ number_format($pct, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="{{ $pct >= 100 ? 'bg-green-500' : 'bg-blue-600' }} h-4 rounded-full transition-all"
                             style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Planned:</span>
                        <span class="font-medium">{{ number_format($workOrder->planned_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Produced:</span>
                        <span class="font-medium">{{ number_format($workOrder->produced_qty, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Remaining:</span>
                        <span class="font-medium">{{ number_format(max($workOrder->planned_qty - $workOrder->produced_qty, 0), 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Issues --}}
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Issues</h3>
                    @if(!in_array($workOrder->status, ['DONE','CANCELLED']))
                        <button @click="reportIssueOpen = true"
                                class="text-sm text-red-600 hover:text-red-800 font-medium border border-red-200 hover:border-red-400 px-3 py-1 rounded-lg transition-colors">
                            + Report
                        </button>
                    @endif
                </div>

                @if($workOrder->issues->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-4">No issues reported.</p>
                @else
                    <div class="space-y-3">
                        @foreach($workOrder->issues->take(5) as $issue)
                            <div class="p-3 rounded-lg {{ $issue->isBlocking() ? 'bg-red-50 border border-red-100' : 'bg-gray-50' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold text-gray-800">{{ $issue->issueType->name }}</span>
                                    <span class="px-2 py-0.5 rounded text-xs font-medium
                                        @if($issue->status === 'OPEN')             bg-red-100 text-red-700
                                        @elseif($issue->status === 'ACKNOWLEDGED') bg-yellow-100 text-yellow-700
                                        @elseif($issue->status === 'RESOLVED')     bg-green-100 text-green-700
                                        @else                                      bg-gray-100 text-gray-400
                                        @endif">
                                        {{ $issue->status }}
                                    </span>
                                </div>
                                <p class="text-sm font-medium text-gray-700">{{ $issue->title }}</p>
                                @if($issue->description)
                                    <p class="text-xs text-gray-500 mt-1">{{ Str::limit($issue->description, 80) }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $issue->reported_at->diffForHumans() }}
                                    @if($issue->reportedBy) by {{ $issue->reportedBy->name }} @endif
                                </p>
                            </div>
                        @endforeach
                        @if($workOrder->issues->count() > 5)
                            <p class="text-xs text-gray-400 text-center">+{{ $workOrder->issues->count() - 5 }} more issues</p>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>

    {{-- Create Batch Modal --}}
    <div x-show="createBatchOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="createBatchOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Create New Batch</h3>
                @php $remaining = max($workOrder->planned_qty - $workOrder->produced_qty, 0) @endphp
                <form action="{{ route('operator.batch.store') }}" method="POST"
                      x-data="{ quantity: {{ $remaining }} }">
                    @csrf
                    <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
                    <div class="mb-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="actual_qty" x-model="quantity"
                               step="0.01" min="0.01" max="{{ $remaining }}"
                               class="form-input w-full" required>
                        <p class="mt-1 text-sm text-gray-500">
                            Remaining: {{ number_format($remaining, 2) }}
                        </p>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="createBatchOpen = false" class="btn-touch btn-secondary">Cancel</button>
                        <button type="submit" class="btn-touch btn-primary">Create Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Report Issue Modal --}}
    <div x-show="reportIssueOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="reportIssueOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Report Issue</h3>
                <form action="{{ route('operator.issue.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Issue Type <span class="text-red-500">*</span></label>
                            <select name="issue_type_id" class="form-input w-full" required>
                                <option value="">— Select type —</option>
                                @foreach($issueTypes as $type)
                                    <option value="{{ $type->id }}">
                                        {{ $type->name }}
                                        @if($type->is_blocking) ⚠ Blocking @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" class="form-input w-full"
                                   placeholder="Brief summary of the issue" required maxlength="255">
                        </div>

                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-input w-full"
                                      placeholder="Additional details…" maxlength="2000"></textarea>
                        </div>
                    </div>

                    <div class="flex gap-3 justify-end mt-6">
                        <button type="button" @click="reportIssueOpen = false" class="btn-touch btn-secondary">Cancel</button>
                        <button type="submit" class="btn-touch btn-primary bg-red-600 hover:bg-red-700">Report Issue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>

@push('scripts')
<script>
setTimeout(() => location.reload(), 30000);
</script>
@endpush
@endsection
