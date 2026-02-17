<div wire:poll.5s class="space-y-4">
    @if($batch)
        <!-- Batch Info -->
        <div class="card bg-blue-50 border border-blue-200">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Batch #{{ $batch->batch_no }}</h3>
                    <p class="text-sm text-gray-600">Quantity: {{ number_format($batch->actual_qty, 2) }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    @if($batch->status === 'PENDING') bg-gray-100 text-gray-800
                    @elseif($batch->status === 'IN_PROGRESS') bg-blue-100 text-blue-800
                    @elseif($batch->status === 'COMPLETED') bg-green-100 text-green-800
                    @endif">
                    {{ ucfirst(str_replace('_', ' ', $batch->status)) }}
                </span>
            </div>
        </div>

        <!-- Steps List -->
        <div class="space-y-3">
            @foreach($batch->steps->sortBy('step_order') as $step)
                <div class="card
                    @if($step->status === 'COMPLETED') bg-green-50 border border-green-200
                    @elseif($step->status === 'IN_PROGRESS') bg-blue-50 border border-blue-200
                    @else bg-white
                    @endif">

                    <div class="flex items-start justify-between gap-4">
                        <!-- Step Info -->
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="flex items-center justify-center h-8 w-8 rounded-full
                                    @if($step->status === 'COMPLETED') bg-green-500 text-white
                                    @elseif($step->status === 'IN_PROGRESS') bg-blue-500 text-white
                                    @else bg-gray-300 text-gray-700
                                    @endif">
                                    {{ $step->step_order }}
                                </span>
                                <h4 class="text-lg font-bold text-gray-800">{{ $step->name }}</h4>
                            </div>

                            @if($step->description)
                                <p class="text-sm text-gray-600 mb-2 ml-11">{{ $step->description }}</p>
                            @endif

                            <!-- Step Details -->
                            <div class="ml-11 text-sm text-gray-600 space-y-1">
                                @if($step->started_at)
                                    <p>
                                        Started: {{ \Carbon\Carbon::parse($step->started_at)->format('M d, Y H:i') }}
                                        @if($step->startedBy)
                                            by {{ $step->startedBy->name }}
                                        @endif
                                    </p>
                                @endif

                                @if($step->completed_at)
                                    <p>
                                        Completed: {{ \Carbon\Carbon::parse($step->completed_at)->format('M d, Y H:i') }}
                                        @if($step->completedBy)
                                            by {{ $step->completedBy->name }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">
                            @if($step->status === 'PENDING')
                                <!-- Can only start if previous step is completed or this is first step -->
                                @php
                                    $previousStep = $batch->steps->where('step_order', $step->step_order - 1)->first();
                                    $canStart = !$previousStep || $previousStep->status === 'COMPLETED';
                                @endphp

                                <button
                                    wire:click="startStep({{ $step->id }})"
                                    @if(!$canStart) disabled @endif
                                    class="btn-touch btn-primary text-sm"
                                    :class="{ 'opacity-50 cursor-not-allowed': !$canStart }"
                                >
                                    Start
                                </button>
                            @elseif($step->status === 'IN_PROGRESS')
                                <button
                                    wire:click="completeStep({{ $step->id }})"
                                    class="btn-touch btn-success text-sm"
                                >
                                    Complete
                                </button>
                            @else
                                <span class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Done
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Flash Messages -->
        @if(session()->has('success'))
            <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session()->has('error'))
            <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                {{ session('error') }}
            </div>
        @endif
    @else
        <div class="card text-center py-8">
            <p class="text-gray-500">Batch not found</p>
        </div>
    @endif
</div>
